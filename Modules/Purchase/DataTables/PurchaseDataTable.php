<?php

namespace Modules\Purchase\DataTables;

use Modules\Purchase\Entities\Purchase;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class PurchaseDataTable extends DataTable
{
    use \App\Traits\HasPdfExport;

    public function dataTable($query) {
        return datatables()
            ->eloquent($query)
            ->addColumn('reference', function ($data) {
                $reference = $data->reference;
                if ($data->status === 'Draft') {
                    $reference .= ' <span class="badge badge-warning">Draft</span>';
                }
                return $reference;
            })
            ->editColumn('date', function ($data) {
                return $data->date ? \Carbon\Carbon::parse($data->date)->format('d-m-Y') : '';
            })
            ->addColumn('invoice_date_formatted', function ($data) {
                return $data->invoice_date ? \Carbon\Carbon::parse($data->invoice_date)->format('d-m-Y') : '';
            })
            ->addColumn('overall_tax_amount', function ($data) {
                return format_currency($data->overall_tax_amount ?? $data->tax_amount ?? 0);
            })
            ->addColumn('overall_bill_amount', function ($data) {
                return format_currency($data->overall_net_rate ?? $data->total_amount ?? 0);
            })
            ->addColumn('paid_amount', function ($data) {
                return format_currency($data->paid_amount ?? 0);
            })
            ->addColumn('balance_amount', function ($data) {
                return format_currency($data->due_amount ?? 0);
            })
            ->addColumn('payment_status', function ($data) {
                $status = $data->payment_status ?? 'Unpaid';
                $class = match($status) {
                    'Paid'    => 'badge-success',
                    'Partial' => 'badge-warning',
                    default   => 'badge-danger',
                };
                return '<span class="badge ' . $class . '">' . $status . '</span>';
            })
            ->addColumn('status', function ($data) {
                return $data->status_badge;
            })
            ->addColumn('action', function ($data) {
                return view('purchase::partials.actions', compact('data'));
            })
            ->rawColumns(['reference', 'payment_status', 'status', 'action']);
    }

    public function query(Purchase $model) {
        // Eager load supplier, last email log and payments count to avoid lazy-loading during rendering
        $query = $model->newQuery()
            ->with('supplier')
            ->withCount('purchasePayments')
            ->with('lastEmailLog')
            ->orderBy('id', 'desc');

        $year = request()->get('year');
        $month = request()->get('month');
        $day = request()->get('day');
        $start = request()->get('start_date');
        $end = request()->get('end_date');

        // Prefer explicit date range if provided (start_date & end_date in YYYY-MM-DD)
        if ($start && $end) {
            // ensure correct ordering
            try {
                $s = \Carbon\Carbon::parse($start)->startOfDay()->toDateString();
                $e = \Carbon\Carbon::parse($end)->endOfDay()->toDateString();
                $query->whereBetween('date', [$s, $e]);
            } catch (\Exception $ex) {
                // invalid dates - fall back to legacy year/month/day
            }
        } else {
            if ($year) {
                $query->whereYear('date', $year);
            }

            if ($month) {
                $query->whereMonth('date', str_pad($month, 2, '0', STR_PAD_LEFT));
            }

            if ($day) {
                $query->whereDay('date', str_pad($day, 2, '0', STR_PAD_LEFT));
            }
        }

        // If a supplier filter is present (supplier detail page / export), exclude Draft purchases
        $supplier = request()->get('supplier_id');
        if ($supplier) {
            $query->where(function($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'Draft');
            });
        }

        // Product filter: when supplied, only return purchases that have matching purchase_details
        $productId = request()->get('product_id');
        $productText = request()->get('product_text');
        if ($productId || $productText) {
            $query->whereExists(function($q) use ($productId, $productText) {
                $q->select(\DB::raw(1))
                  ->from('purchase_details as pd')
                  ->whereRaw('pd.purchase_id = purchases.id');

                if ($productId) {
                    $q->where('pd.product_id', $productId);
                } elseif ($productText) {
                    $q->where(function($q2) use ($productText) {
                        $q2->where('pd.product_code', $productText)
                           ->orWhere('pd.product_name', 'like', '%' . $productText . '%');
                    });
                }
            });
        }

        return $query->orderBy('id', 'desc');
    }

    public function html() {
        return $this->builder()
            ->setTableId('purchases-table')
            ->columns($this->getColumns())
            ->ajax(['data' => 'function(d){ d.year = $("#filter-year").val(); d.month = $("#filter-month").val(); d.day = $("#filter-day").val(); d.product_id = $("#product-search").val(); d.product_text = $("#product-search").val(); }'])
            ->parameters(['stateSave' => true])
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> .
                                'tr' .
                                <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->lengthMenu([5, 10, 25, 50, 100])
            ->orderBy(0, 'desc')
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
            Column::computed('reference')
                ->title('Bill No')
                ->className('text-center align-middle'),

            Column::make('date')
                ->title('Bill Ref Date')
                ->className('text-center align-middle'),

            Column::make('invoice_no')
                ->title('Invoice No')
                ->className('text-center align-middle'),

            Column::computed('invoice_date_formatted')
                ->title('Invoice Date')
                ->className('text-center align-middle'),

            Column::make('supplier_name')
                ->title('Supplier Name')
                ->className('text-center align-middle'),

            Column::make('area')
                ->title('Area')
                ->className('text-center align-middle'),

            Column::computed('overall_tax_amount')
                ->title('Tax Amount')
                ->className('text-end align-middle'),

            Column::computed('overall_bill_amount')
                ->title('Bill Amount')
                ->className('text-end align-middle'),

            Column::computed('paid_amount')
                ->title('Paid')
                ->className('text-end align-middle'),

            Column::computed('balance_amount')
                ->title('Balance')
                ->className('text-end align-middle'),

            Column::computed('payment_status')
                ->title('Payment')
                ->className('text-center align-middle'),

            Column::computed('status')
                ->title('Status')
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
        return 'Purchase_' . date('YmdHis');
    }

}
