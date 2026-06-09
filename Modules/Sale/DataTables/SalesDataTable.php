<?php

namespace Modules\Sale\DataTables;

use Modules\Sale\Entities\Sale;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SalesDataTable extends DataTable
{
    use \App\Traits\HasPdfExport;

    public function dataTable($query) {
        $dt = datatables()
            ->eloquent($query)
            ->addColumn('payment_method', function ($data) {
                return $data->payment_method ?? ($data->payment_mode ?? '');
            })
            ->addColumn('overall_tax_amount', function ($data) {
                return format_currency($data->overall_tax_amount ?? $data->tax_amount ?? 0);
            })
            ->addColumn('overall_amount', function ($data) {
                return format_currency($data->overall_amount ?: $data->total_amount ?: 0);
            })
            ->addColumn('paid_amount', function ($data) {
                return format_currency($data->paid_amount ?? 0);
            })
            ->addColumn('discount_amount', function ($data) {
                return format_currency($data->discount_amount ?? 0);
            })
            ->addColumn('due_amount', function ($data) {
                // If due_amount is negative, show as Advance (extra paid) with positive value
                if ($data->due_amount < 0) {
                    $advance = abs($data->due_amount);
                    return '<span class="text-success">Advance ' . format_currency($advance) . '</span>';
                }

                return format_currency($data->due_amount);
            })
            ->editColumn('date', function ($data) {
                return $data->date ? \Carbon\Carbon::parse($data->date)->format('d-m-Y') : '';
            })
            ->addColumn('reference', function ($data) {
                $reference = $data->reference;
                if ($data->status === 'Draft') {
                    $reference .= ' <span class="badge status-draft">Draft</span>';
                }
                return $reference;
            })
            ->addColumn('status', function ($data) {
                // Always show payment status badge
                return $data->status_badge;
            })
            ->addColumn('action', function ($data) {
                return view('sale::partials.actions', compact('data'));
            })
            ->rawColumns(['action', 'due_amount', 'reference', 'status']);

        // Server-side global search: include payment method in search
        $searchValue = $this->request()->get('search')['value'] ?? null;
        if ($searchValue) {
            $dt->filter(function ($q) use ($searchValue) {
                $q->where(function ($sub) use ($searchValue) {
                    $sub->orWhere('reference', 'like', "%{$searchValue}%")
                        ->orWhere('customer_name', 'like', "%{$searchValue}%")
                        ->orWhere('area', 'like', "%{$searchValue}%")
                        ->orWhere('payment_method', 'like', "%{$searchValue}%");
                });
            });
        }

        return $dt;
    }

    public function query(Sale $model) {
        // eager load count to avoid N+1 when computing status
        // Eager load customer, last email log and payments count to avoid N+1 during rendering
        $query = $model->newQuery()
            ->with('customer')
            ->withCount('salePayments')
            ->with('lastEmailLog');

        // When listing sales for a specific customer (customer detail / export),
        // exclude sales in Draft status so aggregates and exports match customer summaries.
        $customer = request()->get('customer_id');
        if ($customer) {
            $query->where(function($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'Draft');
            });
        }

        $year = request()->get('year');
        $month = request()->get('month');

        // Support both the legacy year/month filters and the new date range
        $start = request()->get('start_date');
        $end = request()->get('end_date');

        if ($start || $end) {
            // If either start or end is provided, build a whereDate range
            // Normalize dates and apply bounds. If only one bound is provided,
            // use an open-ended query on the other side.
            try {
                if ($start && $end) {
                    $query->whereBetween('date', [
                        date('Y-m-d', strtotime($start)),
                        date('Y-m-d', strtotime($end))
                    ]);
                } elseif ($start) {
                    $query->whereDate('date', '>=', date('Y-m-d', strtotime($start)));
                } elseif ($end) {
                    $query->whereDate('date', '<=', date('Y-m-d', strtotime($end)));
                }
            } catch (\Exception $e) {
                // If parsing fails, fall back to no date filter
            }
        } else {
            if ($year) {
                $query->whereYear('date', $year);
            }

            if ($month) {
                $query->whereMonth('date', str_pad($month, 2, '0', STR_PAD_LEFT));
            }
        }

        // Product filter: allow filtering sales which include a given product id
        $productId = request()->get('product_id');
        $productText = request()->get('product_text');
        if ($productId || $productText) {
            $query->whereExists(function($q) use ($productId, $productText) {
                $q->select(\DB::raw(1))
                    ->from('sales_details as sd')
                    ->whereRaw('sd.sale_id = sales.id');

                if ($productId) {
                    $q->where('sd.product_id', $productId);
                } elseif ($productText) {
                    $q->where(function($qq) use ($productText) {
                        $qq->where('sd.product_code', $productText)
                           ->orWhere('sd.product_name', 'like', "%{$productText}%");
                    });
                }
            });
        }

        return $query->orderBy('id', 'desc');
    }

    public function html() {
        return $this->builder()
            ->setTableId('sales-table')
            ->columns($this->getColumns())
            ->ajax(['data' => 'function(d){ d.start_date = $("#filter-start-sales-table").val(); d.end_date = $("#filter-end-sales-table").val(); d.product_id = $("#product-search").val(); d.product_text = $("#product-search").val(); }'])
            ->parameters(['stateSave' => true])
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> .
                                'tr' .
                                <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->lengthMenu([5, 10, 25, 50, 100])
            ->buttons(
                // Excel export — server-side so ALL rows are included
                Button::make('excel')
                    ->text('<i class="bi bi-file-earmark-excel-fill"></i> Excel'),

                // PDF export — server-side so ALL rows (not just current page) are included
                Button::make('pdf')
                    ->text('<i class="bi bi-file-earmark-pdf-fill"></i> PDF'),

                // Print export
                Button::raw([
                        'extend' => 'print',
                        'exportOptions' => ['columns' => ':visible:not(.not-export)'],
                        'title' => ' ',
                        'messageTop' => ' ',
                        'messageBottom' => ' ',
                        'customize' => "function (win) {\n                            var style = win.document.createElement('style');\n                            style.type = 'text/css';\n                            style.innerHTML = '.dt-print-header, .dt-print-title, .dt-print-message { display: none !important; }';\n                            win.document.head.appendChild(style);\n                            var header = win.document.querySelector('.dt-print-header');\n                            if (header) { header.remove(); }\n                            var message = win.document.querySelector('.dt-print-message');\n                            if (message) { message.remove(); }\n                            var title = win.document.querySelector('.dt-print-title');\n                            if (title) { title.remove(); }\n                            var genericTitle = win.document.querySelector('h1');\n                            if (genericTitle && genericTitle.classList.contains('dt-print-title')) { genericTitle.remove(); }\n                        }"
                    ])
                    ->text('<i class="bi bi-printer-fill"></i> Print'),

                Button::make('reset')
                    ->text('<i class="bi bi-x-circle"></i> Reset'),
                Button::make('reload')
                    ->text('<i class="bi bi-arrow-repeat"></i> Reload')
            );
    }

    protected function getColumns()
    {
        return [
            Column::make('reference')
                ->title('Bill No')
                ->className('text-center align-middle'),

            Column::make('date')
                ->title('Bill Ref Date')
                ->className('text-center align-middle'),

            Column::make('customer_name')
                ->title('Customer Name')
                ->className('text-center align-middle'),

            Column::make('area')
                ->title('Area')
                ->className('text-center align-middle'),


            Column::computed('overall_tax_amount')
                ->title('TAX Amount')
                ->className('text-end align-middle'),

            Column::computed('overall_amount')
                ->title('Overall Amount')
                ->className('text-end align-middle'),

            Column::make('paid_amount')
                ->title('Received Amount')
                ->className('text-end align-middle'),

            Column::computed('discount_amount')
                ->title('Discount Amount')
                ->className('text-end align-middle'),

            Column::computed('due_amount')
                ->title('Balance Amount')
                ->className('text-end align-middle'),

            Column::make('payment_method')
                ->title('Payment Method')
                ->className('text-center align-middle'),

            Column::computed('status')
                ->title('Payment Status')
                ->className('text-center align-middle'),

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->className('text-center align-middle not-export')
        ];
    }

    protected function filename(): string
    {
        return 'Sales_' . date('YmdHis');
    }

}
