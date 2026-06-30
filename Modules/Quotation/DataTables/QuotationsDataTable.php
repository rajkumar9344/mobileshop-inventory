<?php

namespace Modules\Quotation\DataTables;

use Modules\Quotation\Entities\Quotation;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class QuotationsDataTable extends DataTable
{
    use \App\Traits\HasPdfExport;

    public function dataTable($query) {
        return datatables()
            ->eloquent($query)
            ->editColumn('customer_name', function ($data) {
                // Use the snapshot stored on the quotation row first (always available
                // even when the customer record is inactive or deleted).
                if (!empty($data->customer_name)) return $data->customer_name;
                // Fallback to relation for older records that have no snapshot.
                return $data->customer?->customer_name ?? '';
            })
            ->editColumn('total_amount', function ($data) {
                // Prefer overall_net_rate when present (frontend displays net rate there)
                $amount = $data->overall_net_rate ?? $data->total_amount;
                return format_currency($amount);
            })
            ->editColumn('reference', function ($data) {
                $ref = $data->reference;
                if ($data->status === 'Draft') {
                    $ref .= ' <span class="badge status-draft">Draft</span>';
                }
                return $ref;
            })
            ->editColumn('contact_phone', function ($data) {
                // Prefer contact_phone stored on the quotation (used for 'new' customers)
                if (!empty($data->contact_phone)) {
                    return $data->contact_phone;
                }

                // Fallback to customer relation (eager-loaded; null for inactive/deleted customers).
                return $data->customer?->customer_phone ?? '';
            })
            ->filterColumn('contact_phone', function($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('contact_phone', 'like', "%{$keyword}%")
                      ->orWhereHas('customer', function ($customerQuery) use ($keyword) {
                          $customerQuery->where('customer_phone', 'like', "%{$keyword}%");
                      });
                });
            })
            ->filterColumn('date', function($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('date', 'like', "%{$keyword}%")
                      ->orWhereRaw("DATE_FORMAT(date, '%d %b, %Y') like ?", ["%{$keyword}%"])
                      ->orWhereRaw("DATE_FORMAT(date, '%d-%m-%Y') like ?", ["%{$keyword}%"]);
                });
            })
            ->filterColumn('total_amount', function($query, $keyword) {
                $normalized = str_replace(',', '', preg_replace('/[^0-9.,-]/', '', $keyword));

                $query->where(function ($q) use ($normalized) {
                    if ($normalized !== '' && is_numeric($normalized)) {
                        $minorUnits = (int) round(((float) $normalized) * 100);
                        $q->where('overall_net_rate', $minorUnits)
                          ->orWhere('total_amount', $minorUnits);
                    }

                    if ($normalized !== '') {
                        $q->orWhereRaw("REPLACE(FORMAT(COALESCE(overall_net_rate, total_amount) / 100, 2), ',', '') like ?", ["%{$normalized}%"])
                          ->orWhereRaw("REPLACE(FORMAT(total_amount / 100, 2), ',', '') like ?", ["%{$normalized}%"]);
                    }
                });
            })
            ->addColumn('action', function ($data) {
                return view('quotation::partials.actions', compact('data'));
            })
            ->rawColumns(['reference', 'action']);
    }

    public function query(Quotation $model) {
        // eager-load customer to avoid lazy-loading violations when rendering columns
        return $model->newQuery()->with(['customer', 'lastEmailLog'])->orderBy('id', 'desc');
    }

    public function html() {
        return $this->builder()
            ->setTableId('sales-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters(['stateSave' => true])
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> .
                                'tr' .
                                <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->lengthMenu([5, 10, 25, 50, 100])
            ->orderBy(4)
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
            Column::make('date')
                ->className('text-center align-middle'),

            Column::make('reference')
                ->title('Reference')
                ->className('text-center align-middle'),

            Column::make('customer_name')
                ->title('Customer')
                ->className('text-center align-middle'),

            Column::make('contact_phone')
                ->title('Phone')
                ->className('text-center align-middle'),

            Column::make('total_amount')
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
        return 'Quotations_' . date('YmdHis');
    }

}
