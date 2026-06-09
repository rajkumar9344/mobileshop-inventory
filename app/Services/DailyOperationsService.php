<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Sale\Entities\Sale;
use Modules\SalesReceipt\Entities\SalesReceipt;
use Modules\Expense\Entities\Expense;
use Modules\Setting\Entities\Setting;
use Modules\Setting\Entities\PettyCashEntry;

class DailyOperationsService
{
    /**
     * Get all data for the daily operations report
     */
    public static function getReportData(string $dateString): array
    {
        $date = Carbon::parse($dateString);
        $previousDate = $date->copy()->subDay();

        // Opening balance
        $openingBalance = self::getOpeningBalance($previousDate);

        // Daily Sales (Completed only)
        $dailySalesGross = Sale::where('status', '!=', 'Draft')
            ->whereDate('date', $date)
            ->sum(DB::raw('COALESCE(overall_gross_amount, total_amount)')) / 100;

        $dailySalesNet = Sale::where('status', '!=', 'Draft')
            ->whereDate('date', $date)
            ->sum('total_amount') / 100;

        // Daily Received from Sales Receipts
        $dailyReceived = SalesReceipt::whereDate('date', $date)->sum('total_amount') / 100;

        // Daily Expenses
        $dailyExpenses = Expense::whereDate('date', $date)->sum('amount') / 100;

        // Calculate closing balances
        $netClosingBeforePetty = $openingBalance + $dailyReceived - $dailyExpenses;
        // Tomorrow opening is stored per-date in petty_cash_entries. Fallback to settings default if missing.
        $tomorrowOpening = self::getPettyCashForDate($date->copy()->addDay());
        $closingBalance = $netClosingBeforePetty - $tomorrowOpening;

        // Payment method totals
        $paymentTotals = self::getPaymentMethodTotals($date);

        // Receipts by payment method
        $receiptsByMethod = self::getReceiptsByMethod($date);

        // Expenses by payment mode
        $expensesByMode = self::getExpensesByMode($date);

        return [
            'opening_balance' => $openingBalance,
            'daily_sales_gross' => $dailySalesGross,
            'daily_sales_net' => $dailySalesNet,
            'daily_received' => $dailyReceived,
            'daily_expenses' => $dailyExpenses,
            'net_closing_before_petty' => $netClosingBeforePetty,
            'tomorrow_opening' => $tomorrowOpening,
            'closing_balance' => $closingBalance,
            'payment_totals' => $paymentTotals,
            'receipts_by_method' => $receiptsByMethod,
            'expenses_by_mode' => $expensesByMode,
        ];
    }

    /**
     * Get opening balance
     */
    protected static function getOpeningBalance($previousDate): float
    {
        // If a petty cash entry exists for the target date (previousDate + 1),
        // that entry is the explicit 'opening' for that day — return it directly.
        $targetDate = $previousDate->copy()->addDay();
        $pettyForTarget = PettyCashEntry::whereDate('date', $targetDate)->value('amount');
        if ($pettyForTarget !== null) {
            return (float) $pettyForTarget;
        }
        // If no explicit petty entry for the report date, opening balance is 0.
        return 0;
    }

    /**
     * Get petty cash setting
     */
    protected static function getPettyCashSetting(): float
    {
        static $cachedSetting = null;
        if ($cachedSetting === null) {
            $cachedSetting = Setting::first();
        }

        return $cachedSetting->default_opening_balance ?? 0;
    }

    /**
     * Get petty cash amount for a specific date (date is the opening date for that day).
     * If not present, fallback to a default from settings if available, otherwise 0.
     */
    protected static function getPettyCashForDate(Carbon $date): float
    {
        $entry = PettyCashEntry::whereDate('date', $date->toDateString())->first();
        if ($entry) {
            return (float) $entry->amount;
        }

        return 0;
    }

