<?php

namespace App\Livewire\Reports;

use Livewire\Component;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Sale\Entities\Sale;
use Modules\SalesReceipt\Entities\SalesReceipt;
use Modules\Expense\Entities\Expense;
use Modules\Setting\Entities\Setting;
use Modules\Setting\Entities\PettyCashEntry;
use App\Services\DailyOperationsService;

class DailyOperationsReport extends Component
{
    public $report_date;
    
    // KPI values
    public $opening_balance = 0;
    public $daily_sales_gross = 0;
    public $daily_sales_net = 0;
    public $daily_received = 0;
    public $daily_expenses = 0;
    public $net_closing_before_petty = 0;
    public $tomorrow_opening = 0;
    public $closing_balance = 0;
    
    // Payment method totals
    public $payment_totals = [];
    public $total_before_expense = 0;
    public $total_after_expense = 0;
    public $product_return_amount = 0;
    
    // Receipts by payment method
    public $receipts_by_method = [];
    
    // Expenses by payment mode
    public $expenses_by_mode = [];
    
    // Monthwise summary
    public $summary_year;
    public $summary_month;
    public $monthwise_data = [];
    
    // Accordion states
    public $expanded_methods = [];

    // Controls visibility of the filter / export bar (hide on home page)
    public $showFilter = true;

    protected $listeners = ['refreshReport' => '$refresh'];

    public function mount($showFilter = true)
    {
        $this->showFilter = $showFilter;
        $this->report_date = today()->format('Y-m-d');
        $this->summary_year = date('Y');
        $this->summary_month = date('m');
        $this->loadReportData();
        $this->loadMonthwiseSummary();
    }

    public function updatedReportDate()
    {
        $this->loadReportData();
    }

    public function resetFilters()
    {
        $this->report_date = today()->format('Y-m-d');
        $this->loadReportData();
        $this->loadMonthwiseSummary();
    }

    public function updatedSummaryYear()
    {
        $this->loadMonthwiseSummary();
    }

    public function updatedSummaryMonth()
    {
        $this->loadMonthwiseSummary();
    }

    public function loadReportData()
    {
        $data = DailyOperationsService::getReportData($this->report_date);

        $this->opening_balance = $data['opening_balance'] ?? 0;
        $this->daily_sales_gross = $data['daily_sales_gross'] ?? 0;
        $this->daily_sales_net = $data['daily_sales_net'] ?? 0;
        $this->daily_received = $data['daily_received'] ?? 0;
        $this->daily_expenses = $data['daily_expenses'] ?? 0;
        $this->net_closing_before_petty = $data['net_closing_before_petty'] ?? 0;
        $this->tomorrow_opening = $data['tomorrow_opening'] ?? 0;
        $this->closing_balance = $data['closing_balance'] ?? 0;

        // Payment totals structure returned by service
        $this->payment_totals = $data['payment_totals']['methods'] ?? [];
        $this->total_before_expense = $data['payment_totals']['total_before_expense'] ?? 0;
        $this->product_return_amount = $data['payment_totals']['product_return_amount'] ?? 0;
        $this->total_after_expense = $data['payment_totals']['total_after_expense'] ?? 0;

        $this->receipts_by_method = $data['receipts_by_method'] ?? [];
        $this->expenses_by_mode = $data['expenses_by_mode'] ?? [];
    }

    protected function getOpeningBalance($previousDate)
    {
        // Get previous day's closing balance or return default from settings
        $setting = Setting::first();
        $defaultOpening = $setting->default_opening_balance ?? 0;
        
        // Calculate previous day's closing if data exists
        $prevReceived = SalesReceipt::whereDate('date', $previousDate)->sum('total_amount') / 100;
        $prevExpenses = Expense::whereDate('date', $previousDate)->sum('amount') / 100;
        
        if ($prevReceived > 0 || $prevExpenses > 0) {
            // Recursively get previous opening and calculate
            $prevOpening = $this->getOpeningBalanceForDate($previousDate->copy()->subDay());
            return $prevOpening + $prevReceived - $prevExpenses - $this->getPettyCashSetting($previousDate);
        }
        
        return $defaultOpening;
    }

    protected function getOpeningBalanceForDate($date)
    {
        $setting = Setting::first();
        return $setting->default_opening_balance ?? 0;
    }

    protected function getPettyCashSetting($forDate = null)
    {
        $date = $forDate ? Carbon::parse($forDate) : Carbon::parse($this->report_date ?? today());

        $entry = PettyCashEntry::whereDate('date', $date->toDateString())->first();
        if ($entry) {
            return (float) $entry->amount;
        }

        return 0;
    }

