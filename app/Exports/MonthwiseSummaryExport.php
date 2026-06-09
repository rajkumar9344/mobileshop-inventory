<?php

namespace App\Exports;

use App\Services\DailyOperationsService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class MonthwiseSummaryExport implements FromView
{
    protected int $year;
    protected $month;

    public function __construct(int $year, $month)
    {
        $this->year = $year;
        $this->month = $month;
    }

    public function view(): View
    {
        $data = DailyOperationsService::getMonthwiseSummary($this->year, $this->month);

        return view('exports.monthwise-summary-excel', [
            'data' => $data,
            'year' => $this->year,
            'month' => $this->month,
        ]);
    }
}
