<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Modules\Sale\Entities\SaleDetails;

class GstrReportExport implements FromQuery, WithChunkReading, WithMapping, WithHeadings, ShouldAutoSize
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        return app(\App\Services\ReportQueryService::class)->buildGstrQuery($this->filters);
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function map($row): array
    {
        $hsn = $row->hsn ?? ($row->product->hsn ?? '');
        $description = $row->product_name;
        $uqc = $row->product->product_unit ?? '';
        $qty = $row->quantity;
        $totalValue = ($row->mrp ?? 0) * $qty;
        $taxableValue = ($row->rate ?? 0) * $qty;

        $igst = 0; $cgst = 0; $sgst = 0;
        if (!empty($row->sale) && ($row->sale->overall_igst ?? 0) > 0) {
            $igst = $row->tax_amount ?? 0;
        } else {
            $cgst = $sgst = (($row->tax_amount ?? 0) / 2);
        }

        $cess = 0;
        $rate = rtrim(rtrim((string)($row->tax_percentage ?? 0), '0'), '.');

        return [
            $hsn,
            $description,
            $uqc,
            $qty,
            number_format($totalValue, 2, '.', ''),
            number_format($taxableValue, 2, '.', ''),
            number_format($igst, 2, '.', ''),
            number_format($cgst, 2, '.', ''),
            number_format($sgst, 2, '.', ''),
            number_format($cess, 2, '.', ''),
            $rate,
        ];
    }

    public function headings(): array
    {
        return [
            'HSN',
            'Description',
            'UQC',
            'Quantity',
            'Total Value (MRP)',
            'Taxable Value',
            'IGST',
            'CGST',
            'SGST',
            'Cess',
            'Rate %',
        ];
    }
}
