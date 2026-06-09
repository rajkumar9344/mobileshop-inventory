<?php

namespace Modules\Product\DataTables;

use Modules\Product\Entities\Category;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class CategoryDataTable extends DataTable
{
    use \App\Traits\HasPdfExport;

    public function dataTable($query) {
        return datatables()
            ->eloquent($query)
            ->addColumn('status', function ($data) {
                return $data->status ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>';
            })
            ->addColumn('products_count', function ($data) {
                return $data->products_count ?? $data->products()->count();
            })
            ->addColumn('action', function ($data) {
                return view('product::categories.partials.category-actions', compact('data'));
            })
            ->rawColumns(['status', 'action']);
    }

    public function query(Category $model) {
        // Order by newest first
        return $model->newQuery()->withCount('products')->orderBy('created_at', 'desc');
    }

    public function html() {
        return $this->builder()
            ->setTableId('categories-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters(['stateSave' => true])
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> . 'tr' . <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            // Default order: created_at desc (newest first). created_at is the hidden column at index 4
            ->orderBy(4, 'desc')
            ->buttons(
                Button::make('excel')->text('<i class="bi bi-file-earmark-excel-fill"></i> Excel'),
                Button::make('pdf')->text('<i class="bi bi-file-earmark-pdf-fill"></i> PDF'),
                Button::make('print')->text('<i class="bi bi-printer-fill"></i> Print'),
                Button::make('reset')->text('<i class="bi bi-x-circle"></i> Reset'),
                Button::make('reload')->text('<i class="bi bi-arrow-repeat"></i> Reload')
            );
    }

    protected function getColumns() {
        return [
            Column::make('category_code')->title('Brand Code')->addClass('text-center'),
            Column::make('category_name')->title('Brand Name')->addClass('text-center'),
            Column::make('status')->title('Status')->addClass('text-center'),
            Column::make('products_count')->addClass('text-center'),
            Column::computed('action')->exportable(false)->printable(false)->addClass('text-center'),
            Column::make('created_at')->visible(false)->exportable(false)->printable(false),
        ];
    }

    protected function filename(): string {
        return 'Categories_' . date('YmdHis');
    }

}
