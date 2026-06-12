<?php

namespace App\Services;

use Modules\Sale\Entities\SaleDetails;
use Modules\SalesReceipt\Entities\SalesReceiptLine;
use Illuminate\Database\Eloquent\Builder;
use Modules\Sale\Entities\Sale;
use Modules\Purchase\Entities\Purchase;
use Carbon\Carbon;
use Modules\Product\Entities\Product;

class ReportQueryService
{
    public function buildGstrQuery(array $filters): Builder
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate   = $filters['end_date']   ?? null;

        $query = SaleDetails::with(['sale:id,date,overall_igst,overall_cgst,overall_sgst', 'product:id,product_unit,hsn'])
            ->whereHas('sale', function ($q) use ($startDate, $endDate) {
                // Exclude Draft bills
                $q->where(function ($s) {
                    $s->whereNull('status')->orWhere('status', '!=', 'Draft');
                });
                // Date range filters merged into the same subquery to avoid extra JOINs
                if ($startDate) {
                    $q->whereDate('date', '>=', $startDate);
                }
                if ($endDate) {
                    $q->whereDate('date', '<=', $endDate);
                }
            });

        if (!empty($filters['hsn'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('hsn', 'like', '%' . $filters['hsn'] . '%')
                  ->orWhereHas('product', function ($p) use ($filters) {
                      $p->where('hsn', 'like', '%' . $filters['hsn'] . '%');
                  });
            });
        }

        // Optionally hide rows where neither the sale_details.hsn nor the product.hsn is present
        if (!empty($filters['hide_without_hsn'])) {
            $query->where(function ($q) {
                $q->where(function ($qq) {
                    $qq->whereNotNull('hsn')->where('hsn', '!=', '');
                })->orWhereHas('product', function ($p) {
                    $p->whereNotNull('hsn')->where('hsn', '!=', '');
                });
            });
        }

        if (!empty($filters['product'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('product_name', 'like', '%' . $filters['product'] . '%')
                  ->orWhereHas('product', function ($p) use ($filters) {
                      $p->where('product_name', 'like', '%' . $filters['product'] . '%');
                  });
            });
        }

        if (isset($filters['rate']) && $filters['rate'] !== '') {
            // UI rate is normalized to percent (e.g. 5 or 18). DB may store either percent (5)
            // or fractional (0.05). Match both possibilities numerically.
            $selected = (float) $filters['rate'];
            $query->where(function ($q) use ($selected) {
                $q->whereRaw('CAST(tax_percentage AS DECIMAL(10,6)) = ?', [$selected])
                  ->orWhereRaw('CAST(tax_percentage AS DECIMAL(10,6)) = ?', [$selected / 100]);
            });
        }

