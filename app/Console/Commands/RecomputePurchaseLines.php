<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Purchase\Entities\PurchaseDetail;
use Modules\PurchasesReturn\Entities\PurchaseReturnDetail;
use Illuminate\Support\Facades\Log;

class RecomputePurchaseLines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recompute:purchase-lines {--purchase= : Limit to a specific purchase_id} {--chunk=200 : Chunk size} {--dry-run : Do not persist changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recompute per-line fields (rate, amount, tax, unit_price, sub_total, discounts) for existing purchases and purchase returns';

    public function handle()
    {
        $purchaseId = $this->option('purchase');
        $chunk = (int) $this->option('chunk');
        $dry = (bool) $this->option('dry-run');

        $this->info('Starting recompute of PurchaseDetail rows' . ($purchaseId ? " for purchase_id={$purchaseId}" : ''));
        Log::info('RecomputePurchaseLines started', ['purchase_id' => $purchaseId, 'chunk' => $chunk, 'dry_run' => $dry]);

        $query = PurchaseDetail::query();
        if ($purchaseId) $query->where('purchase_id', $purchaseId);

        $total = $query->count();
        $this->info("Found {$total} purchase detail rows to process");

        $processed = 0;
        $perPurchaseCounts = [];
        $query->chunkById($chunk, function($rows) use (&$processed, $dry, &$perPurchaseCounts) {
            foreach ($rows as $detail) {
                try {
                    $rbd = $detail->rate_before_discount ?? $detail->rate ?? $detail->unit_price ?? 0;
                    $discountPercent = (float) ($detail->discount_percent ?? 0);
                    $cashDiscountPercent = (float) ($detail->cash_discount_percent ?? 0);
                    $cashDiscountAmount = (float) ($detail->cash_discount_amount ?? 0);
                    $taxPercent = (float) ($detail->tax_percent ?? 0);
                    $qty = (float) ($detail->quantity ?? 0);

                    if ($rbd !== null && floatval($rbd) > 0) {
                        $perUnitAfterPercent = floatval($rbd) * (1 - ($discountPercent / 100));
                        $cashPercentAmtPerUnit = $perUnitAfterPercent * ($cashDiscountPercent / 100);
                        $cashTotalPerUnit = $cashPercentAmtPerUnit + $cashDiscountAmount;
                        $computedRate = round($perUnitAfterPercent - $cashTotalPerUnit, 2);
                        $computedAmount = round($computedRate * $qty, 2);
                        $computedTaxAmount = round($computedAmount * ($taxPercent / 100), 2);
                    } else {
                        $computedRate = $detail->rate ?? 0;
                        $computedAmount = ($detail->rate ?? 0) * $qty;
                        $computedTaxAmount = ($detail->rate ?? 0) * (($taxPercent ?? 0) / 100) * $qty;
                    }

                    $productDiscountAmount = ($detail->product_discount_type ?? '') === 'percentage'
                        ? round((float)($rbd ?? 0) * ($discountPercent / 100) * $qty, 2)
                        : ($detail->product_discount_amount ?? 0);

                    if (!$dry) {
                        $detail->rate = $computedRate;
                        $detail->tax_amount = $computedTaxAmount;
                        $detail->amount = $computedAmount;
                        $detail->unit_price = $computedRate;
                        $detail->sub_total = $computedAmount;
                        $detail->product_discount_amount = $productDiscountAmount;
                        $detail->product_tax_amount = $computedTaxAmount;
                        $detail->save();
                        Log::info('RecomputePurchaseLines - updated PurchaseDetail', ['id' => $detail->id, 'purchase_id' => $detail->purchase_id]);
                        // tally per-purchase
                        $pid = $detail->purchase_id ?? 0;
                        if (!isset($perPurchaseCounts[$pid])) $perPurchaseCounts[$pid] = 0;
                        $perPurchaseCounts[$pid]++;
                    } else {
                        Log::info('RecomputePurchaseLines - dry-run computed values', ['id' => $detail->id, 'purchase_id' => $detail->purchase_id, 'rate' => $computedRate, 'amount' => $computedAmount, 'tax' => $computedTaxAmount]);
                    }
                    $processed++;
                } catch (\Exception $e) {
                    $this->error('Failed to process PurchaseDetail id='.$detail->id.': '.$e->getMessage());
                    Log::error('RecomputePurchaseLines - error processing PurchaseDetail', ['id' => $detail->id, 'error' => $e->getMessage()]);
                }
            }
        });

        $this->info("Processed {$processed} purchase detail rows");
        Log::info('RecomputePurchaseLines - PurchaseDetail summary', ['total_processed' => $processed, 'per_purchase_counts' => $perPurchaseCounts]);

        // Purchase Returns
        $this->info('Starting recompute of PurchaseReturnDetail rows' . ($purchaseId ? " for purchase_id={$purchaseId}" : ''));
        $q2 = PurchaseReturnDetail::query();
        if ($purchaseId) $q2->where('purchase_return_id', $purchaseId);
        $total2 = $q2->count();
        $this->info("Found {$total2} purchase return detail rows to process");

        $processed2 = 0;
        $perReturnCounts = [];
        $q2->chunkById($chunk, function($rows) use (&$processed2, $dry, &$perReturnCounts) {
            foreach ($rows as $detail) {
                try {
                    $rbd = $detail->rate_before_discount ?? $detail->rate ?? $detail->unit_price ?? 0;
                    $discountPercent = (float) ($detail->discount_percent ?? 0);
                    $cashDiscountPercent = (float) ($detail->cash_discount_percent ?? 0);
                    $cashDiscountAmount = (float) ($detail->cash_discount_amount ?? 0);
                    $taxPercent = (float) ($detail->tax_percent ?? 0);
                    $qty = (float) ($detail->quantity ?? 0);

                    if ($rbd !== null && floatval($rbd) > 0) {
                        $perUnitAfterPercent = floatval($rbd) * (1 - ($discountPercent / 100));
                        $cashPercentAmtPerUnit = $perUnitAfterPercent * ($cashDiscountPercent / 100);
                        $cashTotalPerUnit = $cashPercentAmtPerUnit + $cashDiscountAmount;
                        $computedRate = round($perUnitAfterPercent - $cashTotalPerUnit, 2);
                        $computedAmount = round($computedRate * $qty, 2);
                        $computedTaxAmount = round($computedAmount * ($taxPercent / 100), 2);
                    } else {
                        $computedRate = $detail->rate ?? 0;
                        $computedAmount = ($detail->rate ?? 0) * $qty;
                        $computedTaxAmount = ($detail->rate ?? 0) * (($taxPercent ?? 0) / 100) * $qty;
                    }

                    $productDiscountAmount = ($detail->product_discount_type ?? '') === 'percentage'
                        ? round((float)($rbd ?? 0) * ($discountPercent / 100) * $qty, 2)
                        : ($detail->product_discount_amount ?? 0);

                    if (!$dry) {
                        $detail->rate = $computedRate;
                        $detail->tax_amount = $computedTaxAmount;
                        $detail->amount = $computedAmount;
                        $detail->unit_price = $computedRate;
                        $detail->sub_total = $computedAmount;
                        $detail->product_discount_amount = $productDiscountAmount;
                        $detail->product_tax_amount = $computedTaxAmount;
                        $detail->save();
                        Log::info('RecomputePurchaseLines - updated PurchaseReturnDetail', ['id' => $detail->id, 'purchase_return_id' => $detail->purchase_return_id]);
                        $prid = $detail->purchase_return_id ?? 0;
                        if (!isset($perReturnCounts[$prid])) $perReturnCounts[$prid] = 0;
                        $perReturnCounts[$prid]++;
                    } else {
                        Log::info('RecomputePurchaseLines - dry-run computed values for return', ['id' => $detail->id, 'purchase_return_id' => $detail->purchase_return_id, 'rate' => $computedRate, 'amount' => $computedAmount, 'tax' => $computedTaxAmount]);
                    }
                    $processed2++;
                } catch (\Exception $e) {
                    $this->error('Failed to process PurchaseReturnDetail id='.$detail->id.': '.$e->getMessage());
                    Log::error('RecomputePurchaseLines - error processing PurchaseReturnDetail', ['id' => $detail->id, 'error' => $e->getMessage()]);
                }
            }
        });

        $this->info("Processed {$processed2} purchase return detail rows");
        Log::info('RecomputePurchaseLines - PurchaseReturnDetail summary', ['total_processed' => $processed2, 'per_return_counts' => $perReturnCounts]);

        $this->info('Recompute finished' . ($dry ? ' (dry-run, no changes saved)' : ''));

        return 0;
    }
}
