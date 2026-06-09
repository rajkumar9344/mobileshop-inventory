<?php

namespace Modules\People\Http\Controllers;

use Modules\People\DataTables\CustomersDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Modules\People\Http\Requests\StoreCustomerRequest;
use Modules\People\Http\Requests\UpdateCustomerRequest;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\People\Entities\Customer;
use Modules\Sale\Entities\Sale;
use Illuminate\Support\Facades\DB;
use App\Services\QueryFilters;
use Carbon\Carbon;

class CustomersController extends Controller
{

    public function index(CustomersDataTable $dataTable) {
        abort_if(Gate::denies('access_customers'), 403);

        return $dataTable->render('people::customers.index');
    }

    /**
     * Aggregated totals for customers listing (supports optional year/month filters).
     */
    public function totals(Request $request) {
        abort_if(Gate::denies('access_customers'), 403);

        $year = $request->get('year');
        $month = $request->get('month');
        $start = $request->get('start_date');
        $end = $request->get('end_date');
        $search = $request->get('search');

        $base = DB::table('sales')->leftJoin('customers', 'sales.customer_id', '=', 'customers.id');

        // Reuse query filters for date and search so controller and DataTable behave identically
        // Filter by customer creation date instead of sale date
        QueryFilters::applyDateFilters($base, $start, $end, $year, $month, 'customers.created_at');
        QueryFilters::applyGlobalSearch($base, $search, 'customers.customer_name', 'customers.area');

        $totals = [];
        // Count distinct customers with sales in the period
        $totals['overall_count'] = $base->distinct('customers.id')->count('customers.id');
        // Totals (sales amounts are stored as integers in paise in sales table; divide by 100 for display)
        $totals['overall_total_amount'] = $base->sum(DB::raw('COALESCE(sales.total_amount,0)')) / 100;
        $totals['overall_received_amount'] = $base->sum(DB::raw('COALESCE(sales.paid_amount,0)')) / 100;
        $totals['overall_balance'] = ($base->sum(DB::raw('COALESCE(sales.total_amount,0)')) - $base->sum(DB::raw('COALESCE(sales.paid_amount,0)'))) / 100;

        // Compute overall_open_balance via a WHERE EXISTS subquery so we don't pull IDs into PHP
        $overall_open_balance_q = DB::table('customers as c')
            ->whereExists(function($q) {
                $q->select(DB::raw(1))->from('sales as s')->whereRaw('s.customer_id = c.id');
            });

        // apply created_at range on customers (alias `c`)
        QueryFilters::applyDateFilters($overall_open_balance_q, $start, $end, $year, $month, 'c.created_at');

        // Apply search filter to customers when provided so totals match table filtering
        if (!empty($search)) {
            $overall_open_balance_q->where(function($w) use ($search) {
                $w->where('c.customer_name', 'like', "%{$search}%")
                  ->orWhere('c.area', 'like', "%{$search}%");
            });
        }

        $totals['overall_open_balance'] = $overall_open_balance_q->sum(DB::raw('COALESCE(c.opening_balance,0)'));
        $totals['overall_excess'] = $overall_open_balance_q->sum(DB::raw('COALESCE(c.excess_amount,0)'));

        return response()->json($totals);
    }


    public function create() {
        abort_if(Gate::denies('create_customers'), 403);

        return view('people::customers.create');
    }


    public function store(StoreCustomerRequest $request) {
        abort_if(Gate::denies('create_customers'), 403);

        // Validation and sanitization handled by StoreCustomerRequest
        $data = $request->validated();

        // Ensure is_active is boolean (defaults to 1)
        $data['is_active'] = isset($data['is_active']) ? (bool) $data['is_active'] : true;

        // Defaults for dropdowns
        $data['lock'] = $data['lock'] ?? 'No';
        $data['outstanding'] = $data['outstanding'] ?? 'No';

        foreach (['opening_balance', 'excess_amount', 'credit_limit'] as $numField) {
            if (array_key_exists($numField, $data)) {
                if ($data[$numField] === null || $data[$numField] === '') {
                    $data[$numField] = 0;
                } else {
                    $data[$numField] = str_replace(',', '', $data[$numField]);
                }
            } else {
                $data[$numField] = 0;
            }
        }

        Customer::create($data);

        toast('Customer Created!', 'success');

        return redirect()->route('customers.index');
    }