    protected function loadPaymentMethodTotals($date)
    {
        $paymentModes = ['Cash', 'Cheque', 'Cards', 'Bank Transfer', 'UPI Payment', 'Product return'];
        
        $this->payment_totals = [];
        $this->total_before_expense = 0;
        $this->product_return_amount = 0;
        
        foreach ($paymentModes as $mode) {
            $received = SalesReceipt::whereDate('date', $date)
                ->where('payment_mode', $mode)
                ->sum('total_amount') / 100;
            
            $expense = Expense::whereDate('date', $date)
                ->where('payment_mode', $mode)
                ->sum('amount') / 100;
            
            $this->payment_totals[$mode] = [
                'before_expense' => $received,
                'expense' => $expense,
                'after_expense' => $received - $expense,
            ];
            
            if ($mode === 'Product return') {
                $this->product_return_amount = $received;
            } else {
                $this->total_before_expense += $received;
            }
        }
        
        $this->total_after_expense = $this->total_before_expense + $this->product_return_amount - $this->daily_expenses;
    }

    protected function loadReceiptsByMethod($date)
    {
        $paymentModes = ['Cash', 'Cheque', 'Cards', 'Bank Transfer', 'UPI Payment', 'Product return'];
        
        $this->receipts_by_method = [];
        
        foreach ($paymentModes as $mode) {
            $receipts = SalesReceipt::with('customer')
                ->whereDate('date', $date)
                ->where('payment_mode', $mode)
                ->orderBy('id', 'desc')
                ->get()
                ->map(function ($receipt) {
                    return [
                        'id' => $receipt->id,
                        'reference' => $receipt->reference ?? ('RE' . str_pad($receipt->id, 5, '0', STR_PAD_LEFT)),
                        'customer_name' => $receipt->customer->customer_name ?? '-',
                        'date' => \Carbon\Carbon::parse($receipt->date)->format('d-m-Y'),
                        'total_amount' => $receipt->total_amount,
                    ];
                })
                ->toArray();
            
            $this->receipts_by_method[$mode] = $receipts;
        }
    }

    protected function loadExpensesByMode($date)
    {
        $this->expenses_by_mode = Expense::with('category')
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

    public $monthwise_column_totals = [];
    public $monthwise_grand_total = 0;
    public $monthwise_net_total = 0;
    public $monthwise_month_name = '';

    public function loadMonthwiseSummary()
    {
        $year = $this->summary_year;
        $month = $this->summary_month;

        $data = DailyOperationsService::getMonthwiseSummary((int)$year, $month);

        $this->monthwise_data = $data['rows'] ?? [];
        $this->monthwise_column_totals = $data['column_totals'] ?? [];
        $this->monthwise_grand_total = $data['grand_total'] ?? 0;
        $this->monthwise_net_total = $data['net_total'] ?? 0;
        $this->monthwise_month_name = $data['month_name'] ?? '';
    }

    public function updatedTomorrowOpening($value)
    {
        $this->tomorrow_opening = is_numeric($value) ? (float) $value : 0;
        $this->closing_balance = ($this->net_closing_before_petty ?? 0) - $this->tomorrow_opening;
    }

    public function saveTomorrowOpening()
    {
        $nextDate = Carbon::parse($this->report_date)->addDay()->toDateString();

        PettyCashEntry::updateOrCreate([
            'date' => $nextDate,
        ], [
            'amount' => $this->tomorrow_opening,
            'user_id' => auth()->id() ?? null,
        ]);

        // Reload report data so all values are consistent with persisted entry
        $this->loadReportData();
    }

    public function expandAll()
    {
        $this->expanded_methods = ['Cash', 'Cheque', 'Cards', 'Bank Transfer', 'UPI Payment', 'Product return'];
    }

    public function collapseAll()
    {
        $this->expanded_methods = [];
    }

    public function toggleMethod($method)
    {
        if (in_array($method, $this->expanded_methods)) {
            $this->expanded_methods = array_diff($this->expanded_methods, [$method]);
        } else {
            $this->expanded_methods[] = $method;
        }
    }

    public function render()
    {
        return view('livewire.reports.daily-operations-report', [
            'years' => range(date('Y'), date('Y') - 5),
            'months' => [
                '01' => 'January', '02' => 'February', '03' => 'March',
                '04' => 'April', '05' => 'May', '06' => 'June',
                '07' => 'July', '08' => 'August', '09' => 'September',
                '10' => 'October', '11' => 'November', '12' => 'December',
            ],
        ]);
    }
}
