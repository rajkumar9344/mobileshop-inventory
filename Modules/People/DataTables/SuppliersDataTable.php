<?php

namespace Modules\People\DataTables;

use Illuminate\Support\Facades\DB;
use Modules\People\Entities\Supplier;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;
use App\Services\QueryFilters;

class SuppliersDataTable extends DataTable
{
    use \App\Traits\HasPdfExport;

    public function dataTable($query) {
        // Compute server-side summary based on purchases date (if provided)
        $start = $this->request()->get('start_date');
        $end = $this->request()->get('end_date');

        $summaryQuery = DB::table('suppliers')
            ->leftJoin('purchases', 'purchases.supplier_id', '=', 'suppliers.id');

        // Apply date filters to suppliers.created_at (filter by supplier creation)
        // and global search to align summary with table
        QueryFilters::applyDateFilters($summaryQuery, $start, $end, null, null, 'suppliers.created_at');
        $searchValue = $this->request()->get('search')['value'] ?? null;
        QueryFilters::applyGlobalSearch($summaryQuery, $searchValue, 'suppliers.supplier_name', 'suppliers.area');

        $summary = $summaryQuery->select(
            DB::raw('COUNT(DISTINCT suppliers.id) as suppliers_count'),
            // Exclude purchases with status = "Draft"
            DB::raw("COALESCE(SUM(CASE WHEN purchases.status IS NULL OR purchases.status != 'Draft' THEN purchases.total_amount ELSE 0 END),0) as overall_total"),
            DB::raw("COALESCE(SUM(CASE WHEN purchases.status IS NULL OR purchases.status != 'Draft' THEN purchases.paid_amount ELSE 0 END),0) as overall_paid"),
            DB::raw("COALESCE(SUM(CASE WHEN purchases.status IS NULL OR purchases.status != 'Draft' THEN purchases.discount_amount ELSE 0 END),0) as overall_discount"),
            DB::raw("COALESCE(SUM(CASE WHEN purchases.status IS NULL OR purchases.status != 'Draft' THEN purchases.total_amount ELSE 0 END),0) - COALESCE(SUM(CASE WHEN purchases.status IS NULL OR purchases.status != 'Draft' THEN purchases.paid_amount ELSE 0 END),0) - COALESCE(SUM(CASE WHEN purchases.status IS NULL OR purchases.status != 'Draft' THEN purchases.discount_amount ELSE 0 END),0) as overall_balance"),
            DB::raw('COALESCE(SUM(suppliers.excess_amount),0) as overall_excess')
        )->first();

        // Sum open_balance for ALL suppliers in the filtered list (the list left-joins
        // purchases, so suppliers without any purchase are still shown). Restricting this
        // to suppliers-with-purchases made the total understate the real open balance.
        $overall_open_balance_q = DB::table('suppliers as s');

        // apply created_at range on suppliers (alias `s`)
        QueryFilters::applyDateFilters($overall_open_balance_q, $start, $end, null, null, 's.created_at');

        QueryFilters::applyGlobalSearch($overall_open_balance_q, $searchValue, 's.supplier_name', 's.area');

        $overall_open_balance = $overall_open_balance_q->sum('s.open_balance');

        // Amounts paid against Open Balance via receipts, for the filtered suppliers (paise)
        $overall_opening_paid_q = DB::table('purchases_receipts as pr')
            ->join('suppliers as s', 's.id', '=', 'pr.supplier_id');
        QueryFilters::applyDateFilters($overall_opening_paid_q, $start, $end, null, null, 's.created_at');
        QueryFilters::applyGlobalSearch($overall_opening_paid_q, $searchValue, 's.supplier_name', 's.area');
        $overall_opening_paid = $overall_opening_paid_q->sum('pr.applied_to_supplier');

        // Total Balance = Open Balance (rupees) + Bill Balance (paise -> rupees)
        $overall_total_balance = ($overall_open_balance ?? 0) + (($summary->overall_balance ?? 0) / 100);

        $dt = datatables()
            ->eloquent($query)
            ->addColumn('total_amount', function($data){
                return format_currency( ($data->total_amount ?? 0) / 100 );
            })
            ->addColumn('paid_amount', function($data){
                // Paid = bill payments (purchases.paid_amount) + amounts applied to
                // the supplier's Open Balance via receipts. Both are stored in paise.
                return format_currency( (($data->paid_amount ?? 0) + ($data->opening_paid ?? 0)) / 100 );
            })
            ->addColumn('discount_amount', function($data){
                return format_currency( ($data->discount_amount ?? 0) / 100 );
            })
            ->addColumn('balance_amount', function($data){
                return format_currency( ($data->balance_amount ?? 0) / 100 );
            })
            ->addColumn('open_balance', function($data){
                // `open_balance` is stored on `suppliers` in major units (rupees)
                return format_currency( ($data->open_balance ?? 0) );
            })
            ->addColumn('total_balance', function($data){
                // Total Balance = Open Balance (rupees) + Bill Balance (paise -> rupees)
                return format_currency( ($data->open_balance ?? 0) + (($data->balance_amount ?? 0) / 100) );
            })
            ->addColumn('excess_amount', function($data){
                // `excess_amount` is stored on `suppliers` (rupees), display formatted currency
                return format_currency( ($data->excess_amount ?? 0) );
            })
            ->addColumn('action', function ($data) {
                return view('people::suppliers.partials.actions', compact('data'));
            })
            ->with([
                'summary' => [
                    'suppliers_count' => $summary->suppliers_count ?? 0,
                    'overall_total' => format_currency( ($summary->overall_total ?? 0) / 100 ),
                    'overall_paid' => format_currency( (($summary->overall_paid ?? 0) + ($overall_opening_paid ?? 0)) / 100 ),
                    'overall_discount' => format_currency( ($summary->overall_discount ?? 0) / 100 ),
                    'overall_balance' => format_currency( ($summary->overall_balance ?? 0) / 100 ),
                    'overall_open_balance' => format_currency( ($overall_open_balance ?? 0) ),
                    'overall_total_balance' => format_currency( $overall_total_balance ),
                    'overall_excess' => format_currency( ($summary->overall_excess ?? 0) ),
                ]
            ]);

        // Server-side global search: ensure search term matches supplier name or area
        $searchValue = $this->request()->get('search')['value'] ?? null;
        if ($searchValue) {
            $dt->filter(function ($q) use ($searchValue) {
                $q->where(function ($sub) use ($searchValue) {
                    $sub->where('suppliers.supplier_name', 'like', "%{$searchValue}%")
                        ->orWhere('suppliers.area', 'like', "%{$searchValue}%");
                });
            });
        }

        return $dt;
    }

