<?php

namespace Modules\People\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{

    use HasFactory;

    protected $fillable = [
        'customer_name', 'customer_code', 'customer_email', 'customer_phone', 'address', 'city', 'state',
        'pincode', 'area', 'vat_id', 'opening_balance', 'credit_limit',
        'lock', 'is_active', 'account_id', 'remarks', 'excess_amount'
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'credit_limit'    => 'decimal:2',
        'excess_amount'   => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    protected static function newFactory() {
        return \Modules\People\Database\factories\CustomerFactory::new();
    }

    public function getOpeningBalanceFormattedAttribute() {
        return number_format($this->opening_balance, 2, '.', '');
    }

    /* ───────────────────────────────────────────────────────────────────
     * Three-bucket balance model (single source of truth):
     *   Open Balance  = carried-forward opening amount (stored opening_balance).
     *                   Only changed by manual edit + "Apply to Open Balance"
     *                   receipts — NOT by a bill's unpaid amount.
     *   Bill Balance  = sum of unpaid dues across the customer's non-draft sales.
     *   Total Balance = Open + Bill.
     * due_amount is stored in paise on the sales table, so divide by 100.
     * ─────────────────────────────────────────────────────────────────── */

    public function getOpenBalanceAttribute(): float
    {
        return (float) ($this->opening_balance ?? 0);
    }

    public function getBillBalanceAttribute(): float
    {
        return (float) \Modules\Sale\Entities\Sale::where('customer_id', $this->id)
            ->where('status', '!=', 'Draft')
            ->sum('due_amount') / 100;
    }

    public function getTotalBalanceAttribute(): float
    {
        return round($this->open_balance + $this->bill_balance, 2);
    }

}
