<?php

namespace Modules\People\DataTables;


use Modules\People\Entities\Customer;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;
use App\Services\QueryFilters;

class CustomersDataTable extends DataTable
{
    use \App\Traits\HasPdfExport;

    public function dataTable($query) {
        // Compute server-side summary (applies same year/month filters or start_date/end_date)
        $year = $this->request()->get('year');
        $month = $this->request()->get('month');
        $start = $this->request()->get('start_date');
        $end = $this->request()->get('end_date');

        $summaryQuery = DB::table('customers' )
            ->leftJoin('sales', 'sales.customer_id', '=', 'customers.id');

        // Apply date filters to customers.created_at (filter by customer creation)
        QueryFilters::applyDateFilters($summaryQuery, $start, $end, $year, $month, 'customers.created_at');

        // Include global search term in summary so totals update when DataTable global search is used
        $searchValue = $this->request()->get('search')['value'] ?? null;
        QueryFilters::applyGlobalSearch($summaryQuery, $searchValue, 'customers.customer_name', 'customers.area');

        $summary = $summaryQuery->select(
            DB::raw('COUNT(DISTINCT customers.id) as customers_count'),
            // Exclude sales with status = "Draft"
            DB::raw("COALESCE(SUM(CASE WHEN sales.status IS NULL OR sales.status != 'Draft' THEN sales.total_amount ELSE 0 END),0) as overall_total"),
            DB::raw("COALESCE(SUM(CASE WHEN sales.status IS NULL OR sales.status != 'Draft' THEN sales.paid_amount ELSE 0 END),0) as overall_paid"),
            DB::raw("COALESCE(SUM(CASE WHEN sales.status IS NULL OR sales.status != 'Draft' THEN sales.discount_amount ELSE 0 END),0) as overall_discount"),
            DB::raw("COALESCE(SUM(CASE WHEN sales.status IS NULL OR sales.status != 'Draft' THEN sales.total_amount ELSE 0 END),0) - COALESCE(SUM(CASE WHEN sales.status IS NULL OR sales.status != 'Draft' THEN sales.paid_amount ELSE 0 END),0) - COALESCE(SUM(CASE WHEN sales.status IS NULL OR sales.status != 'Draft' THEN sales.discount_amount ELSE 0 END),0) as overall_balance"),
            DB::raw('COALESCE(SUM(customers.excess_amount),0) as overall_excess')
        )->first();

        // Compute overall_open_balance efficiently using a WHERE EXISTS subquery that
        // matches customers with at least one sale in the filtered set. This avoids
        // building large PHP arrays and leverages the DB for aggregation.
        // If a DataTable global search is present, restrict the overall_open_balance
        // aggregation to customers matching the search as well as existing date filters.

        $overall_open_balance_q = DB::table('customers as c')
            ->whereExists(function($q) {
                $q->select(DB::raw(1))->from('sales as s')->whereRaw('s.customer_id = c.id');
            });

        // apply created_at range on customers (alias `c`)
        QueryFilters::applyDateFilters($overall_open_balance_q, $start, $end, $year, $month, 'c.created_at');

        // Apply the same global search to the customers filter
        QueryFilters::applyGlobalSearch($overall_open_balance_q, $searchValue, 'c.customer_name', 'c.area');

        $overall_open_balance = $overall_open_balance_q->sum('c.opening_balance');

        $dt = datatables()
            ->eloquent($query)
            ->addColumn('total_amount', function ($data) {
                return format_currency( ($data->total_amount ?? 0) / 100 );
            })
            ->addColumn('paid_amount', function ($data) {
                return format_currency( ($data->paid_amount ?? 0) / 100 );
            })
            ->addColumn('discount_amount', function ($data) {
                return format_currency( ($data->discount_amount ?? 0) / 100 );
            })
            ->addColumn('balance_amount', function ($data) {
                return format_currency( ($data->balance_amount ?? 0) / 100 );
            })
            ->addColumn('open_balance', function ($data) {
                // `opening_balance` is stored on `customers` in major units
                return format_currency( ($data->opening_balance ?? 0) );
            })
            ->addColumn('excess_amount', function ($data) {
                // `excess_amount` is stored on `customers` (rupees), display formatted currency
                return format_currency( ($data->excess_amount ?? 0) );
            })
            ->addColumn('action', function ($data) {
                return view('people::customers.partials.actions', compact('data'));
            })
            ->with([
                'summary' => [
                    'customers_count' => $summary->customers_count ?? 0,
                    'overall_total' => format_currency( ($summary->overall_total ?? 0) / 100 ),
                    'overall_paid' => format_currency( ($summary->overall_paid ?? 0) / 100 ),
                    'overall_discount' => format_currency( ($summary->overall_discount ?? 0) / 100 ),
                    'overall_balance' => format_currency( ($summary->overall_balance ?? 0) / 100 ),
                    'overall_open_balance' => format_currency( ($overall_open_balance ?? 0) ),
                    'overall_excess' => format_currency( ($summary->overall_excess ?? 0) ),
                ]
            ]);
        // Server-side global search: ensure search term matches customer name or area
        $searchValue = $this->request()->get('search')['value'] ?? null;
        if ($searchValue) {
            $dt->filter(function ($q) use ($searchValue) {
                $q->where(function ($sub) use ($searchValue) {
                    $sub->where('customers.customer_name', 'like', "%{$searchValue}%")
                        ->orWhere('customers.area', 'like', "%{$searchValue}%");
                });
            });
        }

        return $dt;
    }

