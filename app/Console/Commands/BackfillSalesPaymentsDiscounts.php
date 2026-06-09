<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Sale\Entities\Sale;

/**
 * Backfill command to split existing Sale.paid_amount (which historically
 * may have included discounts) into `paid_amount` (actual payments) and
 * `discount_amount` (discounts), using sales_receipt_lines and sale_payments data.
 */
class BackfillSalesPaymentsDiscounts extends Command
{
    protected $signature = 'backfill:sales-payments-discounts {--chunk=500} {--dry-run : Show affected rows without saving} {--ids= : Comma separated sale ids to process}';
    protected $description = 'Backfill sale paid/discount fields from receipts/payments';

    public function handle()
    {
        $chunk = (int) $this->option('chunk');
        $dry = (bool) $this->option('dry-run');
        $idsOpt = $this->option('ids');
        $this->info("Starting backfill of sales (chunk={$chunk})" . ($dry ? ' [dry-run]' : ''));

        $ids = null;
        if ($idsOpt) {
            $ids = array_filter(array_map('intval', explode(',', $idsOpt)));
            $this->info('Restricting to IDs: ' . implode(',', $ids));
        }

        $updated = 0;
        $wouldUpdate = 0;
        $skipped = 0;
        $failed = 0;

        $query = Sale::query();
        if ($ids) {
            $query->whereIn('id', $ids);
        }

        $query->chunkById($chunk, function($sales) use (&$updated, &$wouldUpdate, &$skipped, &$failed, $dry) {
            foreach ($sales as $sale) {
                $id = $sale->id;

                // Sum payments from sale_payments table (stored in minor units)
                $paymentsMinor = DB::table('sale_payments')
                    ->where('sale_id', $id)
                    ->sum('amount');

                // Also sum payment amounts recorded on receipt lines. Prefer
                // receipt totals when available (they reflect what was entered
                // on the receipt), otherwise fall back to sale->paid_amount
                // or to sale_payments table.
                $receiptsPaymentsMinor = DB::table('sales_receipt_lines')
                    ->where('sale_id', $id)
                    ->sum('payment_amount');

                // Sum discounts recorded on receipt lines (stored in minor units)
                $discountsMinor = DB::table('sales_receipt_lines')
                    ->where('sale_id', $id)
                    ->sum('discount_amount');

                // Use the sale's authoritative overall total for calculations.
                // Prefer `overall_net_rate`, then `overall_amount`, then `total_amount`.
                $authoritativeTotal = $sale->overall_net_rate ?? $sale->overall_amount ?? $sale->total_amount ?? 0;

                // If `total_amount` field differs from authoritative total, update it
                // (do not change receipt bill_amounts). Report in dry-run.
                if (abs(floatval($sale->total_amount ?? 0) - floatval($authoritativeTotal)) > 0.005) {
                    if ($dry) {
                        $this->line(sprintf("Would set sale %d total_amount %0.2f -> %0.2f", $id, floatval($sale->total_amount ?? 0), floatval($authoritativeTotal)));
                        $wouldUpdate++;
                    } else {
                        try {
                            $sale->total_amount = floatval($authoritativeTotal);
                            $sale->save();
                            $this->info(sprintf('Updated sale %d total_amount=%0.2f', $id, floatval($authoritativeTotal)));
                        } catch (\Exception $e) {
                            $this->error("Failed to update sale total for {$id}: {$e->getMessage()}");
                        }
                    }

                    // Refresh total used below
                    $total = floatval($authoritativeTotal);
                }

                // Decide which payment source to use: receipts first, then
                // sale model value, then sale_payments table.
                if (($receiptsPaymentsMinor ?? 0) > 0) {
                    $usePaymentsMinor = $receiptsPaymentsMinor;
                } elseif (floatval($sale->paid_amount ?? 0) > 0) {
                    $usePaymentsMinor = intval(round(floatval($sale->paid_amount) * 100));
                } else {
                    $usePaymentsMinor = ($paymentsMinor ?? 0);
                }

                // Convert minor units (paise -> rupees)
                $payments = floatval($usePaymentsMinor / 100);
                $discounts = floatval(($discountsMinor ?? 0) / 100);

                // If receipt discounts are zero but the Sale has a discount,
                // backfill the first receipt line so receipts reflect the sale.
                if ((($discountsMinor ?? 0) == 0) && floatval($sale->discount_amount ?? 0) > 0) {
                    $targetMinor = intval(round(floatval($sale->discount_amount) * 100));
                    $firstLine = DB::table('sales_receipt_lines')
                        ->where('sale_id', $id)
                        ->orderBy('id')
                        ->first();

                    if ($firstLine) {
                        if ($dry) {
                            $this->line(sprintf("Would set receipt line %d discount to %d (minor) for sale %d", $firstLine->id, $targetMinor, $id));
                            $wouldUpdate++;
                        } else {
                            try {
                                DB::table('sales_receipt_lines')
                                    ->where('id', $firstLine->id)
                                    ->update(['discount_amount' => $targetMinor]);
                                $this->info(sprintf('Backfilled receipt line %d discount=%0.2f for sale %d', $firstLine->id, floatval($targetMinor / 100), $id));
                            } catch (\Exception $e) {
                                $this->error("Failed to backfill receipt line {$firstLine->id} for sale {$id}: {$e->getMessage()}");
                            }
                        }

                        // Update local vars so subsequent sale update uses backfilled discount
                        $discountsMinor = $targetMinor;
                        $discounts = floatval($discountsMinor / 100);
                    }
                }

                // (No automatic receipt-line backfill in this version.)

                $currentPaid = floatval($sale->paid_amount ?? 0);
                $currentDiscount = floatval($sale->discount_amount ?? 0);

                // If already matches, skip
                if (abs($currentPaid - $payments) < 0.005 && abs($currentDiscount - $discounts) < 0.005) {
                    $skipped++;
                    continue;
                }

                // Compute new due: use sale total_amount (model accessor returns rupees)
                // If we already set $total above (after updating to authoritative
                // value), don't overwrite it.
                if (!isset($total)) {
                    $total = floatval($sale->total_amount ?? 0);
                }
                $newPaid = $payments;
                $newDiscount = $discounts;
                $newDue = $total - ($newPaid + $newDiscount);

                // Determine status
                if ($newDue == $total) {
                    $status = 'Unpaid';
                } elseif ($newDue > 0) {
                    $status = 'Partial';
                } else {
                    $status = 'Paid';
                }

                if ($dry) {
                    $this->line(sprintf("Would update sale %d: paid %0.2f -> %0.2f, discount %0.2f -> %0.2f, due -> %0.2f", $id, $currentPaid, $newPaid, $currentDiscount, $newDiscount, $newDue));
                    $wouldUpdate++;
                    continue;
                }

                try {
                    $sale->paid_amount = $newPaid;
                    $sale->discount_amount = $newDiscount;
                    $sale->due_amount = $newDue;
                    $sale->payment_status = $status;
                    $sale->save();
                    $this->info(sprintf('Updated sale %d: paid=%0.2f, discount=%0.2f, due=%0.2f', $id, $newPaid, $newDiscount, $newDue));
                    $updated++;
                } catch (\Exception $e) {
                    $this->error("Failed to update sale {$id}: {$e->getMessage()}");
                    $failed++;
                }
            }
        });

        $this->info('Backfill complete');
        $this->info("Summary: skipped={$skipped}, updated={$updated}, would_update={$wouldUpdate}, failed={$failed}");
        return 0;
    }
}
