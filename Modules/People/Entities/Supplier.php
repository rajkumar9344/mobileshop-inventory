<?php

namespace Modules\People\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_name',
        'supplier_code',
        'supplier_email',
        'supplier_phone',
        'area',
        'state',
        'city',
        'address',
        'open_balance',
        'excess_amount',
        'credit_limit',
        'tax_percent',
        'due_days',
        'status',
        'remarks',
    ];

    protected $casts = [
        'open_balance'  => 'decimal:2',
        'excess_amount' => 'decimal:2',
        'credit_limit'  => 'decimal:2',
        'tax_percent'   => 'decimal:2',
        'due_days'      => 'integer',
    ];

    protected static function newFactory() {
        return \Modules\People\Database\factories\SupplierFactory::new();
    }
}