        return $query->orderByDesc('id');
    }

    /**
     * Profit / Loss per sales bill.
     *
     * Profit = bill total WITHOUT VAT − sum of purchase rate (products.product_cost)
     * × quantity for every product on that bill. All amounts are stored in
     * minor units (paise); the SELECT below keeps them in paise — divide by 100
     * for display.
     *
     * Selected computed columns (paise): amount_incl_vat, amount_excl_vat,
     * purchase_total, profit_amount.
     */
    public function buildProfitLossQuery(array $filters): Builder
    {
        // Per-bill purchase cost: sum of (quantity × product purchase rate).
        // LEFT JOIN products so details whose product was deleted count as 0
        // instead of silently dropping the whole row.
        $purchaseTotals = \Illuminate\Support\Facades\DB::table('sales_details as sd')
            ->leftJoin('products as p', 'p.id', '=', 'sd.product_id')
            ->select('sd.sale_id', \Illuminate\Support\Facades\DB::raw('SUM(sd.quantity * COALESCE(p.product_cost, 0)) as purchase_total'))
            ->groupBy('sd.sale_id');

        $inclVat = 'COALESCE(sales.overall_amount, sales.total_amount, 0)';
        $exclVat = $inclVat . ' - COALESCE(sales.overall_tax_amount, sales.tax_amount, 0)';
        $profit  = '(' . $exclVat . ') - COALESCE(pt.purchase_total, 0)';

        $query = Sale::query()
            ->where('sales.status', '!=', 'Draft')
            ->leftJoinSub($purchaseTotals, 'pt', 'pt.sale_id', '=', 'sales.id')
            ->select(
                'sales.*',
                \Illuminate\Support\Facades\DB::raw($inclVat . ' as amount_incl_vat'),
                \Illuminate\Support\Facades\DB::raw($exclVat . ' as amount_excl_vat'),
                \Illuminate\Support\Facades\DB::raw('COALESCE(pt.purchase_total, 0) as purchase_total'),
                \Illuminate\Support\Facades\DB::raw($profit . ' as profit_amount')
            )
            ->with('customer:id,customer_name');

        if (!empty($filters['customer_id'])) {
            $query->where('sales.customer_id', $filters['customer_id']);
        }

        if (!empty($filters['reference'])) {
            $query->where('sales.reference', 'like', '%' . $filters['reference'] . '%');
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('sales.date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('sales.date', '<=', $filters['end_date']);
        }

        if (!empty($filters['pl_status'])) {
            if ($filters['pl_status'] === 'profit') {
                $query->whereRaw($profit . ' >= 0');
            } elseif ($filters['pl_status'] === 'loss') {
                $query->whereRaw($profit . ' < 0');
            }
        }

        return $query->orderByDesc('sales.date')->orderByDesc('sales.id');
    }

    public function buildReorderQuery(array $filters): Builder
    {
        // Statuses that mean the purchase is confirmed/in-progress (not yet received).
        // Products in such purchases are already being ordered — exclude from reorder report.
        $activePurchaseStatuses = ['Ordered', 'Pending', 'Partial'];

        $query = Product::with(['category', 'supplier'])
            ->where('status', 'active')
            ->whereColumn('product_quantity', '<', 'product_stock_alert');

        // By default we exclude products that already have active purchase entries to
        // avoid listing items that are already being ordered. Callers may override
        // this by passing `include_active_purchases=true` in the $filters array.
        if (empty($filters['include_active_purchases'])) {
            $query->whereDoesntHave('purchaseDetails', function ($q) use ($activePurchaseStatuses) {
                $q->whereHas('purchase', function ($p) use ($activePurchaseStatuses) {
                    $p->whereIn('status', $activePurchaseStatuses);
                });
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['compatibility'])) {
            $query->where('product_note', 'like', '%' . $filters['compatibility'] . '%');
        }

        if (!empty($filters['generated_date_from']) && !empty($filters['generated_date_to'])) {
            // Use date-only comparisons to include the entire end date (inclusive)
            $query->whereDate('created_at', '>=', $filters['generated_date_from'])
                  ->whereDate('created_at', '<=', $filters['generated_date_to']);
        } elseif (!empty($filters['generated_date_from'])) {
            $query->whereDate('created_at', '>=', $filters['generated_date_from']);
        } elseif (!empty($filters['generated_date_to'])) {
            $query->whereDate('created_at', '<=', $filters['generated_date_to']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', '%' . $search . '%')
                  ->orWhere('product_code', 'like', '%' . $search . '%');
            });
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function buildCustomersPaymentQuery(array $filters): Builder
    {
        $query = SalesReceiptLine::with([
            'sale:id,reference,date,overall_amount,total_amount',
            'receipt:id,date,payment_mode,customer_id',
            'receipt.customer:id,customer_name'
        ])->where('payment_amount', '>', 0);

        if (!empty($filters['customer_id'])) {
            $query->whereHas('receipt', function ($q) use ($filters) {
                $q->where('customer_id', $filters['customer_id']);
            });
        }

        if (!empty($filters['reference'])) {
            // Search both Sale.reference and Receipt.reference so a single input
            // matches either a sales bill ref or a receipt ref.
            $ref = $filters['reference'];
            $query->where(function($q) use ($ref) {
                $q->whereHas('sale', function ($s) use ($ref) {
                    $s->where('reference', 'like', '%' . $ref . '%');
                })->orWhereHas('receipt', function ($r) use ($ref) {
                    $r->where('reference', 'like', '%' . $ref . '%');
                });
            });
        }

        if (!empty($filters['payment_mode'])) {
            $query->whereHas('receipt', function ($q) use ($filters) {
                $q->where('payment_mode', $filters['payment_mode']);
            });
        }

        if (!empty($filters['start_date'])) {
            $query->whereHas('receipt', function ($q) use ($filters) {
                $q->whereDate('date', '>=', $filters['start_date']);
            });
        }

        if (!empty($filters['end_date'])) {
            $query->whereHas('receipt', function ($q) use ($filters) {
                $q->whereDate('date', '<=', $filters['end_date']);
            });
        }

        return $query->orderByDesc('id');
    }

    /**
     * Returns a merged, sorted Collection that includes both:
     *  – SalesReceiptLine rows (payments against specific sales)
     *  – Lineless SalesReceipt rows (open-balance settlements with no sale lines)
     *
     * Each item in the collection exposes the same interface expected by the
     * customers-payment views: receipt, sale, bill_ref, bill_amount,
     * payment_amount, is_settled.
     */
    public function getCustomersPaymentCollection(array $filters): \Illuminate\Support\Collection
    {
        // Part 1 – lines tied to specific sales (existing behaviour)
        $lines = $this->buildCustomersPaymentQuery($filters)->get();

        // Part 2 – lineless receipts (open-balance settlements)
        // Include lineless receipts when no sale-reference filter is active,
        // or when searching receipt references explicitly via include_receipt_reference.
        $lineless = collect();
        // Include lineless receipts when no reference filter is provided, or
        // when reference filter is present (we may be searching receipt.reference).
        $shouldIncludeLineless = true;
        if ($shouldIncludeLineless) {
            $q = \Modules\SalesReceipt\Entities\SalesReceipt::query()
                ->doesntHave('lines')
                ->with('customer:id,customer_name')
                ->where('total_amount', '>', 0);

            if (!empty($filters['customer_id'])) {
                $q->where('customer_id', $filters['customer_id']);
            }
            if (!empty($filters['payment_mode'])) {
                $q->where('payment_mode', $filters['payment_mode']);
            }
            if (!empty($filters['start_date'])) {
                $q->whereDate('date', '>=', $filters['start_date']);
            }
            if (!empty($filters['end_date'])) {
                $q->whereDate('date', '<=', $filters['end_date']);
            }

            if (!empty($filters['reference'])) {
                $q->where('reference', 'like', '%' . $filters['reference'] . '%');
            }

            $lineless = $q->get()->map(function ($receipt) {
                $obj               = new \stdClass();
                $obj->receipt      = $receipt;
                $obj->sale         = null;
                $obj->bill_ref     = 'Open Balance';
                $obj->bill_date    = null;
                $obj->bill_amount  = 0.0;
                $obj->payment_amount = $receipt->total_amount; // accessor already returns rupees
                $obj->is_settled   = true;
                return $obj;
            });
        }

        // Merge and sort reliably: receipt date desc (by timestamp), then id desc for stable ordering.
        // Compute a numeric sort key (timestamp * 100000 + id) so sorting is stable and immune to string format issues.
            return $lines->concat($lineless)
                ->sortByDesc(function ($item) {
                    // Sort priority: receipt date (desc) -> receipt id (desc) -> line id (desc)
                    $isLine = $item instanceof \Modules\SalesReceipt\Entities\SalesReceiptLine;
                    $dateRaw = $item->receipt->date ?? null;

                    // normalize to UNIX timestamp (fallback to 0)
                    $ts = 0;
                    if (!empty($dateRaw)) {
                        try {
                            $ts = is_numeric($dateRaw) ? intval($dateRaw) : strtotime((string)$dateRaw);
                            if ($ts === false) $ts = 0;
                        } catch (\Throwable $e) {
                            $ts = 0;
                        }
                    }

                    $receiptId = intval($item->receipt->id ?? 0);
                    $lineId = $isLine ? intval($item->id) : 0;

                    // Build a numeric sort key: timestamp * 1e9 + receiptId * 1e5 + lineId
                    // This preserves numeric ordering and keeps groups together.
                    return ($ts * 1000000000) + ($receiptId * 100000) + $lineId;
            })
            ->values();
    }
}
