<?php

namespace Modules\SalesReceipt\DataTables;

use Modules\SalesReceipt\Entities\SalesReceipt;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SalesReceiptsDataTable extends DataTable
{
    use \App\Traits\HasPdfExport;

    public function dataTable($query) {
        // Use model scopes for settled/global search (keeps logic centralized)

        return datatables()
            ->eloquent($query)
            ->addColumn('customer', function ($data) {
                // Customer entity uses 'customer_name' field
                return optional($data->customer)->customer_name;
            })
            ->addColumn('area', function ($data) {
                return optional($data->customer)->area;
            })
            // enable server-side searching for computed 'customer' and 'area' columns
            ->filterColumn('customer', function($query, $keyword) {
                $query->whereHas('customer', function($q) use ($keyword) {
                    $q->where('customer_name', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('area', function($query, $keyword) {
                $query->whereHas('customer', function($q) use ($keyword) {
                    $q->where('area', 'like', "%{$keyword}%");
                });
            })
            // Allow searching by total amount (user types rupees, DB stores paise)
            ->filterColumn('total_amount_formatted', function($query, $keyword) {
                $k = trim($keyword);
                if ($k === '') return;
                // remove currency symbols, commas, spaces
                $clean = preg_replace('/[^0-9.\-]/', '', $k);
                if ($clean === '') return;
                $amount = floatval($clean);
                $paise = intval(round($amount * 100));
                // exact match by amount (paise)
                $query->where('total_amount', $paise)
                      // also allow partial digit matches against the paise integer (so "999" matches 9,999.98 -> 999998)
                      ->orWhereRaw('CAST(total_amount AS CHAR) LIKE ?', ["%{$clean}%"]);
            })

            // Allow searching by Settled column (accepts Yes/No, y/n, 1/0, true/false)
            ->filterColumn('settled', function($query, $keyword) {
                $k = strtolower(trim($keyword));
                if ($k === '') return;
                $yes = ['yes','y','1','true'];
                $no = ['no','n','0','false'];

                if (in_array($k, $yes, true)) {
                    $query->where(function($q) { $q->settledYes(); });
                } elseif (in_array($k, $no, true)) {
                    $query->where(function($q) { $q->settledNo(); });
                }
            })
            // global search across reference, date, customer name and area
            ->filter(function($query) {
                $search = request()->get('search');
                $term = null;
                if (is_array($search) && isset($search['value'])) {
                    $term = trim($search['value']);
                }
                if ($term) {
                    $query->applyGlobalSearch($term);
                }
            })
            ->addColumn('total_amount_formatted', function ($data) {
                return format_currency($data->total_amount);
            })
            ->addColumn('total_discount_formatted', function ($data) {
                return format_currency($data->total_discount);
            })
            ->addColumn('payment_mode', function ($data) {
                return $data->payment_mode ? $data->payment_mode : '-';
            })
            ->addColumn('cheque', function ($data) {
                // Only show amount when payment_mode is explicitly 'Cheque'
                $amt = ($data->payment_mode === 'Cheque') ? ($data->total_amount ?? 0) : 0;
                return format_currency($amt);
            })
            ->addColumn('settled', function ($data) {
                // Compute the Yes/No state, then render it as a coloured badge
                // (Yes = green, No = red).
                $state = (function ($data) {
                // Determine settled state.
                // Priority 1: if there are lines, infer settled state from allocated amounts
                // (payment_amount + discount_amount) so it matches the edit-page calculation.
                if (isset($data->lines) && $data->lines->isNotEmpty()) {
                    $allocated = $data->lines->sum(function($l){
                        return floatval(($l->payment_amount ?? 0));
                    });
                    $receiptAmt = floatval($data->total_amount ?? 0);
                    if ($receiptAmt > 0 && abs($receiptAmt - $allocated) < 0.01) return 'Yes';
                    return 'No';
                }

                // Priority 2: if this is a lineless receipt created for a Sale Return,
                // use stored `applied_to_customer` (paise) to determine whether the
                // full receipt amount was effectively applied. Convert total_amount
                // (rupees) to paise for comparison.
                if (!empty($data->sale_return_id)) {
                    $applied = intval($data->applied_to_customer ?? 0);
                    $totalPaise = intval(round(floatval($data->total_amount ?? 0) * 100));
                    if ($applied > 0 && $applied >= $totalPaise) return 'Yes';
                    return 'No';
                }

                // Priority 3: if this is a lineless receipt applied to opening balance,
                // use stored `applied_to_customer` (paise) to determine settlement.
                if (!empty($data->applied_to_customer) && empty($data->sale_return_id)) {
                    $applied = intval($data->applied_to_customer ?? 0);
                    $totalPaise = intval(round(floatval($data->total_amount ?? 0) * 100));
                    if ($applied > 0 && $applied >= $totalPaise) return 'Yes';
                    return 'No';
                }

                // Fallback: no lines and not a lineless receipt -> not settled
                return 'No';
                })($data);

                $color = $state === 'Yes' ? 'success' : 'danger';
                return '<span class="badge badge-' . $color . '">' . $state . '</span>';
            })
            ->editColumn('date', function ($data) {
                $d = $data->date ?? null;
                if (empty($d) || $d === '-' || $d === '0000-00-00') return '';
                return \Carbon\Carbon::parse($d)->format('d-m-Y');
            })
            ->addColumn('cash', function ($data) {
                $amt = ($data->payment_mode === 'Cash') ? ($data->total_amount ?? 0) : 0;
                return format_currency($amt);
            })
            ->addColumn('action', function ($data) {
                return view('salesreceipt::partials.actions', compact('data'));
            })
            ->rawColumns(['action', 'settled']);
    }

    public function query(SalesReceipt $model) {
        // eager-load lines so we can compute settled state in the DataTable
        $query = $model->newQuery()->with(['customer', 'lines']);

        $start = request()->get('start_date');
        $end = request()->get('end_date');
        $customer = request()->get('customer_id');
        $paymentMode = request()->get('payment_mode');

        if ($start && $end) {
            $query->whereBetween('date', [$start, $end]);
        } elseif ($start) {
            $query->whereDate('date', '>=', $start);
        } elseif ($end) {
            $query->whereDate('date', '<=', $end);
        }

        if ($customer) {
            $query->where('customer_id', $customer);
        }

        if ($paymentMode) {
            $query->where('payment_mode', $paymentMode);
        }

        return $query->orderBy('id', 'desc');
    }

    public function html() {
        return $this->builder()
            ->setTableId('sales-receipts-table')
            ->columns($this->getColumns())
            ->ajax([
                'url' => route('sales-receipts.index'),
                    'data' => 'function(d){ d.start_date = $("#filter-start-sales-receipts-table").val(); d.end_date = $("#filter-end-sales-receipts-table").val(); }'
            ])
            ->minifiedAjax()
            ->parameters(['stateSave' => true])
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> . 'tr' . <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->orderBy(1, 'desc')
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
            Column::make('reference')->title('Ref.No')->className('text-center align-middle'),
            Column::make('date')->title('Ref.Date')->className('text-center align-middle'),
            Column::computed('customer')->title('Name')->orderable(false)->className('text-center align-middle'),
            Column::computed('area')->title('Area')->orderable(false)->className('text-center align-middle'),
            Column::make('payment_mode')->title('Payment Mode')->className('text-center align-middle'),
            Column::computed('total_amount_formatted')->title('Receipt Amount')->orderable(false)->className('text-center align-middle'),
            Column::computed('settled')->title('Settled')->orderable(false)->className('text-center align-middle'),
            Column::computed('action')->exportable(false)->printable(false)->orderable(false)->className('text-center align-middle not-export'),
            // hide created_at from display, export and print (used internally only)
            Column::make('created_at')->visible(false)->exportable(false)->printable(false)
        ];
    }

    protected function filename(): string {
        return 'SalesReceipts_' . date('YmdHis');
    }
}
