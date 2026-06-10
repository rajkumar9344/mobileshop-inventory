<?php

namespace Modules\People\Http\Controllers;

use Modules\People\DataTables\SuppliersDataTable;
use Illuminate\Contracts\Support\Renderable;
use Modules\People\Http\Requests\StoreSupplierRequest;
use Modules\People\Http\Requests\UpdateSupplierRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Modules\People\Entities\Supplier;
use Illuminate\Validation\Rule;
use App\Services\QueryFilters;

class SuppliersController extends Controller
{

    public function index(SuppliersDataTable $dataTable) {
        abort_if(Gate::denies('access_suppliers'), 403);

        return $dataTable->render('people::suppliers.index');
    }


    public function create() {
        abort_if(Gate::denies('create_suppliers'), 403);

        return view('people::suppliers.create');
    }


    public function store(StoreSupplierRequest $request) {
        abort_if(Gate::denies('create_suppliers'), 403);

        // Validation handled by StoreSupplierRequest

        Supplier::create($request->validated());

        toast('Supplier Created!', 'success');

        return redirect()->route('suppliers.index');
    }


    public function show(Supplier $supplier) {
        abort_if(Gate::denies('show_suppliers'), 403);

        return view('people::suppliers.show', compact('supplier'));
    }


    public function edit(Supplier $supplier) {
        abort_if(Gate::denies('edit_suppliers'), 403);

        return view('people::suppliers.edit', compact('supplier'));
    }


    public function update(UpdateSupplierRequest $request, Supplier $supplier) {
        abort_if(Gate::denies('edit_suppliers'), 403);

        // Validation handled by UpdateSupplierRequest

        $supplier->update($request->validated());

        toast('Supplier Updated!', 'info');

        return redirect()->route('suppliers.index');
    }


    public function destroy(Supplier $supplier) {
        abort_if(Gate::denies('delete_suppliers'), 403);

        $supplier->delete();

        toast('Supplier Deleted!', 'warning');

        return redirect()->route('suppliers.index');
    }

    /**
     * Return supplier as JSON for API consumption (used by purchase form Ajax).
     */
    public function apiShow(Supplier $supplier) {
        // minimal security: require authenticated user
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Return only the fields needed by the purchase form
        // Include supplier discount so purchase form can apply it to the cart
        return response()->json($supplier->only([
            'id', 'supplier_name', 'supplier_phone', 'area', 'open_balance', 'credit_limit', 'tax_percent', 'excess_amount', 'due_days'
        ]));
    }

    /**
     * Search suppliers for Select2 (session-authenticated).
     * Expects query param `q` and returns JSON in Select2 format: { results: [ { id, text } ] }
     */
    public function apiSearch(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $q = trim($request->get('q', ''));

        $query = Supplier::query();

        if ($q !== '') {
            $query->where(function($w) use ($q) {
                $w->where('supplier_name', 'like', "%{$q}%")
                  ->orWhere('supplier_code', 'like', "%{$q}%")
                  ->orWhere('supplier_phone', 'like', "%{$q}%");
            });
        }

        $suppliers = $query->orderBy('supplier_name')->limit(50)->get();

        $results = $suppliers->map(function($s){
            return [
                'id' => $s->id,
                // text is what Select2 displays in the dropdown
                'text' => $s->supplier_name . ($s->supplier_code ? ' (' . $s->supplier_code . ')' : ''),
                // include style/type so Select2 selection handlers can read supplier type without an extra API call
                'type' => $s->style ?? 1,
                'style' => $s->style ?? 1,
                'excess' => $s->excess_amount ?? 0,
            ];
        })->values();

        return response()->json(['results' => $results]);
    }

    /**
     * Aggregated totals for suppliers listing (supports optional date filters).
     */
    public function totals(Request $request) {
        abort_if(Gate::denies('access_suppliers'), 403);

        $start = $request->get('start_date');
        $end = $request->get('end_date');
        $search = $request->get('search');

        $base = DB::table('purchases')->leftJoin('suppliers', 'purchases.supplier_id', '=', 'suppliers.id');

        // Reuse query filters for date and search so controller and DataTable behave identically
        QueryFilters::applyDateFilters($base, $start, $end, null, null, 'purchases.date');
        QueryFilters::applyGlobalSearch($base, $search, 'suppliers.supplier_name', 'suppliers.area');

        $totals = [];
        // Count distinct suppliers with purchases in the period
        $totals['overall_count'] = $base->distinct('suppliers.id')->count('suppliers.id');
        // Totals (purchases amounts are stored as integers in paise in purchases table; divide by 100 for display)
        $totals['overall_total_amount'] = $base->sum(DB::raw('COALESCE(purchases.total_amount,0)')) / 100;
        $totals['overall_received_amount'] = $base->sum(DB::raw('COALESCE(purchases.paid_amount,0)')) / 100;
        $totals['overall_balance'] = ($base->sum(DB::raw('COALESCE(purchases.total_amount,0)')) - $base->sum(DB::raw('COALESCE(purchases.paid_amount,0)'))) / 100;

        // Compute overall_open_balance via a WHERE EXISTS subquery so we don't pull IDs into PHP
        $overall_open_balance_q = DB::table('suppliers as s')
            ->whereExists(function($q) {
                $q->select(DB::raw(1))->from('purchases as p')->whereRaw('p.supplier_id = s.id');
            });

        // apply created_at range on suppliers (alias `s`)
        QueryFilters::applyDateFilters($overall_open_balance_q, $start, $end, null, null, 's.created_at');

        // Apply search filter to suppliers when provided so totals match table filtering
        if (!empty($search)) {
            $overall_open_balance_q->where(function($w) use ($search) {
                $w->where('s.supplier_name', 'like', "%{$search}%")
                  ->orWhere('s.area', 'like', "%{$search}%");
            });
        }

        $totals['overall_open_balance'] = $overall_open_balance_q->sum(DB::raw('COALESCE(s.open_balance,0)'));
        $totals['overall_excess'] = $overall_open_balance_q->sum(DB::raw('COALESCE(s.excess_amount,0)'));

        return response()->json($totals);
    }
}
