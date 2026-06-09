<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Purchase\Entities\Purchase;

/**
 * Backfill command to split existing Purchase.paid_amount (which historically
 * included discounts) into proper `paid_amount` (actual payments) and
 * `discount_amount` (discounts), using PurchasesReceiptLine and PurchasePayment data.
 */
class BackfillPurchasePaymentsDiscounts extends Command
{
    protected $signature = 'backfill:purchase-payments-discounts {--chunk=500} {--dry-run : Show affected rows without saving}';
    protected $description = 'Backfill purchase paid/discount fields from receipts/payments';

    public function handle()
    {
        $chunk = (int) $this->option('chunk');
        $dry = (bool) $this->option('dry-run');
        $this->info("Starting backfill of purchases (chunk={$chunk})" . ($dry ? ' [dry-run]' : ''));

        $updated = 0;
        $wouldUpdate = 0;
        $skipped = 0;
        $failed = 0;

        Purchase::query()->chunkById($chunk, function($purchases) use (&$updated, &$wouldUpdate, &$skipped, &$failed, $dry) {
            foreach ($purchases as $purchase) {
                $id = $purchase->id;

                // Sum payments from purchase_payments table (stored in minor units e.g. paise)
                $paymentsMinor = DB::table('purchase_payments')
                    ->where('purchase_id', $id)
                    ->sum('amount');

                // Sum discounts recorded on receipt lines (stored in minor units)
                $discountsMinor = DB::table('purchases_receipt_lines')
                    ->where('purchase_id', $id)
                    ->sum('discount_amount');

                // Convert minor units (paise) to rupees as floats
                $payments = floatval(($paymentsMinor ?? 0) / 100);
                $discounts = floatval(($discountsMinor ?? 0) / 100);

                // If the current purchase already matches these numbers, skip
                $currentPaid = floatval($purchase->paid_amount ?? 0);
                $currentDiscount = floatval($purchase->discount_amount ?? 0);

                // Note: model accessors return rupees already; DB sums are stored as rupees
                if (abs($currentPaid - $payments) < 0.005 && abs($currentDiscount - $discounts) < 0.005) {
                    $skipped++;
                    continue;
                }

                $newPaid = $payments;
                $newDiscount = $discounts;
                $newDue = floatval($purchase->total_amount ?? 0) - ($newPaid + $newDiscount);

                $status = 'Unpaid';
                if ($newDue == floatval($purchase->total_amount ?? 0)) {
                    $status = 'Unpaid';
                } elseif ($newDue > 0) {
                    $status = 'Partial';
                } else {
                    $status = 'Paid';
                }

                // Persist changes using minor-unit mutators (models expect rupees, mutators convert)
                if ($dry) {
                    $this->line(sprintf("Would update purchase %d: paid from %0.2f -> %0.2f, discount from %0.2f -> %0.2f, due -> %0.2f", $id, $currentPaid, $newPaid, $currentDiscount, $newDiscount, $newDue));
                    $wouldUpdate++;
                    continue;
                }

                try {
                    $purchase->paid_amount = $newPaid;
                    $purchase->discount_amount = $newDiscount;
                    $purchase->due_amount = $newDue;
                    $purchase->payment_status = $status;
                    $purchase->save();
                    $this->info(sprintf("Updated purchase %d: paid=%0.2f, discount=%0.2f, due=%0.2f", $id, $newPaid, $newDiscount, $newDue));
                    $updated++;
                } catch (\Exception $e) {
                    $this->error("Failed to update purchase {$id}: {$e->getMessage()}");
                    $failed++;
                }
            }
        });

        $this->info('Backfill complete');
        $this->info("Summary: skipped={$skipped}, updated={$updated}, would_update={$wouldUpdate}, failed={$failed}");
        return 0;
    }
}
