<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'company_name',
        'contact_person',
        'phone',
        'email',
        'description',
        'quantity',
        'unit_price',
        'total',
        'delivery_date',
        'status',
        'notes',
        'additional_costs',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
        'additional_costs' => 'array',
    ];

    public function payments()
    {
        return $this->hasMany(ContractPayment::class);
    }
}
