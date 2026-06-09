<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Purchase\Entities\PurchaseDetail;
use Modules\PurchasesReturn\Entities\PurchaseReturnDetail;
use Illuminate\Support\Facades\Log;

class BackfillRateBeforeDiscount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backfill:rate-before-discount {--compute : Try to reconstruct from discount_percent when available} {--from-mrp : Compute from mrp/(1+tax_percent) when available} {--chunk=200 : Chunk size}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill null or zero rate_before_discount on purchase and purchase_return details';

    public function handle()
    {
        $compute = $this->option('compute');
        $fromMrp = $this->option('from-mrp');
        $chunk = (int) $this->option('chunk');

        $this->info('Starting backfill for purchase_details');
        Log::info('BackfillRateBeforeDiscount started', ['compute' => $compute, 'from_mrp' => $fromMrp, 'chunk' => $chunk]);

        $perPurchaseCounts = [];
        PurchaseDetail::whereNull('rate_before_discount')
            ->orWhere('rate_before_discount', 0)
            ->chunkById($chunk, function($rows) use ($compute, $fromMrp, &$perPurchaseCounts) {
                foreach ($rows as $r) {
                    try {
                        if ($fromMrp && isset($r->mrp) && isset($r->tax_percent)) {
                            $pre = $r->mrp / (1 + ($r->tax_percent/100));
                            $r->rate_before_discount = round($pre, 2);
                        } elseif ($compute && isset($r->discount_percent) && $r->discount_percent != 0) {
                            $pre = $r->rate / (1 - ($r->discount_percent/100));
                            $r->rate_before_discount = round($pre, 2);
                        } else {
                            $r->rate_before_discount = $r->rate;
                        }
                        $r->save();
                        Log::info('BackfillRateBeforeDiscount - updated PurchaseDetail', ['id' => $r->id, 'purchase_id' => $r->purchase_id, 'rate_before_discount' => $r->rate_before_discount]);
                        $pid = $r->purchase_id ?? 0;
                        if (!isset($perPurchaseCounts[$pid])) $perPurchaseCounts[$pid] = 0;
                        $perPurchaseCounts[$pid]++;
                    } catch (\Throwable $e) {
                        $this->error("Failed id={$r->id}: {$e->getMessage()}");
                        Log::error('BackfillRateBeforeDiscount - error processing PurchaseDetail', ['id' => $r->id, 'error' => $e->getMessage()]);
                    }
                }
            });

        $this->info('PurchaseDetails backfill complete. Now backfilling purchase_return_details');
        Log::info('BackfillRateBeforeDiscount - purchase_details complete', ['per_purchase_counts' => $perPurchaseCounts]);

        $perReturnCounts = [];
        PurchaseReturnDetail::whereNull('rate_before_discount')
            ->orWhere('rate_before_discount', 0)
            ->chunkById($chunk, function($rows) use ($compute, $fromMrp, &$perReturnCounts) {
                foreach ($rows as $r) {
                    try {
                        if ($fromMrp && isset($r->mrp) && isset($r->tax_percent)) {
                            $pre = $r->mrp / (1 + ($r->tax_percent/100));
                            $r->rate_before_discount = round($pre, 2);
                        } elseif ($compute && isset($r->discount_percent) && $r->discount_percent != 0) {
                            $pre = $r->rate / (1 - ($r->discount_percent/100));
                            $r->rate_before_discount = round($pre, 2);
                        } else {
                            $r->rate_before_discount = $r->rate;
                        }
                        $r->save();
                        Log::info('BackfillRateBeforeDiscount - updated PurchaseReturnDetail', ['id' => $r->id, 'purchase_return_id' => $r->purchase_return_id, 'rate_before_discount' => $r->rate_before_discount]);
                        $prid = $r->purchase_return_id ?? 0;
                        if (!isset($perReturnCounts[$prid])) $perReturnCounts[$prid] = 0;
                        $perReturnCounts[$prid]++;
                    } catch (\Throwable $e) {
                        $this->error("Return id={$r->id} failed: {$e->getMessage()}");
                        Log::error('BackfillRateBeforeDiscount - error processing PurchaseReturnDetail', ['id' => $r->id, 'error' => $e->getMessage()]);
                    }
                }
            });

        $this->info('Backfill finished.');
        Log::info('BackfillRateBeforeDiscount finished', ['per_purchase_counts' => $perPurchaseCounts, 'per_return_counts' => $perReturnCounts]);
        return 0;
    }
}
