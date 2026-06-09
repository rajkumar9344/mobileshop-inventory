<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RenumberBillsByFinancialYear extends Command
{
    protected $signature = 'bills:renumber-fy {--apply : Persist the new bill numbers instead of previewing them}';

    protected $description = 'Preview or renumber sales bill references for the current financial year';

    public function handle()
    {
        $apply = (bool) $this->option('apply');
        $mode = $apply ? 'APPLY' : 'PREVIEW';
        $financialYear = $this->financialYearLabel(now());

        $this->info("Bill renumbering by financial year ({$mode})");
        $this->line('Financial year runs from 1 April to 31 March.');
        $this->line('Current financial year: ' . $financialYear);

        $summary = $this->buildRenumberPlan();

        $this->newLine();
        $this->info('Sales (sales)');
        $this->line('Rows found: ' . count($summary['rows']));
        $this->line('Financial years detected: ' . implode(', ', array_keys($summary['byFy'])));

        foreach ($summary['byFy'] as $fy => $count) {
            $this->line("  {$fy}: {$count} rows");
        }

        $previewRows = array_slice($summary['rows'], 0, 10);
        if (!empty($previewRows)) {
            $this->line('Preview:');
            foreach ($previewRows as $row) {
                $this->line(sprintf(
                    '  #%d | %s | %s -> %s',
                    $row['id'],
                    $row['bill_date'],
                    $row['current_reference'],
                    $row['new_reference']
                ));
            }
        }
        
        $this->newLine();
        $this->info('Related Updates (sales_receipt_lines, sale_payments, sales_receipts)');
        $totalReceiptLines = 0;
        $totalPayments = 0;
        $totalReceiptParticulars = 0;
        
        foreach ($summary['rows'] as $row) {
            $receiptLinesCount = DB::table('sales_receipt_lines')->where('sale_id', $row['id'])->count();
            $paymentsCount = DB::table('sale_payments')->where('sale_id', $row['id'])->where('reference', 'INV/' . $row['current_reference'])->count();
            
            $receiptIds = DB::table('sales_receipt_lines')->where('sale_id', $row['id'])->pluck('sales_receipt_id');
            $receiptParticularsCount = $receiptIds->isNotEmpty() 
                ? DB::table('sales_receipts')->whereIn('id', $receiptIds)->where('particular', 'Sale ' . $row['current_reference'])->count() 
                : 0;

            $totalReceiptLines += $receiptLinesCount;
            $totalPayments += $paymentsCount;
            $totalReceiptParticulars += $receiptParticularsCount;
        }
        
        $this->line("Receipt lines to update: {$totalReceiptLines}");
        $this->line("Sale payments to update: {$totalPayments}");
        $this->line("Receipt particulars to update: {$totalReceiptParticulars}");

        if ($apply) {
            $updated = 0;
            
            DB::transaction(function () use ($summary, &$updated) {
                foreach ($summary['rows'] as $row) {
                    // Update main sales table
                    DB::table('sales')
                        ->where('id', $row['id'])
                        ->update(['reference' => $row['new_reference']]);
                    
                    // Update sales receipt lines
                    DB::table('sales_receipt_lines')
                        ->where('sale_id', $row['id'])
                        ->update(['bill_ref' => $row['new_reference']]);

                    // Update sale payments
                    DB::table('sale_payments')
                        ->where('sale_id', $row['id'])
                        ->where('reference', 'INV/' . $row['current_reference'])
                        ->update(['reference' => 'INV/' . $row['new_reference']]);

                    // Update sales receipts particular (if it matches the default 'Sale <reference>' format)
                    $receiptIds = DB::table('sales_receipt_lines')
                        ->where('sale_id', $row['id'])
                        ->pluck('sales_receipt_id');

                    if ($receiptIds->isNotEmpty()) {
                        DB::table('sales_receipts')
                            ->whereIn('id', $receiptIds)
                            ->where('particular', 'Sale ' . $row['current_reference'])
                            ->update(['particular' => 'Sale ' . $row['new_reference']]);
                    }

                    $updated++;
                }
            });

            $this->info("Successfully processed in transaction.");
            $this->info("Updated {$updated} rows in sales, along with related receipt lines and payments.");
            $this->newLine();
            $this->info('Renumbering complete.');
            return 0;
        }

        $this->newLine();
        $this->info('Preview complete. No changes were made.');
        $this->line('Run again with --apply to persist the new bill numbers.');

        return 0;
    }

    /**
     * Build the renumber plan for a table.
     *
     * @return array{rows: array<int, array<string, mixed>>, byFy: array<string, int>}
     */
    protected function buildRenumberPlan(): array
    {
        $currentFinancialYear = $this->financialYearLabel(now());
        $startYear = explode('-', $currentFinancialYear)[0];
        $endYear = explode('-', $currentFinancialYear)[1];
        $currentYearStart = Carbon::createFromFormat('Y-m-d', $startYear . '-04-01')->startOfDay();
        $currentYearEnd = Carbon::createFromFormat('Y-m-d', $endYear . '-03-31')->endOfDay();

        $records = DB::table('sales')
            ->select(['id', 'reference', 'date', 'created_at'])
            ->whereBetween('date', [$currentYearStart->toDateString(), $currentYearEnd->toDateString()])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $rows = [];
        $byFy = [];
        $counters = [];

        foreach ($records as $record) {
            $billDate = $record->date
                ? Carbon::parse($record->date)
                : ($record->created_at ? Carbon::parse($record->created_at) : now());
            $fy = $this->financialYearLabel($billDate);

            if (!isset($counters[$fy])) {
                $counters[$fy] = 0;
            }

            $counters[$fy]++;
            $byFy[$fy] = ($byFy[$fy] ?? 0) + 1;

            $rows[] = [
                'id' => $record->id,
                'bill_date' => $billDate->format('Y-m-d'),
                'current_reference' => $record->reference,
                'new_reference' => $this->makeReference($counters[$fy], $fy),
            ];
        }

        return [
            'rows' => $rows,
            'byFy' => $byFy,
        ];
    }

    protected function financialYearLabel(Carbon $date): string
    {
        $year = (int) $date->year;
        $month = (int) $date->month;

        if ($month >= 4) {
            return $year . '-' . ($year + 1);
        }

        return ($year - 1) . '-' . $year;
    }

    protected function financialYearStartYear(string $financialYear): int
    {
        return (int) explode('-', $financialYear)[0];
    }

    protected function makeReference(int $number, string $financialYear): string
    {
        $paddedNumber = str_pad((string) $number, 5, '0', STR_PAD_LEFT);

        $startYear = $this->financialYearStartYear($financialYear);

        return 'SSA/' . $paddedNumber . '/' . $startYear . '-' . ($startYear + 1);
    }
}
