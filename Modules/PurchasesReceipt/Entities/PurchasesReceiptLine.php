<?php

namespace Modules\PurchasesReceipt\Entities;

use Illuminate\Database\Eloquent\Model;

class PurchasesReceiptLine extends Model
{
    protected $table = 'purchases_receipt_lines';
    protected $guarded = [];
    protected $casts = [
        'is_settled' => 'boolean',
        'settled_at' => 'datetime',
    ];

    public function receipt()
    {
        return $this->belongsTo(PurchasesReceipt::class, 'purchases_receipt_id');
    }

    public function purchase()
    {
        return $this->belongsTo(\Modules\Purchase\Entities\Purchase::class, 'purchase_id');
    }

    // Accessors
    public function getBillAmountAttribute($value)
    {
        return $value / 100;
    }

    public function getPaidBeforeAttribute($value)
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

    public function setPaidBeforeAttribute($value)
    {
        $this->attributes['paid_before'] = (int) round($value * 100);
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