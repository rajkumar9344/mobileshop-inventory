<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillAllProductCodes extends Command
{
    protected $signature = 'backfill:product-codes {--dry-run : Do not persist changes}';

    protected $description = 'Backfill product_code_id across all transaction detail tables (supports --dry-run)';

    /** @var array<string,string> mapping table => display name */
    protected $tables = [
        'sales_details' => 'Sales Details',
        'purchase_details' => 'Purchase Details',
        'quotation_details' => 'Quotation Details',
        'sale_return_details' => 'Sale Return Details',
        'purchase_return_details' => 'Purchase Return Details',
    ];

    public function handle()
    {
        $dry = $this->option('dry-run');

        $this->info('Starting consolidated backfill for product_code_id across detail tables');

        $summary = [];

        foreach ($this->tables as $table => $label) {
            $this->info("Inspecting: {$label} ({$table})");

            // Count candidates to update: rows with product_code set and product_code_id IS NULL
            $countSql = "SELECT COUNT(*) as c FROM `{$table}` t WHERE t.product_code IS NOT NULL AND (t.product_code_id IS NULL OR t.product_code_id = 0)";
            $count = DB::selectOne($countSql);
            $toUpdate = (int) ($count->c ?? 0);

            // Count unmatched (no product_codes row exists for (product_id, product_code))
            $unmatchedSql = "SELECT COUNT(*) as c FROM `{$table}` t LEFT JOIN product_codes pc ON t.product_code = pc.code AND t.product_id = pc.product_id WHERE (t.product_code_id IS NULL OR t.product_code_id = 0) AND (t.product_code IS NOT NULL) AND pc.id IS NULL";
            $unmatched = DB::selectOne($unmatchedSql);
            $unmatchedCount = (int) ($unmatched->c ?? 0);

            $summary[$table] = [
                'label' => $label,
                'candidates' => $toUpdate,
                'unmatched' => $unmatchedCount,
            ];

            $this->info("  Candidates to update: {$toUpdate}");
            $this->info("  Unmatched (need review): {$unmatchedCount}");

            if ($dry) {
                // show small sample of unmatched rows for operator review
                if ($unmatchedCount > 0) {
                    $this->info('  Sample unmatched rows (product_id, product_code, count):');
                    $rows = DB::select("SELECT t.product_id, t.product_code, COUNT(*) AS cnt FROM `{$table}` t LEFT JOIN product_codes pc ON t.product_code = pc.code AND t.product_id = pc.product_id WHERE (t.product_code_id IS NULL OR t.product_code_id = 0) AND (t.product_code IS NOT NULL) AND pc.id IS NULL GROUP BY t.product_id, t.product_code ORDER BY cnt DESC LIMIT 50");
                    foreach ($rows as $r) {
                        $this->line(sprintf('    %s | %s | %d', $r->product_id ?? 'NULL', $r->product_code ?? 'NULL', $r->cnt ?? 0));
                    }
                }
                continue;
            }

            // Perform update
            if ($toUpdate > 0) {
                try {
                    DB::statement("UPDATE `{$table}` t JOIN product_codes pc ON t.product_code = pc.code AND t.product_id = pc.product_id SET t.product_code_id = pc.id WHERE (t.product_code_id IS NULL OR t.product_code_id = 0) AND t.product_code IS NOT NULL");
                    $this->info("  Updated {$table} product_code_id from product_codes");
                } catch (\Exception $e) {
                    $this->error("  Update failed for {$table}: " . $e->getMessage());
                    Log::error('BackfillAllProductCodes update failed', ['table' => $table, 'error' => $e->getMessage()]);
                    return 1;
                }
            } else {
                $this->info("  Nothing to update for {$table}");
            }
        }

        if ($dry) {
            $this->info('Dry-run complete. No changes were made.');
            return 0;
        }

        // After updates, report remaining unmatched counts
        $this->info('Backfill executed. Now checking for any remaining unmatched rows:');
        $totalUnmatched = 0;
        foreach ($this->tables as $table => $label) {
            $unmatchedSql = "SELECT COUNT(*) as c FROM `{$table}` t LEFT JOIN product_codes pc ON t.product_code = pc.code AND t.product_id = pc.product_id WHERE (t.product_code_id IS NULL OR t.product_code_id = 0) AND (t.product_code IS NOT NULL) AND pc.id IS NULL";
            $unmatched = DB::selectOne($unmatchedSql);
            $uc = (int) ($unmatched->c ?? 0);
            $this->info("  {$label}: {$uc} unmatched rows");
            $totalUnmatched += $uc;
        }

        $this->info("Total unmatched rows across tables: {$totalUnmatched}");

        $this->info('Backfill finished');
        return 0;
    }
}
