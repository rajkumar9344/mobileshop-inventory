<?php

namespace Modules\PurchasesReturn\DataTables;

use Modules\PurchasesReturn\Entities\PurchaseReturn;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class PurchaseReturnsDataTable extends DataTable
{
    use \App\Traits\HasPdfExport;

    public function dataTable($query) {
        return datatables()
            ->eloquent($query)
            ->addColumn('total_amount', function ($data) {
                return format_currency($data->total_amount);
            })
            ->addColumn('area', function ($data) {
                return $data->supplier ? ($data->supplier->area ?? '-') : '-';
            })
            ->addColumn('action', function ($data) {
                return view('purchasesreturn::partials.actions', compact('data'));
            })
            ->rawColumns(['action']);
    }

    public function query(PurchaseReturn $model) {
        return $model->newQuery()->with('supplier');
    }

    public function html() {
        return $this->builder()
            ->setTableId('purchase-returns-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters(['stateSave' => true])
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> .
                                'tr' .
                                <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->orderBy(5)
            ->lengthMenu([5, 10, 25, 50, 100])
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
            Column::make('reference')
                ->className('text-center align-middle'),

            Column::make('supplier_name')
                ->title('Supplier')
                ->className('text-center align-middle'),

            Column::computed('area')
                ->title('Area')
                ->orderable(false)
                ->className('text-center align-middle'),

            Column::computed('total_amount')
                ->orderable(false)
                ->className('text-center align-middle'),

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->className('text-center align-middle'),

            Column::make('created_at')
                ->visible(false)
                ->exportable(false)
                ->printable(false)
        ];
    }

    protected function filename(): string {
        return 'PurchaseReturns_' . date('YmdHis');
    }

}
