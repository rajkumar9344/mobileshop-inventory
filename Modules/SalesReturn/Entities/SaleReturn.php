<?php

namespace Modules\SalesReturn\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaleReturn extends Model
{
    use HasFactory;

    protected $guarded = [];
    
    protected $casts = [
        'create_receipt' => 'boolean',
    ];

    public function saleReturnDetails() {
        return $this->hasMany(SaleReturnDetail::class, 'sale_return_id', 'id');
    }

    public function saleReturnPayments() {
        return $this->hasMany(SaleReturnPayment::class, 'sale_return_id', 'id');
    }

    public function customer() {
        return $this->belongsTo(\Modules\People\Entities\Customer::class, 'customer_id');
    }

    public function salesReceipt()
    {
        return $this->hasOne(\Modules\SalesReceipt\Entities\SalesReceipt::class, 'sale_return_id', 'id');
    }

    public static function boot() {
        parent::boot();

        static::creating(function ($model) {
            $number = SaleReturn::max('id') + 1;
            $model->reference = make_reference_id('SLRN', $number);;
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

    // Mutators to store monetary values in paise
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

    /**
     * Convert an amount in major units (rupees) to minor units (paise)
     */
    protected function toMinor($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) $value = str_replace(',', '', $value);
        return (int) round((float)$value * 100);
    }

    // Overall calculation getters (return rupees)
    public function getOverallNosAttribute($value) { return $value; }
    public function getOverallQuantityAttribute($value) { return $value / 100; }
    public function getOverallGrossAmountAttribute($value) { return $value / 100; }
    public function getOverallTaxableAmountAttribute($value) { return $value / 100; }
    public function getOverallCgstAttribute($value) { return $value / 100; }
    public function getOverallSgstAttribute($value) { return $value / 100; }
    public function getOverallIgstAttribute($value) { return $value / 100; }
    public function getOverallTaxAmountAttribute($value) { return $value / 100; }
    public function getOverallAmountAttribute($value) { return $value / 100; }
    public function getOverallOtherAttribute($value) { return $value / 100; }
    public function getOverallAdjAttribute($value) { return $value / 100; }
    public function getOverallNetRateAttribute($value) { return $value / 100; }

    // Overall calculation setters (store paise)
    public function setOverallQuantityAttribute($value) { $this->attributes['overall_quantity'] = $this->toMinor($value); }
    public function setOverallGrossAmountAttribute($value) { $this->attributes['overall_gross_amount'] = $this->toMinor($value); }
    public function setOverallTaxableAmountAttribute($value) { $this->attributes['overall_taxable_amount'] = $this->toMinor($value); }
    public function setOverallTaxAmountAttribute($value) { $this->attributes['overall_tax_amount'] = $this->toMinor($value); }
    public function setOverallAmountAttribute($value) { $this->attributes['overall_amount'] = $this->toMinor($value); }


    public function setDiscountAmountAttribute($value) {
        $this->attributes['discount_amount'] = $this->toMinor($value);
    }
}
