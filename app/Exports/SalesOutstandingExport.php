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
        $agingDays = self::calculateAging($sale->due_date);
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
            optional(Carbon::parse($sale->due_date))->format('d-m-Y'),
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
            'Bill Due Date',
            'Aging (Days)',
        ];
    }

    public function chunkSize(): int
    {
        return 500; // reasonable default
    }

    protected function applyAgingFilter($query, $range, $today)
    {
        switch ($range) {
            case '1-10':
                $query->whereDate('due_date', '>=', $today->copy()->subDays(10))
                      ->whereDate('due_date', '<', $today);
                break;
            case '10-20':
                $query->whereDate('due_date', '>=', $today->copy()->subDays(20))
                      ->whereDate('due_date', '<', $today->copy()->subDays(10));
                break;
            case '20-30':
                $query->whereDate('due_date', '>=', $today->copy()->subDays(30))
                      ->whereDate('due_date', '<', $today->copy()->subDays(20));
                break;
            case '30-60':
                $query->whereDate('due_date', '>=', $today->copy()->subDays(60))
                      ->whereDate('due_date', '<', $today->copy()->subDays(30));
                break;
            case '60-90':
                $query->whereDate('due_date', '>=', $today->copy()->subDays(90))
                      ->whereDate('due_date', '<', $today->copy()->subDays(60));
                break;
            case '90+':
                $query->whereDate('due_date', '<', $today->copy()->subDays(90));
                break;
        }
        return $query;
    }

    public static function calculateAging($dueDate)
    {
        if (!$dueDate) {
            return 0;
        }
        $due = Carbon::parse($dueDate);
        $today = Carbon::today();
        
        if ($due >= $today) {
            return 0;
        }
        
        return $today->diffInDays($due);
    }
}
