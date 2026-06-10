<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Modules\Sale\Entities\Sale;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use App\Services\ReportQueryService;

class SalesOutstandingExport implements FromQuery, WithMapping, WithHeadings, WithChunkReading, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    public function view(): View
    {
        // kept for backward compatibility in case some callers rely on the view method
        $sales = $this->getFilteredSales();

        return view('exports.sales-outstanding-excel', [
            'sales' => $sales,
            'filters' => $this->filters,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    protected function getFilteredSales()
    {
        return $this->getQueryFromService()->get();
    }

    protected function getQueryFromService(): Builder
    {
        return app(ReportQueryService::class)->buildSalesOutstandingQuery($this->filters);
    }

    /**
     * FromQuery implementation
     */
    public function query()
    {
        return $this->getQueryFromService();
    }

    public function map($sale): array
    {
        $agingDays = self::calculateAging($sale->date);
        $billAmount = $sale->overall_net_rate ?? $sale->overall_amount ?? $sale->total_amount ?? 0;
        $paidAmount = $sale->paid_amount ?? 0;
        $balanceAmount = $sale->due_amount ?? ($billAmount - $paidAmount);

        return [
            $sale->customer->customer_name ?? $sale->customer_name ?? '-',
            $sale->reference,
            optional(Carbon::parse($sale->date))->format('d-m-Y'),
            number_format($billAmount, 2),
            number_format($paidAmount, 2),
            number_format($balanceAmount, 2),
            $agingDays,
        ];
    }

    public function headings(): array
    {
        return [
            'Customer Name',
            'Sales Bill Ref No',
            'Bill Date',
            'Bill Overall Amount',
            'Received Amount',
            'Balance Amount',
            'Aging (Days)',
        ];
    }

    public function chunkSize(): int
    {
        return 500; // reasonable default
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
