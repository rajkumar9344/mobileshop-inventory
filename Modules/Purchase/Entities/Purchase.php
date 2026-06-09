<?php

namespace Modules\Purchase\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasStatusBadge;
use Modules\People\Entities\Supplier;

class Purchase extends Model
{
    use HasFactory, HasStatusBadge;

    protected $guarded = [];

    public function purchaseDetails() {
        return $this->hasMany(PurchaseDetail::class, 'purchase_id', 'id');
    }

    public function purchasePayments() {
        return $this->hasMany(PurchasePayment::class, 'purchase_id', 'id');
    }

    /**
     * Supplier for this purchase (nullable)
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    /**
     * Email logs related to this purchase (polymorphic)
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
            $number = Purchase::max('id') + 1;
            $model->reference = make_reference_id('PU', $number);
        });
    }

    // public function getStatusBadgeAttribute()
    // {
    //     // For purchases, show "Draft" instead of "Pending" for better UX
    //     $status = $this->status;
    //     $statusText = $status;

    //     if ($status === 'Pending') {
    //         $statusText = 'Draft';
    //         $statusClass = 'badge-warning';
    //     } elseif ($status === 'Completed') {
    //         $statusClass = 'badge-success';
    //     } else {
    //         $statusClass = 'badge-secondary';
    //     }

    //     return '<span class="badge ' . $statusClass . '">' . $statusText . '</span>';
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

    public function getBalanceAttribute($value) {
        return $value / 100;
    }

    public function getDiscountAmountAttribute($value) {
        return $value / 100;
    }

    /**
     * Convert a major-unit amount (e.g. '3,000' or 30.5) to minor units (int)
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

    // Mutators for monetary fields
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

    public function setBalanceAttribute($value) {
        $this->attributes['balance'] = $this->toMinor($value);
    }

    public function setDiscountAmountAttribute($value) {
        $this->attributes['discount_amount'] = $this->toMinor($value);
    }

    // Accessors for overall calculation fields
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

    // Mutators for overall calculation fields
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

    protected function getTotalAmountForStatus() {
        return $this->overall_amount ?? $this->total_amount ?? 0;
    }
}