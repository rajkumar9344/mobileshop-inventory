<?php

namespace Modules\Sale\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Traits\HasStatusBadge;

class Sale extends Model
{
    use HasFactory, HasStatusBadge;

    protected $guarded = [];

    // Bill type codes and labels
    const BILL_CASH = 'Cash';
    const BILL_CREDIT = 'Credit';

    public static $billTypes = [
        self::BILL_CASH => 'Cash & Carry',
        self::BILL_CREDIT => 'Credit',
    ];

    public function saleDetails() {
        return $this->hasMany(SaleDetails::class, 'sale_id', 'id');
    }

    public function salePayments() {
        return $this->hasMany(SalePayment::class, 'sale_id', 'id');
    }

    public function customer() {
        return $this->belongsTo(\Modules\People\Entities\Customer::class, 'customer_id', 'id');
    }

    /**
     * Email logs related to this sale (polymorphic)
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
            if (empty($model->reference)) {
                $number = self::getNextSaleNumber($model->date ?? null);
                $model->reference = self::generateSaleReference($number, $model->date ?? null);
            }
        });
    }

    /**
     * Get the next sale number within the bill date's financial year.
     * Uses the highest existing serial in that FY instead of row-count,
     * so deleted/missing numbers do not cause regressions.
     */
    public static function getNextSaleNumber($billDate = null, ?int $excludeSaleId = null) {
        $date = self::resolveReferenceDate($billDate);
        $year = (int) $date->year;
        $month = (int) $date->month;

        if ($month >= 4) {
            $startDate = Carbon::createFromDate($year, 4, 1)->toDateString();
            $endDate = Carbon::createFromDate($year + 1, 3, 31)->toDateString();
        } else {
            $startDate = Carbon::createFromDate($year - 1, 4, 1)->toDateString();
            $endDate = Carbon::createFromDate($year, 3, 31)->toDateString();
        }

        $query = Sale::whereBetween('date', [$startDate, $endDate]);
        if ($excludeSaleId) {
            $query->where('id', '!=', $excludeSaleId);
        }

        $financialYear = self::financialYearLabel($billDate);
        $serialExpr = "CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(reference, '/', 2), '/', -1) AS UNSIGNED)";
        $maxNumber = (int) $query
            ->whereRaw('reference REGEXP ?', ['^SSA/[0-9]+/' . preg_quote($financialYear, '/') . '$'])
            ->max(DB::raw($serialExpr));

        return $maxNumber + 1;
    }

    /**
     * Retry creation on duplicate reference collisions.
     */
    public static function createWithRetry(array $attributes, int $maxAttempts = 3): self
    {
        $attempt = 1;
        $payload = $attributes;

        while (true) {
            try {
                return self::create($payload);
            } catch (QueryException $e) {
                if (!self::isDuplicateReferenceException($e) || $attempt >= $maxAttempts) {
                    throw $e;
                }

                unset($payload['reference']);
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

        return str_contains($message, 'sales_reference_unique')
            || str_contains($message, 'sales.reference')
            || str_contains($message, "for key 'reference'");
    }

    public static function generateSaleReference($number, $billDate = null) {
        $financialYear = self::financialYearLabel($billDate);

        // Format: SSA/XXXXX/YY-ZZ
        $formattedNumber = str_pad($number, 5, '0', STR_PAD_LEFT);
        return 'SSA/' . $formattedNumber . '/' . $financialYear;
    }

    public static function financialYearLabel($billDate = null): string
    {
        $date = self::resolveReferenceDate($billDate);
        $year = (int) $date->year;
        $month = (int) $date->month;

        if ($month >= 4) {
            return $year . '-' . ($year + 1);
        }

        return ($year - 1) . '-' . $year;
    }

    public static function extractFinancialYearFromReference(?string $reference): ?string
    {
        if (!$reference) {
            return null;
        }

        if (preg_match('/\/(\d{4}-\d{4})$/', $reference, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected static function resolveReferenceDate($billDate): Carbon
    {
        if (!$billDate) {
            return now();
        }

        try {
            return Carbon::parse($billDate);
        } catch (\Throwable $e) {
            return now();
        }
    }

    public function scopeCompleted($query) {
        return $query->where('status', 'Completed');
    }

    public function scopeDraft($query) {
        return $query->where('status', 'Draft');
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

    public function getOverallQuantityAttribute($value) {
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

    public function getBalanceAttribute($value) {
        return $value / 100;
    }

    /**
     * Convert an amount in major units (rupees) to minor units (paise)
     * stored in the database. Accepts null/empty and returns null in that case.
     */
    protected function toMinor($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(',', '', $value);
        }

        // Avoid double conversion if an integer already looks like minor units
        // (e.g. already an integer and large). We conservatively assume inputs
        // from forms are floats/strings in rupees and convert them.
        return (int) round((float) $value * 100);
    }

    public function setBalanceAttribute($value)
    {
        $this->attributes['balance'] = $this->toMinor($value);
    }

    public function setShippingAmountAttribute($value)
    {
        $this->attributes['shipping_amount'] = $this->toMinor($value);
    }

    public function setPaidAmountAttribute($value)
    {
        $this->attributes['paid_amount'] = $this->toMinor($value);
    }

    public function setTotalAmountAttribute($value)
    {
        $this->attributes['total_amount'] = $this->toMinor($value);
    }

    public function setDueAmountAttribute($value)
    {
        $this->attributes['due_amount'] = $this->toMinor($value);
    }

    public function setTaxAmountAttribute($value)
    {
        $this->attributes['tax_amount'] = $this->toMinor($value);
    }

    public function setDiscountAmountAttribute($value)
    {
        $this->attributes['discount_amount'] = $this->toMinor($value);
    }

    /* Overall fields */
    public function setOverallQuantityAttribute($value)
    {
        $this->attributes['overall_quantity'] = $this->toMinor($value);
    }

    public function setOverallGrossAmountAttribute($value)
    {
        $this->attributes['overall_gross_amount'] = $this->toMinor($value);
    }

    public function setOverallTaxableAmountAttribute($value)
    {
        $this->attributes['overall_taxable_amount'] = $this->toMinor($value);
    }

    public function setOverallTaxAmountAttribute($value)
    {
        $this->attributes['overall_tax_amount'] = $this->toMinor($value);
    }

    public function setOverallAmountAttribute($value)
    {
        $this->attributes['overall_amount'] = $this->toMinor($value);
    }

    /**
     * Get the total amount to use for status calculation (Sales use overall_amount)
     */
    protected function getTotalAmountForStatus()
    {
        return $this->overall_amount ?? $this->total_amount ?? 0;
    }
}
