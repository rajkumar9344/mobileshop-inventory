<?php

namespace Modules\Bin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Bin\DataTables\BinDataTable;
use Modules\Bin\Entities\Bin;
use Modules\Rack\Entities\Rack;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;

class BinController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(BinDataTable $dataTable)
    {
        if (!auth()->check() || (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('access_bins'))) {
            abort(403, 'Unauthorized');
        }
        return $dataTable->render('bin::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!auth()->check() || (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('create_bins'))) {
            abort(403, 'Unauthorized');
        }
        // Only allow selecting racks that are active. Fetch then apply a natural (numeric-aware) sort
        $racks = Rack::where('status', 'active')->get()->sortBy('rack_name', SORT_NATURAL | SORT_FLAG_CASE)->values();
        return view('bin::create', compact('racks'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!auth()->check() || (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('create_bins'))) {
            abort(403, 'Unauthorized');
        }
        // Normalize and sanitize inputs (trim and collapse internal whitespace)
        $input = $request->all();
        foreach (['bin_id', 'bin_name'] as $k) {
            if (isset($input[$k]) && is_string($input[$k])) {
                $v = trim($input[$k]);
                // collapse multiple spaces to single space
                $v = preg_replace('/\s+/', ' ', $v);
                $input[$k] = $v;
            }
        }
        $request->merge($input);

        // Validation rules per BRD
        $rules = [
            'rack_id' => ['required', Rule::exists('rack_master', 'id')->where('status', 'active')],
            'bin_id' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\\-_ ]+$/'],
            'bin_name' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:0', 'max:9999'],
            'status' => ['required', 'in:active,inactive'],
            'barcode' => ['nullable', 'string'],
        ];

        // Add unique rules for bin_id and bin_name per rack
        $rules['bin_id'][] = 'unique:bins,bin_id,NULL,id,rack_id,' . $request->rack_id;
        $rules['bin_name'][] = 'unique:bins,bin_name,NULL,id,rack_id,' . $request->rack_id;

        $messages = [
            'rack_id.required' => 'Rack is required.',
            'rack_id.exists' => 'Selected rack does not exist.',
            'bin_id.required' => 'Bin ID is required.',
            'bin_id.max' => 'Bin ID must not exceed 20 characters.',
            'bin_id.regex' => 'Bin ID may only contain letters, numbers, spaces, hyphens and underscores.',
            'bin_id.unique' => 'Bin ID already exists for this rack.',
            'bin_name.required' => 'Bin Name is required.',
            'bin_name.max' => 'Bin Name must not exceed 100 characters.',
            'bin_name.unique' => 'Bin Name already exists for this rack.',
            'capacity.required' => 'Bin Capacity is required.',
            'capacity.integer' => 'Bin Capacity must be a number.',
            'capacity.max' => 'Bin Capacity must not exceed 4 digits.',
            'status.required' => 'Status is required.',
            'status.in' => 'Status must be active or inactive.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        // Default status to active if not provided
        if (empty($validated['status'])) {
            $validated['status'] = 'active';
        }

        try {
            // Insert into bins table
            $bin = Bin::create($validated);

            toast('Bin Created!', 'success');

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'id' => $bin->id,
                    'bin_id' => $bin->bin_id,
                    'bin_name' => $bin->bin_name,
                    'rack_id' => $bin->rack_id,
                ], 201);
            }

            return redirect()->route('bin.index');
        } catch (QueryException $e) {
            // Log and return a friendly message
            \Log::error('Error creating bin: ' . $e->getMessage());
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => 'Failed to create bin: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->withErrors(['error' => 'Failed to create bin: ' . $e->getMessage()])->withInput();
        } catch (\Exception $e) {
            \Log::error('Error creating bin: ' . $e->getMessage());
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => 'Failed to create bin: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->withErrors(['error' => 'Failed to create bin: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        if (!auth()->check() || (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('show_bins'))) {
            abort(403, 'Unauthorized');
        }
        $bin = Bin::find($id);
        if (!$bin) {
            abort(404);
        }
        return view('bin::show', compact('bin'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if (!auth()->check() || (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('edit_bins'))) {
            abort(403, 'Unauthorized');
        }
        $bin = Bin::find($id);
        if (!$bin) {
            abort(404);
        }
        // Only show active racks for selection. Use natural (numeric-aware) ordering so RACK2 appears before RACK10
        $racks = Rack::where('status', 'active')->get()->sortBy('rack_name', SORT_NATURAL | SORT_FLAG_CASE)->values();
        return view('bin::edit', compact('bin', 'racks'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        if (!auth()->check() || (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('edit_bins'))) {
            abort(403, 'Unauthorized');
        }
        // Normalize and sanitize inputs for update (trim and collapse internal whitespace)
        $input = $request->all();
        foreach (['bin_name'] as $k) {
            if (isset($input[$k]) && is_string($input[$k])) {
                $v = trim($input[$k]);
                $v = preg_replace('/\s+/', ' ', $v);
                $input[$k] = $v;
            }
        }
        $request->merge($input);

        // Validation rules for update
        $rules = [
            'rack_id' => ['required', Rule::exists('rack_master', 'id')->where('status', 'active')],
            'bin_id' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\\-_ ]+$/', 'unique:bins,bin_id,' . $id . ',id,rack_id,' . $request->rack_id],
            'bin_name' => ['required', 'string', 'max:100', 'unique:bins,bin_name,' . $id . ',id,rack_id,' . $request->rack_id],
            'capacity' => ['required', 'integer', 'min:0', 'max:9999'],
            'status' => ['required', 'in:active,inactive'],
            'barcode' => ['nullable', 'string'],
        ];

        $messages = [
            'rack_id.required' => 'Rack is required.',
            'rack_id.exists' => 'Selected rack does not exist.',
            'bin_id.required' => 'Bin ID is required.',
            'bin_id.max' => 'Bin ID must not exceed 20 characters.',
            'bin_id.regex' => 'Bin ID may only contain letters, numbers, spaces, hyphens and underscores.',
            'bin_id.unique' => 'Bin ID already exists for this rack.',
            'bin_name.required' => 'Bin Name is required.',
            'bin_name.max' => 'Bin Name must not exceed 100 characters.',
            'bin_name.unique' => 'Bin Name already exists for this rack.',
            'capacity.required' => 'Bin Capacity is required.',
            'capacity.integer' => 'Bin Capacity must be a number.',
            'capacity.max' => 'Bin Capacity must not exceed 4 digits.',
            'status.required' => 'Status is required.',
            'status.in' => 'Status must be active or inactive.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        try {
            // Update bins table
            Bin::where('id', $id)->update($validated);

            toast('Bin Updated!', 'success');

            return redirect()->route('bin.index');
        } catch (QueryException $e) {
            \Log::error('Error updating bin: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to update bin: ' . $e->getMessage()])->withInput();
        } catch (\Exception $e) {
            \Log::error('Error updating bin: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to update bin: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): RedirectResponse
    {
        if (!auth()->check() || (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('delete_bins'))) {
            abort(403, 'Unauthorized');
        }
        $bin = Bin::find($id);
        if (!$bin) {
            abort(404);
        }
        // Check for references to this bin (e.g., products)
        try {
            $reasons = [];

            // Products reference bins via the `bin_no` column
            if (\Modules\Product\Entities\Product::where('bin_no', $bin->bin_id)->exists()) {
                $reasons[] = 'products';
            }

            if (!empty($reasons)) {
                $message = 'Cannot delete bin because it is referenced in: ' . implode(', ', $reasons) . '. Please delete or reassign the related records first.';
                toast($message, 'error');
                return redirect()->route('bin.index');
            }

            $bin->delete();

            toast('Bin Deleted!', 'warning');

            return redirect()->route('bin.index');
        } catch (QueryException $e) {
            \Log::error('Error deleting bin: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to delete bin: ' . $e->getMessage()]);
        } catch (\Exception $e) {
            \Log::error('Error deleting bin: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to delete bin: ' . $e->getMessage()]);
        }
    }

    /**
     * Get bins for a specific rack (AJAX endpoint)
     */
    public function getBins(Request $request)
    {
        try {
            $rackId = $request->validate([
                'rack_id' => 'required|integer|exists:rack_master,id'
            ])['rack_id'];

            $bins = Bin::where('rack_id', $rackId)
                      ->where('status', 'active')
                      ->orderBy('bin_id')
                      ->pluck('bin_id')
                      ->toArray();

            return response()->json($bins);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Invalid rack ID'], 422);
        } catch (\Exception $e) {
            \Log::error('Error fetching bins: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load bins'], 500);
        }
    }
}