    /**
     * Get payment method totals for a date
     */
    protected static function getPaymentMethodTotals($date): array
    {
        $paymentModes = ['Cash', 'Cheque', 'Cards', 'Bank Transfer', 'UPI Payment', 'Product return'];

        // Aggregate receipts by payment_mode in a single query
        $receiptSums = SalesReceipt::whereDate('date', $date)
            ->select('payment_mode', DB::raw('SUM(total_amount) as total'))
            ->groupBy('payment_mode')
            ->get()
            ->keyBy('payment_mode');

        // Aggregate expenses by payment_mode in a single query
        $expenseSums = Expense::whereDate('date', $date)
            ->select('payment_mode', DB::raw('SUM(amount) as total'))
            ->groupBy('payment_mode')
            ->get()
            ->keyBy('payment_mode');

        $totals = [];
        $totalBeforeExpense = 0;
        $productReturnAmount = 0;

        foreach ($paymentModes as $mode) {
            $received = isset($receiptSums[$mode]) ? ($receiptSums[$mode]->total / 100) : 0;
            $expense = isset($expenseSums[$mode]) ? ($expenseSums[$mode]->total / 100) : 0;

            $totals[$mode] = [
                'before_expense' => $received,
                'expense' => $expense,
                'after_expense' => $received - $expense,
            ];

            if ($mode === 'Product return') {
                $productReturnAmount = $received;
            } else {
                $totalBeforeExpense += $received;
            }
        }

        // Total expenses for the date (fallback to aggregated total)
        $totalExpenses = array_sum(array_map(fn($i) => $i->total ?? 0, $expenseSums->all())) / 100;
        $totalAfterExpense = $totalBeforeExpense + $productReturnAmount - $totalExpenses;

        return [
            'methods' => $totals,
            'total_before_expense' => $totalBeforeExpense,
            'product_return_amount' => $productReturnAmount,
            'total_after_expense' => $totalAfterExpense,
        ];
    }

    /**
     * Get receipts grouped by payment method
     */
    protected static function getReceiptsByMethod($date): array
    {
        $paymentModes = ['Cash', 'Cheque', 'Cards', 'Bank Transfer', 'UPI Payment', 'Product return'];

        // Fetch all receipts for the date in one query and group in memory
        $allReceipts = SalesReceipt::with('customer')
            ->whereDate('date', $date)
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('payment_mode');

        $receiptsByMethod = [];

        foreach ($paymentModes as $mode) {
            $group = $allReceipts->get($mode, collect());

            $receiptsByMethod[$mode] = $group->map(function ($receipt) {
                return [
                    'id' => $receipt->id,
                    'reference' => $receipt->reference ?? ('RE' . str_pad($receipt->id, 5, '0', STR_PAD_LEFT)),
                    'customer_name' => $receipt->customer->customer_name ?? '-',
                    'date' => Carbon::parse($receipt->date)->format('d-m-Y'),
                    // The SalesReceipt model exposes `total_amount` in major units via accessor,
                    // so avoid dividing here to prevent double-conversion.
                    'total_amount' => $receipt->total_amount,
                ];
            })->values()->toArray();
        }

        return $receiptsByMethod;
    }

