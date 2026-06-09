<?php

namespace Modules\PurchasesReceipt\Entities;

use Illuminate\Database\Eloquent\Model;

class PurchasesReceipt extends Model
{
    protected $table = 'purchases_receipts';
    protected $guarded = [];

    public function lines()
    {
        return $this->hasMany(PurchasesReceiptLine::class, 'purchases_receipt_id');
    }

    public function supplier()
    {
        return $this->belongsTo(\Modules\People\Entities\Supplier::class, 'supplier_id');
    }

    /**
     * Scope: apply settled-Yes clause (receipts with all lines settled OR lineless applied >= total)
     */
    public function scopeSettledYes($query)
    {
        // Consider a purchase receipt settled when the sum of its line payment+discount
        // equals or exceeds the receipt total (both stored in paise). For lineless
        // receipts use applied_to_supplier comparison.
                $query->where(function($q){
                        $q->whereHas('lines')
                            ->whereRaw('(SELECT COALESCE(SUM(payment_amount),0) FROM purchases_receipt_lines WHERE purchases_receipt_lines.purchases_receipt_id = purchases_receipts.id) >= COALESCE(purchases_receipts.total_amount,0)');
        })->orWhere(function($q){
            $q->doesntHave('lines')->whereRaw('COALESCE(applied_to_supplier,0) >= COALESCE(total_amount,0)');
        });
    }

    /**
     * Scope: apply settled-No clause (receipts with any unsettled line OR lineless applied < total)
     */
    public function scopeSettledNo($query)
    {
        // Consider a purchase receipt not settled when the sum of its line payment+discount
        // is less than the receipt total. For lineless receipts use applied_to_supplier.
                $query->where(function($q){
                        $q->whereHas('lines')
                            ->whereRaw('(SELECT COALESCE(SUM(payment_amount),0) FROM purchases_receipt_lines WHERE purchases_receipt_lines.purchases_receipt_id = purchases_receipts.id) < COALESCE(purchases_receipts.total_amount,0)');
        })->orWhere(function($q){
            $q->doesntHave('lines')->whereRaw('COALESCE(applied_to_supplier,0) < COALESCE(total_amount,0)');
        });
    }

    /**
     * Scope: apply global search behaviour used by DataTable and totals.
     * Accepts a string $term and applies text, settled and numeric amount matching.
     */
    public function scopeApplyGlobalSearch($query, $term)
    {
        $t = trim((string)$term);
        if ($t === '') return $query;

        $lower = strtolower($t);
        $yes = ['yes','y','1','true'];
        $no = ['no','n','0','false'];

        $query->where(function($q) use ($t, $lower, $yes, $no) {
            $q->where('reference', 'like', "%{$t}%")
              ->orWhere('date', 'like', "%{$t}%")
              ->orWhere('payment_mode', 'like', "%{$t}%")
              ->orWhereHas('supplier', function($c) use ($t) {
                  $c->where('supplier_name', 'like', "%{$t}%")
                    ->orWhere('area', 'like', "%{$t}%");
              });

            if (in_array($lower, $yes, true)) {
                $q->orWhere(function($q2){ $q2->settledYes(); });
            } elseif (in_array($lower, $no, true)) {
                $q->orWhere(function($q2){ $q2->settledNo(); });
            }

            $clean = preg_replace('/[^0-9.\-]/', '', $t);
            if ($clean !== '' && is_numeric($clean)) {
                $amt = floatval($clean);
                $paise = intval(round($amt * 100));
                $q->orWhere('total_amount', $paise)
                  ->orWhereRaw('CAST(total_amount AS CHAR) LIKE ?', ["%{$clean}%"]);
            }
        });

        return $query;
    }

    // Accessors to return rupees
    public function getTotalAmountAttribute($value)
    {
        return $value / 100;
    }

    public function getTotalDiscountAttribute($value)
    {
        return $value / 100;
    }

    // Mutators if someone sets model attribute directly
    public function setTotalAmountAttribute($value)
    {
        $this->attributes['total_amount'] = (int) round($value * 100);
    }

    public function setTotalDiscountAttribute($value)
    {
        $this->attributes['total_discount'] = (int) round($value * 100);
    }

    /**
     * Calculate if this receipt is settled based on amount allocation
     * Returns true if total receipt amount equals total allocated amount
     */
    public function getIsSettledAttribute()
    {
        $receiptAmount = $this->total_amount; // accessor returns rupees
        $totalAllocated = $this->lines->sum(function($line) {
            return $line->payment_amount;
        });
        
        return abs($receiptAmount - $totalAllocated) < 0.01; // allow for minor rounding differences
    }

    /**
     * Calculate excess amount (difference between receipt amount and allocated amount)
     */
    public function getExcessAmountAttribute()
    {
        $receiptAmount = $this->total_amount;
        $totalAllocated = $this->lines->sum(function($line) {
            return $line->payment_amount;
        });
        
        return $receiptAmount - $totalAllocated;
    }

    /**
     * Check if there's any excess amount
     */
    public function getHasExcessAmountAttribute()
    {
        return abs($this->excess_amount) > 0.01;
    }
}