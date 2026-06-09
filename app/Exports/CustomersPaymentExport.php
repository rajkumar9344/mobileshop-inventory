<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomersPaymentExport implements FromCollection, WithMapping, WithHeadings, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        return app(\App\Services\ReportQueryService::class)->getCustomersPaymentCollection($this->filters);
    }

    public function map($line): array
    {
        $customer = $line->receipt->customer->customer_name ?? '-';
        $ref = $line->sale->reference ?? $line->bill_ref ?? '-';
        $saleDate = '';
        if (!empty($line->sale->date)) {
            $saleDate = \Carbon\Carbon::parse($line->sale->date)->format('d-m-Y');
        } elseif (!empty($line->bill_date)) {
            $saleDate = optional($line->bill_date)->format('d-m-Y');
        }

        $billAmount = $line->bill_amount ?? ($line->sale->overall_net_rate ?? $line->sale->overall_amount ?? $line->sale->total_amount ?? 0);
        $receivedAmount = $line->payment_amount;
        $receivedDate = !empty($line->receipt->date) ? \Carbon\Carbon::parse($line->receipt->date)->format('d-m-Y') : '';
        $paymentMode = $line->receipt->payment_mode ?? '-';

        return [
            $customer,
            $ref,
            $saleDate,
            number_format($billAmount, 2, '.', ''),
            number_format($receivedAmount, 2, '.', ''),
            $receivedDate,
            $paymentMode,
        ];
    }

    public function headings(): array
    {
        return [
            'Customer Name',
            'Sales Bill Ref No',
            'Sales Bill Date',
            'Sales Bill Overall Amount',
            'Received Amount',
            'Received Date',
            'Payment Mode',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
