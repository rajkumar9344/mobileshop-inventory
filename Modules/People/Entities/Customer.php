<?php

namespace Modules\People\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{

    use HasFactory;

    // Allow mass assignment for these fields
    protected $fillable = [
        'customer_name', 'customer_code', 'customer_email', 'customer_phone', 'address', 'city', 'state', 'country',
        'pincode', 'area', 'gst_no', 'pan_no', 'aadhar_no', 'opening_balance', 'credit_limit', 'cash_discount',
        'additional_discount', 'discount_percent', 'terms_days', 'lock', 'outstanding', 'is_active', 'salesman', 'account_id', 'lr_through', 'remarks', 'excess_amount'
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'cash_discount' => 'decimal:2',
        'additional_discount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'terms_days' => 'integer',
        'is_active' => 'boolean',
            'excess_amount' => 'decimal:2',
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
