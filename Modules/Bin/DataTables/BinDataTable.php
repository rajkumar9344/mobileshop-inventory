<?php

namespace Modules\Bin\DataTables;

use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Modules\Bin\Entities\Bin;

class BinDataTable extends DataTable
{
    use \App\Traits\HasPdfExport;

    public function dataTable($query) {
        return datatables()
            ->eloquent($query)
            ->addColumn('rack_name', function ($data) {
                return $data->rack->rack_name . ' (' . $data->rack->rack_id . ')';
            })
            ->filterColumn('rack_name', function($query, $keyword) {
                // allow search against rack name or rack id
                $query->whereHas('rack', function($q) use ($keyword) {
                    $q->where('rack_name', 'like', "%{$keyword}%")
                      ->orWhere('rack_id', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('status', function ($data) {
                return view('bin::partials.status', compact('data'));
            })
            ->addColumn('action', function ($data) {
                return view('bin::partials.actions', compact('data'));
            });
    }

    public function query(Bin $model) {
        // eager load rack relation; search filter uses whereHas
        return $model->newQuery()->with('rack')->orderBy('created_at', 'desc');
    }

    public function html() {
        return $this->builder()
            ->setTableId('bins-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters(['stateSave' => true])
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> .
                                'tr' .
                                <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
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
            Column::make('rack_name')
                ->title('Rack')
                ->className('text-center align-middle'),

            Column::make('bin_id')
                ->title('Bin ID')
                ->className('text-center align-middle'),

            Column::make('bin_name')
                ->title('Bin Name')
                ->className('text-center align-middle'),

            Column::make('capacity')
                ->title('Capacity')
                ->className('text-center align-middle'),

            Column::computed('status')
                ->className('text-center align-middle'),

            Column::make('barcode')
                ->title('Barcode')
                ->className('text-center align-middle'),

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width('120px')
                ->className('text-center align-middle dt-action-column'),

            Column::make('created_at')
                ->visible(false)
                ->exportable(false)
                ->printable(false)
        ];
    }

    protected function filename(): string {
        return 'Bins_' . date('YmdHis');
    }

}