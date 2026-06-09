<?php

namespace Modules\PurchasesReturn\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseReturn extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'create_receipt' => 'boolean',
    ];

    public function purchaseReturnDetails() {
        return $this->hasMany(PurchaseReturnDetail::class, 'purchase_return_id', 'id');
    }

    public function purchaseReturnPayments() {
        return $this->hasMany(PurchaseReturnPayment::class, 'purchase_return_id', 'id');
    }

    public function supplier() {
        return $this->belongsTo(\Modules\People\Entities\Supplier::class, 'supplier_id');
    }

    public static function boot() {
        parent::boot();

        static::creating(function ($model) {
            $number = PurchaseReturn::max('id') + 1;
            $model->reference = make_reference_id('PRRN', $number);
        });
    }

    public function scopeCompleted($query) {
        return $query->where('status', 'Completed');
    }

    public function getShippingAmountAttribute($value) {
        return $value / 100;
    }

    public function getPaidAmountAttribute($value) {
        return $value / 100;
    }

    public function getTotalAmountAttribute($value) {
        return $value / 100;
    }

    public function getDueAmountAttribute($value) {
        return $value / 100;
    }

    public function getTaxAmountAttribute($value) {
        return $value / 100;
    }

    public function getDiscountAmountAttribute($value) {
        return $value / 100;
    }

    // Mutators: accept rupee values and store paise
    public function setShippingAmountAttribute($value) {
        $this->attributes['shipping_amount'] = $this->toMinor($value);
    }

    public function setPaidAmountAttribute($value) {
        $this->attributes['paid_amount'] = $this->toMinor($value);
    }

    public function setTotalAmountAttribute($value) {
        $this->attributes['total_amount'] = $this->toMinor($value);
    }

    public function setDueAmountAttribute($value) {
        $this->attributes['due_amount'] = $this->toMinor($value);
    }

    public function setTaxAmountAttribute($value) {
        $this->attributes['tax_amount'] = $this->toMinor($value);
    }

    public function setDiscountAmountAttribute($value) {
        $this->attributes['discount_amount'] = $this->toMinor($value);
    }

    // Accessors for overall calculation fields (paise <-> rupees)
    public function getOverallGrossAmountAttribute($value) {
        return $value / 100;
    }

    public function getOverallTaxableAmountAttribute($value) {
        return $value / 100;
    }

    public function getOverallCgstAttribute($value) {
        return $value / 100;
    }

    public function getOverallSgstAttribute($value) {
        return $value / 100;
    }

    public function getOverallIgstAttribute($value) {
        return $value / 100;
    }

    public function getOverallTaxAmountAttribute($value) {
        return $value / 100;
    }

    public function getOverallAmountAttribute($value) {
        return $value / 100;
    }

    public function getOverallOtherAttribute($value) {
        return $value / 100;
    }

    public function getOverallAdjAttribute($value) {
        return $value / 100;
    }

    public function getOverallNetRateAttribute($value) {
        return $value / 100;
    }

    // Mutators for overall calculation fields (store paise)
    public function setOverallGrossAmountAttribute($value) {
        $this->attributes['overall_gross_amount'] = $this->toMinor($value);
    }

    public function setOverallTaxableAmountAttribute($value) {
        $this->attributes['overall_taxable_amount'] = $this->toMinor($value);
    }

    public function setOverallTaxAmountAttribute($value) {
        $this->attributes['overall_tax_amount'] = $this->toMinor($value);
    }

    public function setOverallAmountAttribute($value) {
        $this->attributes['overall_amount'] = $this->toMinor($value);
    }

    /**
     * Convert major-unit amount (string like '1,000' or float) to minor units (int paise)
     */
    protected function toMinor($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(',', '', $value);
        }

        return (int) round((float) $value * 100);
    }
}
