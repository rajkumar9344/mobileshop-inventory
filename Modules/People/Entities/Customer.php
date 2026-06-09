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
        'lock', 'outstanding', 'is_active', 'account_id', 'remarks', 'excess_amount'
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

    /**
     * Set the outstanding flag based on opening balance.
     * If $balance is provided, use it; otherwise use current model value.
     */
    public function setOutstandingFromBalance($balance = null)
    {
        $balance = $balance ?? $this->opening_balance ?? 0;
        $this->outstanding = ($balance > 0) ? 'Yes' : 'No';
        $this->save();
    }

}
