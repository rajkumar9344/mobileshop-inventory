<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Product\Entities\Subcategory;
use Modules\Product\Entities\Category;
use Modules\Product\DataTables\SubcategoriesDataTable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;

class SubcategoriesController extends Controller
{
    public function index(SubcategoriesDataTable $dataTable) {
        abort_if(Gate::denies('access_product_subcategories'), 403);

        return $dataTable->render('product::subcategories.index');
    }

    public function store(Request $request) {
        abort_if(Gate::denies('create_product_subcategories'), 403);
        $rules = [
            'category_id' => ['required', 'exists:categories,id'],
            'subcategory_name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('subcategories', 'subcategory_name')->where(function ($q) use ($request) {
                    return $q->where('category_id', $request->category_id);
                })
            ],
            'status' => ['nullable', 'boolean'],
        ];

        $messages = [
            'subcategory_name.max' => 'Subcategory Name may not be greater than 100 characters.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            $failed = $validator->failed();

            if (isset($failed['subcategory_name']) && array_key_exists('Unique', $failed['subcategory_name'])) {
                toast('Subcategory already exists under the selected Brand.', 'error');
            } elseif ($validator->errors()->has('subcategory_name')) {
                toast($validator->errors()->first('subcategory_name'), 'error');
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $subcategory = Subcategory::create([
                'category_id' => $request->category_id,
                'subcategory_name' => $request->subcategory_name,
                'status' => true,
            ]);
        } catch (QueryException $e) {
            // concurrent insert: detect which field conflicts
            $exists = Subcategory::where('category_id', $request->category_id)
                ->where('subcategory_name', $request->subcategory_name)
                ->exists();

            $errors = [];
            if ($exists) {
                $errors['subcategory_name'] = 'This subcategory already exists under the selected category.';
                toast('Subcategory already exists under the selected Brand.', 'error');
            }

            if (empty($errors)) {
                $errors['subcategory_name'] = 'Subcategory creation failed due to duplicate or concurrent insert.';
                toast('Subcategory creation failed.', 'error');
            }

            if ($request->wantsJson() || $request->ajax()) {
                $jsonErrors = array_map(function($v){ return is_array($v) ? $v : [$v]; }, $errors);
                return response()->json(['errors' => $jsonErrors], 422);
            }

            return redirect()->back()->withInput()->withErrors($errors);
        }

        toast('Subcategory Created!', 'success');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'id' => $subcategory->id,
                'subcategory_name' => $subcategory->subcategory_name,
                'category_id' => $subcategory->category_id,
            ], 201);
        }

        return redirect()->back();
    }

    public function edit($id) {
        abort_if(Gate::denies('edit_product_subcategories'), 403);

        $subcategory = Subcategory::with('category')->findOrFail($id);

        return view('product::subcategories.edit', compact('subcategory'));
    }

    public function show($id) {
        abort_if(Gate::denies('show_product_subcategories'), 403);

        $subcategory = Subcategory::with('category')->withCount('products')->findOrFail($id);

        return view('product::subcategories.show', compact('subcategory'));
    }

    public function update(Request $request, $id) {
        abort_if(Gate::denies('edit_product_subcategories'), 403);

        $subcategory = Subcategory::findOrFail($id);
        $rules = [
            'category_id' => ['required', 'exists:categories,id'],
            'subcategory_name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('subcategories', 'subcategory_name')->where(function ($q) use ($request) {
                    return $q->where('category_id', $request->category_id);
                })->ignore($subcategory->id)
            ],
            'status' => ['nullable', 'boolean'],
        ];

        $messages = [
            'subcategory_name.max' => 'Subcategory Name may not be greater than 100 characters.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            $failed = $validator->failed();

            if (isset($failed['subcategory_name']) && array_key_exists('Unique', $failed['subcategory_name'])) {
                toast('Subcategory already exists under the selected Brand.', 'error');
            } elseif ($validator->errors()->has('subcategory_name')) {
                toast($validator->errors()->first('subcategory_name'), 'error');
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Prevent inactivation via edit if there are products associated
        if ($request->has('status') && !(bool)$request->status) {
            if ($subcategory->products()->exists()) {
                toast("Can't inactivate because there are products associated with this subcategory.", 'error');
                return redirect()->back()->withInput();
            }
        }

        try {
            $subcategory->update([
                'category_id' => $request->category_id,
                'subcategory_name' => $request->subcategory_name,
                'status' => $request->has('status') ? (bool)$request->status : $subcategory->status,
            ]);
        } catch (QueryException $e) {
            $exists = Subcategory::where('category_id', $request->category_id)
                ->where('subcategory_name', $request->subcategory_name)
                ->where('id', '!=', $subcategory->id)
                ->exists();

            $errors = [];
            if ($exists) {
                $errors['subcategory_name'] = 'This subcategory already exists under the selected category.';
                toast('Subcategory already exists under the selected Brand.', 'error');
            }

            if (empty($errors)) {
                $errors['subcategory_name'] = 'Subcategory update failed due to duplicate or concurrent update.';
                toast('Subcategory update failed.', 'error');
            }

            return redirect()->back()->withInput()->withErrors($errors);
        }

        toast('Subcategory Updated!', 'info');

        return redirect()->route('product-subcategories.index');
    }

    public function destroy($id) {
        abort_if(Gate::denies('delete_product_subcategories'), 403);

        $subcategory = Subcategory::findOrFail($id);
        if ($subcategory->products()->exists()) {
            toast('Can\'t inactivate because there are products associated with this subcategory.', 'error');
            return redirect()->back();
        }

        // mark as inactive instead of deleting
        $subcategory->status = false;
        $subcategory->save();

        toast('Subcategory Inactivated!', 'warning');

        return redirect()->route('product-subcategories.index');
    }

    public function deleteSubcategory(Request $request, Subcategory $subcategory) {
        abort_if(Gate::denies('delete_product_subcategories'), 403);

        $status = $request->input('status', 0);
        $subcategory->status = (bool)$status;
        $subcategory->save();

        return response()->json(['success' => 'Subcategory status updated successfully.']);
    }
}
