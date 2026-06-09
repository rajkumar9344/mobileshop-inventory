<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use App\Services\ReportQueryService;

class ReorderReportExport implements FromQuery, WithMapping, WithHeadings, WithChunkReading, ShouldAutoSize
{
    protected $filters;

    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    public function query()
    {
        // Make export behaviour consistent with the UI/PDF by including products
        // that already have active purchases. Also eager-load relations used
        // by the mapper to avoid N+1 issues.
        $this->filters['include_active_purchases'] = $this->filters['include_active_purchases'] ?? true;
        return app(ReportQueryService::class)
            ->buildReorderQuery($this->filters)
            ->with(['category', 'supplier', 'productCodes']);
    }

    public function map($product): array
    {
        $reorderQty = null;
        if ($product->product_quantity < $product->product_stock_alert) {
            $reorderQty = $product->product_stock_alert - $product->product_quantity;
        }

        // Fallback: if primary `product_code` is empty, use the first related
        // `productCodes` entry (if any) so exports show the same code as UI.
        $code = $product->product_code ?: ($product->productCodes->first()->code ?? '-');

        return [
            $product->category->category_name ?? '-',
            $product->product_name,
            $code,
            $product->product_note ?? '-',
            $product->supplier->supplier_name ?? '-',
            $reorderQty !== null ? $reorderQty : '-',
            optional($product->created_at)->format('d-m-Y'),
        ];
    }

    public function headings(): array
    {
        return [
            'Product Category',
            'Product Name',
            'Product Code',
            'Compatibility',
            'Shop Name (Supplier)',
            'Reorder Quantity',
            'Generated Date',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}