<?php

namespace Modules\Quotation\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Modules\People\Entities\Customer;

class Quotation extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function quotationDetails() {
        return $this->hasMany(QuotationDetails::class, 'quotation_id', 'id');
    }

    public function customer() {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    /**
     * Email logs related to this quotation (polymorphic)
     */
    public function emailLogs()
    {
        return $this->morphMany(\App\Models\EmailLog::class, 'emailable');
    }

    /**
     * Shortcut to the latest email log using Laravel's latestOfMany helper.
     */
    public function lastEmailLog()
    {
        return $this->morphOne(\App\Models\EmailLog::class, 'emailable')->latestOfMany();
    }

    public static function boot() {
        parent::boot();

        static::creating(function ($model) {
            $number = Quotation::max('id') + 1;
            $model->reference = make_reference_id('QT', $number);
        });
    }

    public function getDateAttribute($value) {
        return Carbon::parse($value)->format('d M, Y');
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

    /**
     * Convert major-unit amount (rupees) to minor units (paise) for storage.
     */
    protected function toMinor($value)
    {
        if ($value === null || $value === '') return null;
        if (is_string($value)) $value = str_replace(',', '', $value);
        return (int) round(floatval($value) * 100);
    }

    /* Mutators: accept rupee values and store as paise */
    public function setShippingAmountAttribute($value) { $this->attributes['shipping_amount'] = $this->toMinor($value); }
    public function setTotalAmountAttribute($value) { $this->attributes['total_amount'] = $this->toMinor($value); }
    public function setTaxAmountAttribute($value) { $this->attributes['tax_amount'] = $this->toMinor($value); }
    public function setDiscountAmountAttribute($value) { $this->attributes['discount_amount'] = $this->toMinor($value); }
    public function setOverallGrossAmountAttribute($value) { $this->attributes['overall_gross_amount'] = $this->toMinor($value); }
    public function setOverallTaxableAmountAttribute($value) { $this->attributes['overall_taxable_amount'] = $this->toMinor($value); }
    public function setOverallCgstAttribute($value) { $this->attributes['overall_cgst'] = $this->toMinor($value); }
    public function setOverallSgstAttribute($value) { $this->attributes['overall_sgst'] = $this->toMinor($value); }
    public function setOverallIgstAttribute($value) { $this->attributes['overall_igst'] = $this->toMinor($value); }
    public function setOverallTaxAmountAttribute($value) { $this->attributes['overall_tax_amount'] = $this->toMinor($value); }
    public function setOverallAmountAttribute($value) { $this->attributes['overall_amount'] = $this->toMinor($value); }
    public function setOverallOtherAttribute($value) { $this->attributes['overall_other'] = $this->toMinor($value); }
    public function setOverallAdjAttribute($value) { $this->attributes['overall_adj'] = $this->toMinor($value); }
    public function setOverallNetRateAttribute($value) { $this->attributes['overall_net_rate'] = $this->toMinor($value); }
}
