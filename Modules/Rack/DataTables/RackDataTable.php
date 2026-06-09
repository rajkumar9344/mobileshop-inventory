<?php

namespace Modules\Rack\DataTables;

use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Modules\Rack\Entities\Rack;

class RackDataTable extends DataTable
{
    use \App\Traits\HasPdfExport;

    public function dataTable($query) {
        return datatables()
            ->query($query)
            ->addColumn('status', function ($data) {
                return view('rack::partials.status', compact('data'));
            })
            ->addColumn('action', function ($data) {
                return view('rack::partials.actions', compact('data'));
            });
    }

    public function query() {
        // Default ordering: newest records first
        return \DB::table('rack_master')->orderBy('created_at', 'desc');
    }

    public function html() {
        return $this->builder()
            ->setTableId('racks-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters(['stateSave' => true])
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> .
                                'tr' .
                                <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->orderBy(3, 'desc')
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
            Column::make('rack_id')
                ->title('Rack ID')
                ->className('text-center align-middle'),

            Column::make('rack_name')
                ->title('Rack Name')
                ->className('text-center align-middle'),

            Column::make('barcode')
                ->title('Barcode')
                ->className('text-center align-middle'),

            Column::computed('status')
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

    protected function filename(): string {
        return 'Rack_' . date('YmdHis');
    }

}