    /**
     * Get expenses grouped by payment mode
     */
    protected static function getExpensesByMode($date): array
    {
        return Expense::with('category')
            ->whereDate('date', $date)
            ->select('payment_mode', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_mode')
            ->get()
            ->keyBy('payment_mode')
            ->map(function ($item) {
                return [
                    'total' => $item->total_amount / 100,
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get monthwise summary data
     */
    public static function getMonthwiseSummary(int $year, $month): array
    {
        $paymentModes = ['Cash', 'Cheque', 'Cards', 'Bank Transfer', 'UPI Payment', 'Product return'];

        // If 'all' or 0 passed, treat as full year and return per-month rows
        if ($month === 'all' || $month === 0 || $month === '0') {
            $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
            $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();

            // Aggregate receipts by month and payment_mode
            $monthlyReceipts = SalesReceipt::whereBetween('date', [$startDate, $endDate])
                ->select(DB::raw('MONTH(date) as month'), 'payment_mode', DB::raw('SUM(total_amount) as total'))
                ->groupBy('month', 'payment_mode')
                ->get();

            $monthlyData = [];
            foreach ($monthlyReceipts as $r) {
                $m = (int) $r->month;
                if (!isset($monthlyData[$m])) {
                    $monthlyData[$m] = [
                        'date' => Carbon::createFromDate($year, $m, 1)->format('F Y'),
                        'totals' => array_fill_keys($paymentModes, 0),
                        'grand_total' => 0,
                    ];
                }
                $monthlyData[$m]['totals'][$r->payment_mode] = ($r->total / 100);
                $monthlyData[$m]['grand_total'] += ($r->total / 100);
            }

            // Expenses per month
            $expenseTotals = Expense::whereBetween('date', [$startDate, $endDate])
                ->select(DB::raw('MONTH(date) as month'), DB::raw('SUM(amount) as total'))
                ->groupBy('month')
                ->get()
                ->keyBy('month');

            // Receipt counts per month
            $receiptCounts = SalesReceipt::whereBetween('date', [$startDate, $endDate])
                ->select(DB::raw('MONTH(date) as month'), DB::raw('COUNT(*) as cnt'))
                ->groupBy('month')
                ->get()
                ->keyBy('month');

            // Attach expenses and counts
            foreach ($monthlyData as $mKey => &$mRow) {
                $expAmount = isset($expenseTotals[$mKey]) ? ($expenseTotals[$mKey]->total / 100) : 0;
                $mRow['overall_expenses'] = $expAmount;
                $mRow['count'] = isset($receiptCounts[$mKey]) ? (int) $receiptCounts[$mKey]->cnt : 0;
                $mRow['net_after_expenses'] = $mRow['grand_total'] - $expAmount;
            }
            unset($mRow);

            ksort($monthlyData);

            // Totals
            $columnTotals = array_fill_keys($paymentModes, 0);
            $columnTotals['overall_expenses'] = 0;
            $grandTotal = 0;
            $netTotal = 0;

            foreach ($monthlyData as $row) {
                foreach ($paymentModes as $mode) {
                    $columnTotals[$mode] += $row['totals'][$mode] ?? 0;
                }
                $columnTotals['overall_expenses'] += $row['overall_expenses'] ?? 0;
                $grandTotal += $row['grand_total'];
                $netTotal += $row['net_after_expenses'] ?? 0;
            }

            return [
                'rows' => array_values($monthlyData),
                'column_totals' => $columnTotals,
                'grand_total' => $grandTotal,
                'net_total' => $netTotal,
                'month_name' => $year,
            ];
        }

        // Specific month: return a single summary row for the month
        $startDate = Carbon::createFromDate($year, (int)$month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Totals by payment mode for the month
        $receiptSums = SalesReceipt::whereBetween('date', [$startDate, $endDate])
            ->select('payment_mode', DB::raw('SUM(total_amount) as total'))
            ->groupBy('payment_mode')
            ->get()
            ->keyBy('payment_mode');

        $rowTotals = array_fill_keys($paymentModes, 0);
        $grandTotal = 0;
        $receiptCount = SalesReceipt::whereBetween('date', [$startDate, $endDate])->count();

        foreach ($paymentModes as $mode) {
            $amt = isset($receiptSums[$mode]) ? ($receiptSums[$mode]->total / 100) : 0;
            $rowTotals[$mode] = $amt;
            $grandTotal += $amt;
        }

        $overallExpenses = Expense::whereBetween('date', [$startDate, $endDate])->sum('amount') / 100;
        $netAfterExpenses = $grandTotal - $overallExpenses;

        $singleRow = [
            'date' => Carbon::createFromDate($year, (int)$month, 1)->format('F Y'),
            'totals' => $rowTotals,
            'grand_total' => $grandTotal,
            'overall_expenses' => $overallExpenses,
            'count' => $receiptCount,
            'net_after_expenses' => $netAfterExpenses,
        ];

        // Column totals mirror row totals for single month
        $columnTotals = $rowTotals;
        $columnTotals['overall_expenses'] = $overallExpenses;

        return [
            'rows' => [$singleRow],
            'column_totals' => $columnTotals,
            'grand_total' => $grandTotal,
            'net_total' => $netAfterExpenses,
            'month_name' => Carbon::createFromDate($year, (int)$month, 1)->format('F Y'),
        ];
    }
}
