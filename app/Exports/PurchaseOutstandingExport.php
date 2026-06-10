<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;
use App\Services\ReportQueryService;

class PurchaseOutstandingExport implements FromQuery, WithMapping, WithHeadings, WithChunkReading, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    protected function getQueryFromService(): Builder
    {
        return app(ReportQueryService::class)->buildPurchaseOutstandingQuery($this->filters);
    }

    public function query()
    {
        return $this->getQueryFromService();
    }

    public function map($purchase): array
    {
        $agingDays = self::calculateAging($purchase->date);
        $billAmount = $purchase->overall_net_rate ?? $purchase->total_amount ?? 0;
        $paidAmount = $purchase->paid_amount ?? 0;
        $balanceAmount = $purchase->due_amount ?? ($billAmount - $paidAmount);

        return [
            $purchase->supplier->supplier_name ?? $purchase->supplier_name ?? '-',
            $purchase->reference,
            optional(Carbon::parse($purchase->date))->format('d-m-Y'),
            number_format($billAmount, 2),
            number_format($paidAmount, 2),
            number_format($balanceAmount, 2),
            $agingDays,
        ];
    }

    public function headings(): array
    {
        return [
            'Supplier Name',
            'Purchase Bill Ref No',
            'Bill Date',
            'Bill Overall Amount',
            'Paid Amount',
            'Balance Amount',
            'Aging (Days)',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public static function calculateAging($invoiceDate)
    {
        if (!$invoiceDate) {
            return 0;
        }

        $invoiced = Carbon::parse($invoiceDate);
        $today = Carbon::today();

        if ($invoiced >= $today) {
            return 0;
        }

        return $today->diffInDays($invoiced);
    }
}
