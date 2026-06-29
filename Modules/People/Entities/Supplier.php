<?php

namespace Modules\People\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_name',
        'supplier_code',
        'trn',
        'supplier_email',
        'supplier_phone',
        'area',
        'state',
        'city',
        'address',
        'open_balance',
        'excess_amount',
        'credit_limit',
        'tax_percent',
        'due_days',
        'status',
        'remarks',
    ];

    protected $casts = [
        'open_balance'  => 'decimal:2',
        'excess_amount' => 'decimal:2',
        'credit_limit'  => 'decimal:2',
        'tax_percent'   => 'decimal:2',
        'due_days'      => 'integer',
    ];

    protected static function newFactory() {
        return \Modules\People\Database\factories\SupplierFactory::new();
    }

    /* ───────────────────────────────────────────────────────────────────
     * Three-bucket balance model (mirrors Customer):
     *   Open Balance  = carried-forward opening amount (stored open_balance).
     *   Bill Balance  = sum of unpaid dues across non-draft purchases.
     *   Total Balance = Open + Bill.
     * due_amount is stored in paise on the purchases table, so divide by 100.
     * ─────────────────────────────────────────────────────────────────── */

    public function getOpenBalanceValueAttribute(): float
    {
        return (float) ($this->open_balance ?? 0);
    }

    public function getBillBalanceAttribute(): float
    {
        return (float) \Modules\Purchase\Entities\Purchase::where('supplier_id', $this->id)
            ->where('status', '!=', 'Draft')
            ->sum('due_amount') / 100;
    }

    public function getTotalBalanceAttribute(): float
    {
        return round($this->open_balance_value + $this->bill_balance, 2);
    }
}
