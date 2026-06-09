<?php

namespace App\Exports;

use App\Services\DailyOperationsService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class DailyOperationsReportExport implements FromView
{
    protected string $date;

    public function __construct(string $date)
    {
        $this->date = $date;
    }

    public function view(): View
    {
        $data = DailyOperationsService::getReportData($this->date);

        return view('exports.daily-operations-excel', [
            'data' => $data,
            'date' => $this->date,
        ]);
    }
}
