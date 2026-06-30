<?php

namespace Modules\Product\DataTables;

use Modules\Product\Entities\Subcategory;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class SubcategoriesDataTable extends DataTable
{
    use \App\Traits\HasPdfExport;

    public function dataTable($query) {
        return datatables()
            ->eloquent($query)
            ->addColumn('status', function ($data) {
                return $data->status ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>';
            })
            ->addColumn('category_code', function ($data) {
                return $data->category->category_code;
            })
            ->addColumn('category_name', function ($data) {
                return $data->category->category_name;
            })
            ->filterColumn('category_code', function($query, $keyword) {
                $query->whereHas('category', function($q) use ($keyword) {
                    $q->where('category_code', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('category_name', function($query, $keyword) {
                $query->whereHas('category', function($q) use ($keyword) {
                    $q->where('category_name', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('action', function ($data) {
                return view('product::categories.partials.actions', compact('data'));
            })
            ->rawColumns(['status', 'action'])
            ->filterColumn('products_count', function($query, $keyword) {
                $query->having('products_count', '=', (int)$keyword);
            });
    }

    public function query(Subcategory $model) {
        // Order by newest first
        return $model->newQuery()->with('category')->withCount('products')->orderBy('created_at', 'desc');
    }

    public function html() {
        return $this->builder()
            ->setTableId('product_subcategories-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters(['stateSave' => true])
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> .
                                'tr' .
                                <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            // Default order: created_at desc (newest first). created_at is the hidden column at index 6
            ->orderBy(6, 'desc')
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

    protected function getColumns() {
        return [
            Column::make('category_code')
                ->title('Brand Code')
                ->addClass('text-center'),

            Column::make('category_name')
                ->title('Brand Name')
                ->addClass('text-center'),

            Column::make('subcategory_name')
                ->addClass('text-center'),

            Column::make('status')->title('Status')->addClass('text-center'),

            Column::make('products_count')
                ->addClass('text-center'),

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->addClass('text-center'),

            Column::make('created_at')
                ->visible(false)
                ->exportable(false)
                ->printable(false)
        ];
    }

    protected function filename(): string {
        return 'Subcategories_' . date('YmdHis');
    }

}
