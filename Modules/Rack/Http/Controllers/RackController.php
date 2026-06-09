<?php

namespace Modules\Rack\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Rack\DataTables\RackDataTable;
use Modules\Rack\Entities\Rack;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;

class RackController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(RackDataTable $dataTable)
    {
        if (!auth()->check() || (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('access_racks'))) {
            abort(403, 'Unauthorized');
        }
        return $dataTable->render('rack::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!auth()->check() || (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('create_racks'))) {
            abort(403, 'Unauthorized');
        }
        return view('rack::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!auth()->check() || (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('create_racks'))) {
            abort(403, 'Unauthorized');
        }
        // Normalize and sanitize inputs (trim and collapse internal whitespace)
        $input = $request->all();
        foreach (['rack_id', 'rack_name'] as $k) {
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
            'rack_id' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\\-_ ]+$/', 'unique:rack_master,rack_id'],
            'rack_name' => ['required', 'string', 'max:100', 'unique:rack_master,rack_name'],
            'barcode' => ['nullable', 'string'],
            'status' => ['required', 'in:Active,Inactive'],
        ];

        $messages = [
            'rack_id.required' => 'Rack ID is required.',
            'rack_id.max' => 'Rack ID must not exceed 20 characters.',
            'rack_id.regex' => 'Rack ID may only contain letters, numbers, spaces, hyphens and underscores.',
            'rack_id.unique' => 'Rack ID already exists.',
            'rack_name.required' => 'Rack Name/Number is required.',
            'rack_name.max' => 'Rack Name/Number must not exceed 100 characters.',
            'rack_name.unique' => 'Rack Name/Number already exists.',
            'status.required' => 'Status is required.',
            'status.in' => 'Status must be either Active or Inactive.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        // Default status to Active if not provided
        if (empty($validated['status'])) {
            $validated['status'] = 'Active';
        }

        // Pre-check duplicates to provide friendly validation errors (avoid DB exception)
        $duplicateErrors = [];
        // Unique rules in validation handle duplicates

        if (!empty($duplicateErrors)) {
            toast('Duplicate entry detected', 'warning');
            return redirect()->back()->withErrors($duplicateErrors)->withInput();
        }

        try {
            // Insert into rack_master table
            $rack = Rack::create($validated);

            toast('Rack Created!', 'success');

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'id' => $rack->id,
                    'rack_id' => $rack->rack_id,
                    'rack_name' => $rack->rack_name,
                ], 201);
            }

            return redirect()->route('rack.index');
        } catch (QueryException $e) {
            // Log and return a friendly message
            \Log::error('Error creating rack: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to create rack: ' . $e->getMessage()])->withInput();
        } catch (\Exception $e) {
            \Log::error('Error creating rack: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to create rack: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        if (!auth()->check() || (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('show_racks'))) {
            abort(403, 'Unauthorized');
        }
        $rack = Rack::find($id);
        if (!$rack) {
            abort(404);
        }
        return view('rack::show', compact('rack'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if (!auth()->check() || (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('edit_racks'))) {
            abort(403, 'Unauthorized');
        }
        $rack = Rack::find($id);
        if (!$rack) {
            abort(404);
        }
        return view('rack::edit', compact('rack'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        if (!auth()->check() || (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('edit_racks'))) {
            abort(403, 'Unauthorized');
        }
        $input = $request->all();
        foreach (['rack_name'] as $k) {
            if (isset($input[$k]) && is_string($input[$k])) {
                $v = trim($input[$k]);
                $v = preg_replace('/\s+/', ' ', $v);
                $input[$k] = $v;
            }
        }
        // include rack_id if present (readonly field during edit)
        if (isset($request->rack_id) && is_string($request->rack_id)) {
            $input['rack_id'] = trim(preg_replace('/\s+/', ' ', $request->rack_id));
        }
        $request->merge($input);

        // Validation rules for update
        $rules = [
            'rack_id' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\\-_ ]+$/', 'unique:rack_master,rack_id,' . $id],
            'rack_name' => ['required', 'string', 'max:100', 'unique:rack_master,rack_name,' . $id],
            'barcode' => ['nullable', 'string'],
            'status' => ['required', 'in:Active,Inactive'],
        ];

        $messages = [
            'rack_id.required' => 'Rack ID is required.',
            'rack_id.max' => 'Rack ID must not exceed 20 characters.',
            'rack_id.regex' => 'Rack ID may only contain letters, numbers, spaces, hyphens and underscores.',
            'rack_id.unique' => 'Rack ID already exists.',
            'rack_name.required' => 'Rack Name/Number is required.',
            'rack_name.max' => 'Rack Name/Number must not exceed 100 characters.',
            'rack_name.unique' => 'Rack Name/Number already exists.',
            'status.required' => 'Status is required.',
            'status.in' => 'Status must be either Active or Inactive.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        // Business logic: Check if trying to deactivate rack with active bins
        if (isset($validated['status']) && $validated['status'] === 'Inactive') {
            $activeBinsCount = \Modules\Bin\Entities\Bin::where('rack_id', $id)
                ->where('status', 'active')
                ->count();

            if ($activeBinsCount > 0) {
                return redirect()->back()->withErrors([
                    'status' => "Cannot deactivate rack. There are {$activeBinsCount} active bin(s) associated with this rack. Please deactivate all bins first."
                ])->withInput();
            }
        }

        // Ensure we have the rack_id to check uniqueness; if not provided in the request (readonly fields may be absent),
        // fall back to the existing record's rack_id from DB.
        $existingRack = Rack::find($id);

        // Pre-check duplicates (exclude current record) to provide friendly validation errors
        $duplicateErrors = [];
        // Unique rules handle duplicates

        if (!empty($duplicateErrors)) {
            toast('Duplicate entry detected', 'warning');
            return redirect()->back()->withErrors($duplicateErrors)->withInput();
        }

        try {
            // Update rack_master table
            Rack::where('id', $id)->update($validated);

            // If rack status changed to Inactive, also deactivate all associated bins
            if (isset($validated['status']) && $validated['status'] === 'Inactive') {
                \Modules\Bin\Entities\Bin::where('rack_id', $id)->update(['status' => 'inactive']);
            }

            toast('Rack Updated!', 'success');

            return redirect()->route('rack.index');
        } catch (QueryException $e) {
            \Log::error('Error updating rack: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to update rack: ' . $e->getMessage()])->withInput();
        } catch (\Exception $e) {
            \Log::error('Error updating rack: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to update rack: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (!auth()->check() || (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('delete_racks'))) {
            abort(403, 'Unauthorized');
        }

        // Business logic: Check if rack has active bins before deletion
        $activeBinsCount = \Modules\Bin\Entities\Bin::where('rack_id', $id)
            ->where('status', 'active')
            ->count();

        if ($activeBinsCount > 0) {
            toast("Cannot delete rack. There are {$activeBinsCount} active bin(s) associated with this rack. Please deactivate all bins first.", 'error');
            return redirect()->back();
        }

        try {
            Rack::find($id)->delete();
            toast('Rack Deleted!', 'success');
            return redirect()->route('rack.index');
        } catch (\Exception $e) {
            \Log::error('Error deleting rack: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to delete rack: ' . $e->getMessage()]);
        }
    }
}
