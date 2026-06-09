<?php

namespace Modules\Product\DataTables;

use Modules\Product\Entities\Product;
use Carbon\Carbon;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class ProductDataTable extends DataTable
{
    use \App\Traits\HasPdfExport;

    protected int $pdfMaxRecords = 500;

    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->addColumn('action', function ($data) {
                return view('product::products.partials.actions', compact('data'));
            })
            ->addColumn('product_image', function ($data) {
                $url = $data->getFirstMediaUrl('images', 'thumb');
                return '<img src="' . $url . '" border="0" width="50" class="img-thumbnail" align="center"/>';
            })
            ->addColumn('category_name', function ($data) {
                return $data->category_name ?? '';
            })
            ->addColumn('product_price', function ($data) {
                return format_currency($data->product_price);
            })
            ->addColumn('product_cost', function ($data) {
                return format_currency($data->product_cost);
            })
            ->addColumn('product_unit', function ($data) {
                return $data->product_unit ?? '';
            })
            ->addColumn('mrp', function ($data) {
                // show 0.00 when mrp is null to match other price columns
                return format_currency($data->mrp ?? 0);
            })
            ->addColumn('list_price', function ($data) {
                // show 0.00 when list_price is null to match Sell Rate behavior
                return format_currency($data->list_price ?? 0);
            })
            // subcategory will be selected in the query() as `subcategory`
            ->addColumn('hsn', function ($data) {
                return $data->hsn ?? 'N/A';
            })
            ->addColumn('product_order_tax', function ($data) {
                return $data->product_order_tax !== null ? $data->product_order_tax . '%' : 'N/A';
            })
            ->addColumn('open_quantity', function ($data) {
                return $data->open_quantity . ' ' . $data->product_unit;
            })
            ->addColumn('purchase_quantity', function ($data) {
                return $data->purchase_quantity . ' ' . $data->product_unit;
            })
            ->addColumn('product_quantity', function ($data) {
                return $data->product_quantity . ' ' . $data->product_unit;
            })
            ->editColumn('product_code', function ($data) {
                // Combine main product_code and all alternate codes
                $codes = collect([$data->product_code]);
                if ($data->productCodes->isNotEmpty()) {
                    $codes = $codes->concat($data->productCodes->pluck('code'));
                }
                return $codes->unique()->filter()->implode(', ');
            })
            ->filterColumn('product_code', function($query, $keyword) {
                 $query->where(function($q) use ($keyword) {
                    $q->where('products.product_code', 'like', "%{$keyword}%")
                       // Search in related product_codes
                      ->orWhereHas('productCodes', function($subQ) use ($keyword) {
                          $subQ->where('code', 'like', "%{$keyword}%");
                      });
                 });
            })
            ->rawColumns(['product_image']);
    }

    public function query(Product $model)
    {
        $table = $model->getTable();
        // Eager load productCodes to display them
        $query = $model->newQuery()->with('productCodes')
            ->leftJoin('subcategories', 'subcategories.id', '=', $table . '.subcategory_id')
            ->leftJoin('categories', $table . '.category_id', '=', 'categories.id')
            ->select($table . '.*', 'subcategories.subcategory_name as subcategory', 'categories.category_name as category_name');

        // apply optional category filter
        $categoryId = request()->get('category_id');
        if ($categoryId) {
            $query->where($table . '.category_id', $categoryId);
        }

        // apply optional subcategory filter
        $subcategoryId = request()->get('subcategory_id');
        $subcategoryName = request()->get('subcategory_name');
        if ($subcategoryName) {
            // When the UI deduplicates subcategories by name we receive a
            // subcategory_name parameter and should filter across all
            // subcategories that match this name (case-insensitive).
            $query->whereRaw('LOWER(subcategories.subcategory_name) = ?', [strtolower($subcategoryName)]);
        } elseif ($subcategoryId) {
            $query->where($table . '.subcategory_id', $subcategoryId);
        }

        // apply optional date-range filter (created_at) from the reusable daterange component
        $start = request()->get('start_date');
        $end = request()->get('end_date');
        if ($start || $end) {
            try {
                $startDt = $start ? Carbon::parse($start)->startOfDay()->toDateTimeString() : null;
                $endDt = $end ? Carbon::parse($end)->endOfDay()->toDateTimeString() : null;
                if ($startDt && $endDt) {
                    $query->whereBetween($table . '.created_at', [$startDt, $endDt]);
                } elseif ($startDt) {
                    $query->where($table . '.created_at', '>=', $startDt);
                } elseif ($endDt) {
                    $query->where($table . '.created_at', '<=', $endDt);
                }
            } catch (\Exception $e) {
                // ignore parse errors and do not apply date filter
            }
        }

        return $query->orderBy($table . '.created_at', 'desc');
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('product-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters(['stateSave' => true])
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> .
                                'tr' .
                                <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->lengthMenu([5, 10, 25, 50, 100])
            ->orderBy(7)
            ->parameters([
                'scrollX' => true,
                'autoWidth' => false,
                'scrollCollapse' => true,
                'fixedHeader' => false,
            ])
            ->buttons(
                Button::make('excel')
                    ->text('<i class="bi bi-file-earmark-excel-fill"></i> Excel'),
                Button::make('pdf')
                    ->text('<i class="bi bi-file-earmark-pdf-fill"></i> PDF'),
                Button::make('print')
                    ->text('<i class="bi bi-printer-fill"></i> Print'),
                Button::make('reset')
                    ->text('<i class="bi bi-x-circle"></i> Reset'),
                Button::make('reload')
                    ->text('<i class="bi bi-arrow-repeat"></i> Reload')
            );
    }

    protected function getColumns()
    {
        $table = (new Product())->getTable();

        return [
            Column::computed('product_image')
                ->title('Image')
                ->exportable(false)
                ->printable(false)
                ->className('text-center align-middle'),

            Column::make('category_name')
                ->name('categories.category_name')
                ->title('Brand')
                ->className('text-center align-middle'),

            Column::make('subcategory')
                ->name('subcategories.subcategory_name')
                ->title('Subcategory')
                ->className('text-center align-middle'),

            Column::make('product_code')
                ->title('Code')
                ->className('text-center align-middle'),

            Column::make('product_name')
                ->title('Product Name')
                ->className('text-center align-middle'),

            Column::make('product_unit')
                ->title('Unit')
                ->className('text-center align-middle'),

            Column::computed('product_cost')
                ->title('Purchase Rate (Net Rate)')
                ->className('text-center align-middle'),

            Column::computed('mrp')
                ->title('MRP')
                ->className('text-center align-middle'),

            Column::computed('list_price')
                ->title('List Price')
                ->className('text-center align-middle'),


            Column::computed('product_price')
                ->title('Sell Rate')
                ->className('text-center align-middle'),

            Column::computed('open_quantity')
                ->title('Open Qty')
                ->className('text-center align-middle'),

            Column::computed('purchase_quantity')
                ->title('Purchase Qty')
                ->className('text-center align-middle'),

            Column::computed('product_quantity')
                ->title('Overall Quantity')
                ->className('text-center align-middle'),

            Column::make('product_order_tax')
                ->title('Tax (%)')
                ->className('text-center align-middle'),

            Column::make('hsn')
                ->name($table . '.hsn')
                ->title('HSN')
                ->className('text-center align-middle'),

            Column::make('rack_no')
                ->title('Rack No')
                ->className('text-center align-middle'),

            Column::make('bin_no')
                ->title('Bin No')
                ->className('text-center align-middle'),

            Column::make('product_note')
                ->title('Compatibility')
                ->className('text-center align-middle'),

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->className('text-center align-middle'),

            Column::make('created_at')
                ->visible(false)
                ->exportable(false)
                ->printable(false)
        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename(): string
    {
        return 'Product_' . date('YmdHis');
    }

}
