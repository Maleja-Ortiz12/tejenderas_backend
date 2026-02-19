<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    /**
     * Display a listing of sales.
     */
    public function index(Request $request)
    {
        // 1. POS Sales
        $salesQuery = Sale::with(['user', 'items.product']);
        
        // 2. Orders (Only completed)
        $ordersQuery = \App\Models\Order::with('user', 'items')->where('status', 'completed');
        
        // 3. Contract Payments
        $paymentsQuery = \App\Models\ContractPayment::with('contract');

        // Apply Date Filters
        if ($request->period === 'daily') {
            $today = now()->today();
            $salesQuery->whereDate('created_at', $today);
            $ordersQuery->whereDate('created_at', $today);
            $paymentsQuery->whereDate('payment_date', $today);
        } elseif ($request->period === 'weekly') {
            $range = [now()->startOfWeek(), now()->endOfWeek()];
            $salesQuery->whereBetween('created_at', $range);
            $ordersQuery->whereBetween('created_at', $range);
            $paymentsQuery->whereBetween('payment_date', $range);
        } elseif ($request->period === 'monthly') {
            $range = [now()->startOfMonth(), now()->endOfMonth()];
            $salesQuery->whereBetween('created_at', $range);
            $ordersQuery->whereBetween('created_at', $range);
            $paymentsQuery->whereBetween('payment_date', $range);
        }

        // Fetch Data
        $sales = $salesQuery->orderBy('created_at', 'desc')->get();
        $orders = $ordersQuery->orderBy('created_at', 'desc')->get();
        $payments = $paymentsQuery->orderBy('payment_date', 'desc')->get();

        // Calculate Stats
        $posTotal = (float) $sales->sum('total');
        $ordersTotal = (float) $orders->sum('total');
        $contractsTotal = (float) $payments->sum('amount');
        $totalSales = $posTotal + $ordersTotal + $contractsTotal;

        $telasTotal = 0;
        $perfumeriaTotal = 0;

        // Transform POS Sales & Calc Category Stats
        $sales->transform(function ($sale) use (&$telasTotal, &$perfumeriaTotal) {
            $sale->type = 'pos';
            $saleTelas = 0;
            $salePerfumeria = 0;
            
            foreach ($sale->items as $item) {
                if ($item->product && $item->product->category === 'perfumeria') {
                    $salePerfumeria += $item->subtotal;
                } else {
                    $saleTelas += $item->subtotal;
                }
            }
            $telasTotal += $saleTelas;
            $perfumeriaTotal += $salePerfumeria;

            $sale->telas_total = $saleTelas;
            $sale->perfumeria_total = $salePerfumeria;
            return $sale;
        });

        // Transform Orders
        $orders->transform(function($order) {
            $order->type = 'order';
            $order->payment_method = 'web'; 
            return $order;
        });

        // Transform Payments
        $payments->transform(function($payment) {
            $payment->type = 'contract_payment';
            $payment->created_at = $payment->payment_date;
            $payment->total = $payment->amount; // Normalize amount to total
            // Create a mock user object for display consistency
            $payment->user = [
                'name' => $payment->contract ? ($payment->contract->company_name . ' (' . $payment->contract->contact_person . ')') : 'Contrato Eliminado'
            ];
            $payment->details = $payment->notes;
            return $payment;
        });

        // Merge and Sort
        $allTransactions = $sales->concat($orders)->concat($payments)->sortByDesc('created_at')->values();

        // Pagination
        $page = $request->input('page', 1);
        $perPage = 20;
        $paginatedItems = new \Illuminate\Pagination\LengthAwarePaginator(
            $allTransactions->forPage($page, $perPage)->values(),
            $allTransactions->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'stats' => [
                'total' => $totalSales,
                'pos_total' => $posTotal,
                'orders_total' => $ordersTotal,
                'contracts_total' => $contractsTotal,
                'telas' => $telasTotal,
                'perfumeria' => $perfumeriaTotal
            ],
            'sales' => $paginatedItems
        ]);
    }

    /**
     * Lookup product by barcode for POS.
     */
    public function lookupBarcode(Request $request)
    {
        $request->validate(['barcode' => 'required|string']);

        $product = Product::where('barcode', $request->barcode)->first();

        if (!$product) {
            return response()->json([
                'message' => 'Producto no encontrado'
            ], 404);
        }

        if ($product->stock <= 0) {
            return response()->json([
                'message' => 'Producto sin stock disponible',
                'product' => $product
            ], 400);
        }

        return response()->json($product);
    }

    /**
     * Store a new sale with items.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:cash,card,transfer',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $total = 0;
            $itemsData = [];

            // Validate stock and calculate totals
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    return response()->json([
                        'message' => "Stock insuficiente para {$product->name}. Disponible: {$product->stock}"
                    ], 400);
                }

                $subtotal = $product->price * $item['quantity'];
                $total += $subtotal;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal' => $subtotal,
                ];

                // Reduce stock
                $product->decrement('stock', $item['quantity']);
            }

            // Create sale
            $sale = Sale::create([
                'user_id' => $request->user()->id,
                'total' => $total,
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Create sale items
            foreach ($itemsData as $itemData) {
                $sale->items()->create($itemData);
            }

            DB::commit();

            return response()->json([
                'message' => 'Venta registrada exitosamente',
                'sale' => $sale->load('items.product'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Sale creation failed: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'message' => 'Error al procesar la venta',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Display a specific sale.
     */
    public function show(Sale $sale)
    {
        return response()->json($sale->load(['user', 'items.product']));
    }
}
