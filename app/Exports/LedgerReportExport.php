<?php

namespace App\Exports;

use App\Services\LedgerService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class LedgerReportExport implements FromView
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function view(): View
    {
        $filters = $this->filters;

        // Default to requested FY (or current FY) if dates not provided
        if (empty($filters['start_date']) || empty($filters['end_date'])) {
            $dates = LedgerService::getFinancialYearDates($filters['financial_year'] ?? null);
            $filters['start_date'] = $dates['start_date'];
            $filters['end_date'] = $dates['end_date'];
        }

        $data = LedgerService::buildLedgerData($filters);

        return view('exports.ledger-report-excel', [
            'data' => $data,
            'filters' => $filters,
        ]);
    }
}
