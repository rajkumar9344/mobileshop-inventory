<?php

namespace Modules\Purchase\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\QueryException;
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
            $number = self::getNextPurchaseNumber();
            $model->reference = self::generatePurchaseReference($number);
        });
    }

    /**
     * Next purchase number, based on the highest existing reference NUMBER (not id).
     * Deleted/test rows advance the id auto-increment counter but must not cause the
     * visible bill numbering to jump — mirrors Sale::getNextSaleNumber().
     */
    public static function getNextPurchaseNumber(): int {
        $maxNumber = self::query()
            ->pluck('reference')
            ->reduce(function ($max, $reference) {
                if (preg_match('/^PU-?(\d+)$/', (string) $reference, $matches)) {
                    return max($max, (int) $matches[1]);
                }
                return $max;
            }, 0);

        return $maxNumber + 1;
    }

    /**
     * Canonical "PU00001" format (no dash) — matches the on-page preview and the
     * majority of existing data. Kept as a dedicated method (rather than the shared
     * dash-style make_reference_id() used by sibling modules) so every code path that
     * assigns a purchase reference stays in sync.
     */
    public static function generatePurchaseReference(int $number): string {
        return 'PU' . str_pad($number, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Retry creation on duplicate reference collisions (see Sale::createWithRetry).
     * A unique DB constraint on `reference` makes collisions impossible to persist;
     * this just regenerates a fresh number and retries instead of surfacing a 500.
     */
    public static function createWithRetry(array $attributes, int $maxAttempts = 3): self
    {
        $attempt = 1;

        while (true) {
            try {
                return self::create($attributes);
            } catch (QueryException $e) {
                if (!self::isDuplicateReferenceException($e) || $attempt >= $maxAttempts) {
                    throw $e;
                }

                usleep(50000 * $attempt);
                $attempt++;
            }
        }
    }

    protected static function isDuplicateReferenceException(QueryException $e): bool
    {
        $sqlState = (string) $e->getCode();
        $errorInfo = $e->errorInfo ?? [];
        $driverCode = (int) ($errorInfo[1] ?? 0);
        $message = strtolower((string) ($errorInfo[2] ?? $e->getMessage()));

        if ($sqlState !== '23000' && $driverCode !== 1062) {
            return false;
        }

        return str_contains($message, 'purchases_reference_unique')
            || str_contains($message, 'purchases.reference')
            || str_contains($message, "for key 'reference'");
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