    public function query(Customer $model) {

        // Join with sales to compute per-customer aggregates (total, paid, discount, balance)
        // Explicitly select customer columns and include them in GROUP BY to satisfy
        // strict SQL modes (ONLY_FULL_GROUP_BY).
        $customerCols = [
            'customers.id', 'customers.customer_code', 'customers.customer_name', 'customers.customer_email',
            'customers.customer_phone', 'customers.city', 'customers.country', 'customers.address',
            'customers.gst_no', 'customers.pan_no', 'customers.aadhar_no', 'customers.area',
            'customers.excess_amount',
            'customers.state', 'customers.pincode', 'customers.lr_through', 'customers.opening_balance',
            'customers.credit_limit', 'customers.cash_discount', 'customers.additional_discount',
            'customers.discount_percent', 'customers.terms_days', 'customers.lock', 'customers.outstanding',
            'customers.is_active', 'customers.salesman', 'customers.account_id', 'customers.remarks',
            'customers.created_at', 'customers.updated_at'
        ];

        $query = $model->newQuery()
            ->leftJoin('sales', 'sales.customer_id', '=', 'customers.id')
            ->select(array_merge($customerCols, [
                DB::raw("COALESCE(SUM(CASE WHEN sales.status IS NULL OR sales.status != 'Draft' THEN sales.total_amount ELSE 0 END), 0) as total_amount"),
                DB::raw("COALESCE(SUM(CASE WHEN sales.status IS NULL OR sales.status != 'Draft' THEN sales.paid_amount ELSE 0 END), 0) as paid_amount"),
                DB::raw("COALESCE(SUM(CASE WHEN sales.status IS NULL OR sales.status != 'Draft' THEN sales.discount_amount ELSE 0 END), 0) as discount_amount"),
                DB::raw("(COALESCE(SUM(CASE WHEN sales.status IS NULL OR sales.status != 'Draft' THEN sales.total_amount ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN sales.status IS NULL OR sales.status != 'Draft' THEN sales.paid_amount ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN sales.status IS NULL OR sales.status != 'Draft' THEN sales.discount_amount ELSE 0 END), 0)) as balance_amount")
            ]))
            ->groupBy($customerCols);

        // Apply optional year/month OR start/end date-range filter passed from the frontend
        $year = $this->request()->get('year');
        $month = $this->request()->get('month');
        $start = $this->request()->get('start_date');
        $end = $this->request()->get('end_date');



        // Apply date filters (start/end preferred else year/month) to the query
        // Filter rows by customer creation date instead of sale date so the
        // UI date range matches the customer's `created_at` field.
        QueryFilters::applyDateFilters($query, $start, $end, $year, $month, 'customers.created_at');

        
        return $query->orderBy('customers.created_at', 'desc');
    }

    public function html() {
        return $this->builder()
            ->setTableId('customers-table')
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
            Column::make('customer_name')
                ->className('text-center align-middle'),
            Column::make('area')
                ->title('Area')
                ->className('text-center align-middle'),

            Column::computed('total_amount')
                ->title('Total Bill Amount')
                ->className('text-center align-middle'),

            Column::computed('paid_amount')
                ->title('Received Amount')
                ->className('text-center align-middle'),

            Column::computed('discount_amount')
                ->title('Discount Amount')
                ->className('text-center align-middle'),

            Column::computed('balance_amount')
                ->title('Bill Balance Amount')
                ->className('text-center align-middle'),

            Column::computed('open_balance')
                ->title('Open Balance')
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
        return 'Customers_' . date('YmdHis');
    }

}
