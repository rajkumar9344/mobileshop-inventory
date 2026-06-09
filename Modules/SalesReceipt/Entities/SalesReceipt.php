<?php

namespace Modules\SalesReceipt\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesReceipt extends Model
{
    protected $table = 'sales_receipts';
    protected $guarded = [];

    /**
     * Notes:
     * - Monetary fields are stored as integers in paise (minor units).
     * - `customer_balance_before` and `applied_to_customer` (both paise) are
     *   used for lineless receipts created from Sale Returns:
     *     - `customer_balance_before`: customer's opening balance before applying this receipt
     *     - `applied_to_customer`: how much of the receipt actually reduced a positive balance
     *   The controller subtracts the full `total_amount` from the customer's
     *   `opening_balance` (allowing negative balances) while `applied_to_customer`
     *   is the authoritative amount used to compute the receipt balance shown
     *   in the UI.
     */

    public function lines()
    {
        return $this->hasMany(SalesReceiptLine::class, 'sales_receipt_id');
    }

    public function customer()
    {
        return $this->belongsTo(\Modules\People\Entities\Customer::class, 'customer_id');
    }

    /**
     * Scope: apply settled-Yes clause (receipts with all lines settled OR lineless applied >= total)
     */
    public function scopeSettledYes($query)
    {
        // Consider a receipt settled when the sum of its line payment+discount equals or
        // exceeds the receipt total (both stored in paise). For lineless receipts use
        // the applied_to_customer comparison (existing behavior).
        $query->where(function($q){
                        $q->whereHas('lines')
                            ->whereRaw('(SELECT COALESCE(SUM(payment_amount),0) FROM sales_receipt_lines WHERE sales_receipt_lines.sales_receipt_id = sales_receipts.id) >= COALESCE(sales_receipts.total_amount,0)');
        })->orWhere(function($q){
            $q->doesntHave('lines')->whereRaw('COALESCE(applied_to_customer,0) >= COALESCE(total_amount,0)');
        });
    }

    /**
     * Scope: apply settled-No clause (receipts with any unsettled line OR lineless applied < total)
     */
    public function scopeSettledNo($query)
    {
        // Consider a receipt not settled when the sum of its line payment+discount is
        // less than the receipt total. For lineless receipts use applied_to_customer.
        $query->where(function($q){
                        $q->whereHas('lines')
                            ->whereRaw('(SELECT COALESCE(SUM(payment_amount),0) FROM sales_receipt_lines WHERE sales_receipt_lines.sales_receipt_id = sales_receipts.id) < COALESCE(sales_receipts.total_amount,0)');
        })->orWhere(function($q){
            $q->doesntHave('lines')->whereRaw('COALESCE(applied_to_customer,0) < COALESCE(total_amount,0)');
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
              ->orWhereHas('customer', function($c) use ($t) {
                  $c->where('customer_name', 'like', "%{$t}%")
                    ->orWhere('area', 'like', "%{$t}%");
              });

            if (in_array($lower, $yes, true)) {
                $q->orWhere(function($q2){ $q2->settledYes(); });
            } elseif (in_array($lower, $no, true)) {
                $q->orWhere(function($q2){ $q2->settledNo(); });
            }

            // numeric amount matching: user types rupees, DB stores paise
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
}
