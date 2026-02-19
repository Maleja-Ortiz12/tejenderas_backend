<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index()
    {
        $contracts = Contract::with('payments')->orderBy('delivery_date', 'asc')->get();
        return response()->json($contracts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'description' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'delivery_date' => 'required|date',
            'notes' => 'nullable|string',
            'additional_costs' => 'nullable|array',
            'additional_costs.*.description' => 'required|string',
            'additional_costs.*.amount' => 'required|numeric|min:0',
            'is_paid' => 'boolean' // New field
        ]);

        $baseTotal = $validated['quantity'] * $validated['unit_price'];
        $additionalCostsTotal = 0;

        if (isset($validated['additional_costs'])) {
            foreach ($validated['additional_costs'] as $cost) {
                $additionalCostsTotal += $cost['amount'];
            }
        }

        $validated['total'] = $baseTotal + $additionalCostsTotal;
        $validated['status'] = 'pending';

        $contract = Contract::create($validated);

        // Handle Pay in Full
        if ($request->boolean('is_paid')) {
            $contract->payments()->create([
                'amount' => $contract->total,
                'payment_date' => now(),
                'payment_method' => 'cash', // Default to cash for initial full payment, or could be a parameter
                'notes' => 'Pago completo inicial',
            ]);
            // Optionally set status to something else? completed? 
            // Usually 'delivered' means the goods are delivered. Payment can be complete before delivery.
            // Let's keep status as pending unless user changes it, but maybe useful to mark as "paid"?
            // We just track payments.
        }

        return response()->json([
            'message' => 'Contrato creado exitosamente',
            'contract' => $contract->load('payments')
        ], 201);
    }

    public function show(Contract $contract)
    {
        return response()->json($contract->load('payments'));
    }

    public function update(Request $request, Contract $contract)
    {
        $validated = $request->validate([
            'company_name' => 'sometimes|string|max:255',
            'contact_person' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:50',
            'email' => 'nullable|email|max:255',
            'description' => 'sometimes|string',
            'quantity' => 'sometimes|integer|min:1',
            'unit_price' => 'sometimes|numeric|min:0',
            'delivery_date' => 'sometimes|date',
            'status' => 'sometimes|in:pending,in_progress,completed,cancelled,delivered',
            'notes' => 'nullable|string',
            'payment_method' => 'nullable|in:cash,card,transfer', // For "Mark as Paid"
            'additional_costs' => 'nullable|array',
            'additional_costs.*.description' => 'required|string',
            'additional_costs.*.amount' => 'required|numeric|min:0',
        ]);

        // Recalculate total logic
        if ($request->hasAny(['quantity', 'unit_price', 'additional_costs'])) {
             $quantity = $validated['quantity'] ?? $contract->quantity;
             $unitPrice = $validated['unit_price'] ?? $contract->unit_price;
             $additionalCosts = $validated['additional_costs'] ?? $contract->additional_costs;

             $baseTotal = $quantity * $unitPrice;
             $additionalCostsTotal = 0;
             if ($additionalCosts) {
                 foreach ($additionalCosts as $cost) {
                     $additionalCostsTotal += $cost['amount'];
                 }
             }
             $validated['total'] = $baseTotal + $additionalCostsTotal;
        }

        // Check if status changed to 'delivered' (Pagado) and create payment for remaining balance
        if (($validated['status'] ?? null) === 'delivered' && $contract->status !== 'delivered') {
            $currentPaid = $contract->payments()->sum('amount');
            $newTotal = $validated['total'] ?? $contract->total;
            $remaining = $newTotal - $currentPaid;

            if ($remaining > 0) {
                $contract->payments()->create([
                    'amount' => $remaining,
                    'payment_date' => now(),
                    'payment_method' => $validated['payment_method'] ?? 'cash',
                    'notes' => 'Pago automático al marcar como Pagado/Entregado',
                ]);
            }
        }

        $contract->update($validated);

        return response()->json([
            'message' => 'Contrato actualizado',
            'contract' => $contract->load('payments')
        ]);
    }

    public function addPayment(Request $request, Contract $contract)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,card,transfer',
            'notes' => 'nullable|string',
        ]);

        $payment = $contract->payments()->create($validated);

        return response()->json([
            'message' => 'Abono registrado exitosamente',
            'payment' => $payment,
            'contract' => $contract->fresh('payments')
        ], 201);
    }

    public function destroy(Contract $contract)
    {
        $contract->delete();
        return response()->json(['message' => 'Contrato eliminado']);
    }

    public function extend(Request $request, Contract $contract)
    {
        \Illuminate\Support\Facades\Log::info('Contract extension request started for contract ' . $contract->id);
        
        $validated = $request->validate([
            'new_date' => 'required|date|after:today',
            'reason'   => 'nullable|string|max:500',
        ]);

        \Illuminate\Support\Facades\Log::info('Validation passed', $validated);

        $contract->delivery_date = $validated['new_date'];
        $contract->save();

        \Illuminate\Support\Facades\Log::info('Contract updated in DB');

        // Send Email if email is present
        if ($contract->email) {
            \Illuminate\Support\Facades\Log::info('Attempting to send email to ' . $contract->email);
            try {
                \Illuminate\Support\Facades\Mail::to($contract->email)->send(new \App\Mail\ContractExtended($contract, $request->input('reason')));
                \Illuminate\Support\Facades\Log::info('Email sent successfully');
            } catch (\Exception $e) {
                // Log error but don't fail the request
                \Illuminate\Support\Facades\Log::error('Error sending extension email: ' . $e->getMessage());
            }
        } else {
            \Illuminate\Support\Facades\Log::info('No email address for contract, skipping email');
        }

        return response()->json([
            'message' => 'Contrato extendido exitosamente',
            'contract' => $contract->load('payments')
        ]);
    }
}