    public function query(Supplier $model) {
        // Explicitly select supplier columns and aggregate purchases so the
        // computed columns (total_amount, paid_amount, discount_amount, balance_amount)
        // are available in the DataTable rows.
        $supplierCols = [
            'suppliers.id', 'suppliers.supplier_name', 'suppliers.supplier_code', 'suppliers.area',
            'suppliers.supplier_email', 'suppliers.supplier_phone', 'suppliers.city', 'suppliers.state',
            'suppliers.address', 'suppliers.open_balance', 'suppliers.excess_amount',
            'suppliers.credit_limit', 'suppliers.tax_percent', 'suppliers.due_days',
            'suppliers.status', 'suppliers.remarks',
            'suppliers.created_at', 'suppliers.updated_at'
        ];

        $query = $model->newQuery()
            ->leftJoin('purchases', 'purchases.supplier_id', '=', 'suppliers.id')
            ->select(array_merge($supplierCols, [
                DB::raw("COALESCE(SUM(CASE WHEN purchases.status IS NULL OR purchases.status != 'Draft' THEN purchases.total_amount ELSE 0 END), 0) as total_amount"),
                DB::raw("COALESCE(SUM(CASE WHEN purchases.status IS NULL OR purchases.status != 'Draft' THEN purchases.paid_amount ELSE 0 END), 0) as paid_amount"),
                DB::raw("COALESCE(SUM(CASE WHEN purchases.status IS NULL OR purchases.status != 'Draft' THEN purchases.discount_amount ELSE 0 END), 0) as discount_amount"),
                DB::raw("(COALESCE(SUM(CASE WHEN purchases.status IS NULL OR purchases.status != 'Draft' THEN purchases.total_amount ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN purchases.status IS NULL OR purchases.status != 'Draft' THEN purchases.paid_amount ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN purchases.status IS NULL OR purchases.status != 'Draft' THEN purchases.discount_amount ELSE 0 END), 0)) as balance_amount"),
                // Amounts paid to the supplier against their Open Balance via receipts (paise)
                DB::raw("COALESCE((SELECT SUM(pr.applied_to_supplier) FROM purchases_receipts pr WHERE pr.supplier_id = suppliers.id), 0) as opening_paid")
            ]))
            ->groupBy($supplierCols);

        // Apply optional start/end date filter on suppliers.created_at
        $start = $this->request()->get('start_date');
        $end = $this->request()->get('end_date');

        QueryFilters::applyDateFilters($query, $start, $end, null, null, 'suppliers.created_at');

        return $query->orderBy('suppliers.created_at', 'desc');
    }

    public function html() {
        return $this->builder()
            ->setTableId('suppliers-table')
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
            Column::make('supplier_name')
                ->className('text-center align-middle'),

            Column::make('area')
                ->title('Area')
                ->className('text-center align-middle'),

            Column::computed('total_amount')
                ->title('Total Bill Amount')
                ->className('text-center align-middle'),

            Column::computed('paid_amount')
                ->title('Paid Amount')
                ->className('text-center align-middle'),

            Column::computed('balance_amount')
                ->title('Bill Balance Amount')
                ->className('text-center align-middle'),

            Column::computed('open_balance')
                ->title('Open Balance')
                ->className('text-center align-middle'),

            Column::computed('total_balance')
                ->title('Total Balance')
                ->className('text-center align-middle'),

            Column::computed('excess_amount')
                ->title('Excess Amount')
                ->className('text-center align-middle'),

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->className('text-center align-middle')
                ->width('120px'),

            Column::make('created_at')
                ->visible(false)
                ->exportable(false)
                ->printable(false),
        ];
    }

    protected function filename(): string {
        return 'Suppliers_' . date('YmdHis');
    }

}
