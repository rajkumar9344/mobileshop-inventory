<?php

namespace Modules\SalesReceipt\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesReceiptLine extends Model
{
    protected $table = 'sales_receipt_lines';
    protected $guarded = [];
    protected $casts = [
        'is_settled' => 'boolean',
        'settled_at' => 'datetime',
    ];

    public function receipt()
    {
        return $this->belongsTo(SalesReceipt::class, 'sales_receipt_id');
    }

    public function sale()
    {
        return $this->belongsTo(\Modules\Sale\Entities\Sale::class, 'sale_id');
    }

    // Accessors
    public function getBillAmountAttribute($value)
    {
        return $value / 100;
    }

    public function getReceivedBeforeAttribute($value)
    {
        return $value / 100;
    }

    public function getBalanceBeforeAttribute($value)
    {
        return $value / 100;
    }

    public function getPaymentAmountAttribute($value)
    {
        return $value / 100;
    }

    public function getDiscountAmountAttribute($value)
    {
        return $value / 100;
    }

    public function getFinalBalanceAttribute($value)
    {
        return $value / 100;
    }

    // Mutators
    public function setBillAmountAttribute($value)
    {
        $this->attributes['bill_amount'] = (int) round($value * 100);
    }

    public function setPaymentAmountAttribute($value)
    {
        $this->attributes['payment_amount'] = (int) round($value * 100);
    }

    public function setDiscountAmountAttribute($value)
    {
        $this->attributes['discount_amount'] = (int) round($value * 100);
    }

    public function setFinalBalanceAttribute($value)
    {
        $this->attributes['final_balance'] = (int) round($value * 100);
    }
     public function setReceivedBeforeAttribute($value)
    {
        $this->attributes['received_before'] = (int) round($value * 100);
    }

    public function setBalanceBeforeAttribute($value)
    {
        $this->attributes['balance_before'] = (int) round($value * 100);
    }

    // helpers
    public function isSettled()
    {
        return (bool) ($this->is_settled ?? false);
    }
}
