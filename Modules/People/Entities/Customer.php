<?php

namespace Modules\People\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{

    use HasFactory;

    protected $fillable = [
        'customer_name', 'customer_code', 'customer_email', 'customer_phone', 'address', 'city', 'state',
        'pincode', 'area', 'vat_id', 'opening_balance', 'credit_limit',
        'lock', 'is_active', 'account_id', 'remarks', 'excess_amount'
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'credit_limit'    => 'decimal:2',
        'excess_amount'   => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    protected static function newFactory() {
        return \Modules\People\Database\factories\CustomerFactory::new();
    }

    public function getOpeningBalanceFormattedAttribute() {
        return number_format($this->opening_balance, 2, '.', '');
    }

}
