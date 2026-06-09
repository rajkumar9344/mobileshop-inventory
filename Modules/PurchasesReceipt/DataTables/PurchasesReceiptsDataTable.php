<?php

namespace Modules\PurchasesReceipt\DataTables;

use Modules\PurchasesReceipt\Entities\PurchasesReceipt;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class PurchasesReceiptsDataTable extends DataTable
{
    use \App\Traits\HasPdfExport;

    public function dataTable($query) {
        return datatables()
            ->eloquent($query)
            ->addColumn('supplier', function ($data) {
                // Supplier entity uses 'supplier_name' field
                return optional($data->supplier)->supplier_name;
            })
            ->addColumn('area', function ($data) {
                return optional($data->supplier)->area;
            })
            // enable server-side searching for computed 'supplier' and 'area' columns
            ->filterColumn('supplier', function($query, $keyword) {
                $query->whereHas('supplier', function($q) use ($keyword) {
                    $q->where('supplier_name', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('area', function($query, $keyword) {
                $query->whereHas('supplier', function($q) use ($keyword) {
                    $q->where('area', 'like', "%{$keyword}%");
                });
            })
            // Allow searching by total amount (user types rupees, DB stores paise)
            ->filterColumn('total_amount_formatted', function($query, $keyword) {
                $k = trim($keyword);
                if ($k === '') return;
                $clean = preg_replace('/[^0-9.\-]/', '', $k);
                if ($clean === '') return;
                $amount = floatval($clean);
                $paise = intval(round($amount * 100));
                $query->where('total_amount', $paise)
                      ->orWhereRaw('CAST(total_amount AS CHAR) LIKE ?', ["%{$clean}%"]);
            })

            // Settled filter uses model scopes to keep logic centralized
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
            // global search across reference, date, supplier name and area
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
                // Infer settled state from allocated amounts (payment + discount) when lines exist.
                $lines = $data->lines ?? collect();
                // Only synthesize an opening-balance line when there are no real lines.
                if ($data->applied_to_supplier > 0 && (empty($data->lines) || $data->lines->isEmpty())) {
                    $applied = $data->applied_to_supplier / 100;
                    $syntheticLine = new \stdClass();
                    $syntheticLine->bill_ref = 'Opening Balance';
                    $syntheticLine->bill_date = '-';
                    $syntheticLine->bill_amount = $data->total_amount;
                    $syntheticLine->paid_before = 0;
                    $syntheticLine->balance_before = $data->supplier->opening_balance ?? 0;
                    $syntheticLine->payment_amount = $applied;
                    $syntheticLine->discount_amount = 0;
                    $syntheticLine->final_balance = $data->total_amount - $applied;
                    $syntheticLine->purchase_id = null;
                    // Determine settled based on integer paise comparison with 1 paise tolerance
                    $appliedPaise = intval($data->applied_to_supplier ?? 0);
                    $receiptPaise = intval(round(floatval($data->total_amount ?? 0) * 100));
                    $syntheticLine->is_settled = abs($appliedPaise - $receiptPaise) <= 1;
                    $lines->push($syntheticLine);
                }

                if ($lines->isNotEmpty()) {
                    // allocation now includes both payment and discount amounts
                    $allocated = $lines->sum(function($l){
                        return floatval(($l->payment_amount ?? 0)) + floatval(($l->discount_amount ?? 0));
                    });
                    $receiptAmt = floatval($data->total_amount ?? 0);
                    if ($receiptAmt > 0 && abs($receiptAmt - $allocated) < 0.01) return 'Yes';
                    return 'No';
                }

                // For lineless receipts applied to opening balance, check applied_to_supplier
                if (!empty($data->applied_to_supplier)) {
                    $applied = intval($data->applied_to_supplier ?? 0);
                    $totalPaise = intval(round(floatval($data->total_amount ?? 0) * 100));
                    if ($applied > 0 && abs($applied - $totalPaise) <= 1) return 'Yes';
                    return 'No';
                }

                return 'No';
            })
            ->editColumn('date', function ($data) {
                return $data->date ? \Carbon\Carbon::parse($data->date)->format('d-m-Y') : '';
            })
            ->addColumn('cash', function ($data) {
                $amt = ($data->payment_mode === 'Cash') ? ($data->total_amount ?? 0) : 0;
                return format_currency($amt);
            })
            ->addColumn('action', function ($data) {
                return view('purchasesreceipt::partials.actions', compact('data'));
            })
            ->rawColumns(['action']);
    }

    public function query(PurchasesReceipt $model) {
        // eager-load lines so we can compute settled state in the DataTable
        $query = $model->newQuery()->with(['supplier', 'lines']);

        $start = request()->get('start_date');
        $end = request()->get('end_date');
        $supplier = request()->get('supplier_id');
        $paymentMode = request()->get('payment_mode');

        if ($start && $end) {
            $query->whereBetween('date', [$start, $end]);
        } elseif ($start) {
            $query->whereDate('date', '>=', $start);
        } elseif ($end) {
            $query->whereDate('date', '<=', $end);
        }

        if ($supplier) {
            $query->where('supplier_id', $supplier);
        }

        if ($paymentMode) {
            $query->where('payment_mode', $paymentMode);
        }

        return $query->orderBy('id', 'desc');
    }

    public function html() {
        return $this->builder()
            ->setTableId('purchases-receipts-table')
            ->columns($this->getColumns())
                ->ajax([
                    'url' => route('purchases-receipts.index'),
                    'data' => 'function(d){ d.start_date = $("#filter-start-purchases-receipts-table").val(); d.end_date = $("#filter-end-purchases-receipts-table").val(); d.supplier_id = $("#filter-supplier").val(); d.payment_mode = $("#filter-payment-mode").val(); }'
                ])
            ->minifiedAjax()
            ->parameters(['stateSave' => true])
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> . 'tr' . <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->orderBy(2)
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
            Column::computed('supplier')->title('Name')->className('text-center align-middle'),
            Column::computed('area')->title('Area')->className('text-center align-middle'),
            Column::make('payment_mode')->title('Payment Mode')->className('text-center align-middle'),
            Column::computed('total_amount_formatted')->title('Total')->className('text-center align-middle'),
                Column::computed('settled')->title('Settled')->className('text-center align-middle'),
            Column::computed('action')->exportable(false)->printable(false)->className('text-center align-middle'),
            // hide created_at from display, export and print (used internally only)
            Column::make('created_at')->visible(false)->exportable(false)->printable(false)
        ];
    }

    protected function filename(): string {
        return 'PurchasesReceipts_' . date('YmdHis');
    }

}