<?php

namespace App\Exports;

use App\Services\ReportQueryService;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProfitLossReportExport implements FromQuery, WithMapping, WithHeadings, WithChunkReading, ShouldAutoSize
{
    protected $filters;

    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    public function query()
    {
        return app(ReportQueryService::class)->buildProfitLossQuery($this->filters);
    }

    public function map($sale): array
    {
        // Computed columns from buildProfitLossQuery are in paise
        $profit = $sale->profit_amount / 100;

        return [
            $sale->customer->customer_name ?? $sale->customer_name ?? '-',
            $sale->reference,
            optional(\Carbon\Carbon::parse($sale->date))->format('d-m-Y'),
            round($sale->amount_incl_vat / 100, 2),
            round($sale->purchase_total / 100, 2),
            round($profit, 2),
            $profit >= 0 ? 'Profit' : 'Loss',
        ];
    }

    public function headings(): array
    {
        return [
            'Customer Name',
            'Sales Bill Ref No',
            'Sales Bill Date',
            'Overall Amount (Incl. VAT)',
            'Purchased Rate Total (Incl. VAT)',
            'Profit/Loss Amount',
            'Profit/Loss Status',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