    public function show(Customer $customer) {
        abort_if(Gate::denies('show_customers'), 403);

        return view('people::customers.show', compact('customer'));
    }

    /**
     * Return customer as JSON for API consumption (used by sale form Ajax).
     */
    public function apiShow(Customer $customer) {
        // minimal security: require authenticated user
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $today = Carbon::today();

        // Match Sales Outstanding Report condition: only overdue bills (due_date < today)
        $hasOverdueOutstanding = Sale::query()
            ->where('customer_id', $customer->id)
            ->whereIn('payment_status', ['Unpaid', 'Pending', 'Partial', 'Partially Paid'])
            ->where('status', '!=', 'Draft')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->exists();

        // Return only the fields needed by the sale form
        $payload = $customer->only([
            'id', 'customer_name', 'customer_phone', 'area', 'opening_balance', 'terms_days', 'lock', 'discount_percent', 'cash_discount', 'additional_discount', 'credit_limit', 'excess_amount', 'outstanding'
        ]);

        $payload['has_overdue_outstanding'] = $hasOverdueOutstanding;

        return response()->json($payload);
    }

    /**
     * Lookup a customer by phone number and return the same payload as apiShow.
     * Used by the sale create form to auto-fill customer details when a phone number is typed.
     */
    public function apiShowByPhone(string $phone)
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $customer = Customer::where('customer_phone', $phone)
            ->where('is_active', true)
            ->first();

        if (!$customer) {
            return response()->json(['found' => false], 404);
        }

        return $this->apiShow($customer);
    }

    /**
     * Search customers for Select2 (session-authenticated).
     * Expects query param `q` and returns JSON in Select2 format: { results: [ { id, text } ] }
     */
    public function apiSearch(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $q = trim($request->get('q', ''));

        $query = Customer::query();

        if ($q !== '') {
            $query->where(function($w) use ($q) {
                $w->where('customer_name', 'like', "%{$q}%")
                  ->orWhere('customer_code', 'like', "%{$q}%")
                  ->orWhere('customer_phone', 'like', "%{$q}%");
            });
        }

        $customers = $query->orderBy('customer_name')->limit(50)->get();

        $results = $customers->map(function($c){
            return [
                'id' => $c->id,
                // text is what Select2 displays in the dropdown
                'text' => $c->customer_name . ($c->customer_code ? ' (' . $c->customer_code . ')' : ''),
            ];
        })->values();

        return response()->json(['results' => $results]);
    }


    public function edit(Customer $customer) {
        abort_if(Gate::denies('edit_customers'), 403);

        return view('people::customers.edit', compact('customer'));
    }


    public function update(UpdateCustomerRequest $request, Customer $customer) {
        abort_if(Gate::denies('update_customers'), 403);

        // Validation and sanitization handled by UpdateCustomerRequest
        $data = $request->validated();

        foreach (['opening_balance', 'excess_amount', 'credit_limit'] as $numField) {
            if (array_key_exists($numField, $data)) {
                if ($data[$numField] === null || $data[$numField] === '') {
                    $data[$numField] = 0;
                } else {
                    $data[$numField] = str_replace(',', '', $data[$numField]);
                }
            }
        }

        $data['is_active'] = isset($data['is_active']) ? (bool) $data['is_active'] : $customer->is_active;

        $data['lock'] = $data['lock'] ?? $customer->lock ?? 'No';
        $data['outstanding'] = $data['outstanding'] ?? $customer->outstanding ?? 'No';

        $customer->update($data);

        toast('Customer Updated!', 'info');

        return redirect()->route('customers.index');
    }


    public function destroy(Customer $customer) {
        abort_if(Gate::denies('delete_customers'), 403);

        $customer->delete();

        toast('Customer Deleted!', 'warning');

        return redirect()->route('customers.index');
    }
}
