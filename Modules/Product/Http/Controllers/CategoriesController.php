<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Product\Entities\Category;
use Illuminate\Support\Facades\Validator;

class CategoriesController extends Controller
{

    public function index(\Modules\Product\DataTables\CategoryDataTable $dataTable) {
        abort_if(Gate::denies('access_product_categories'), 403);

        return $dataTable->render('product::categories.categories-only');
    }


    public function store(Request $request) {
        abort_if(Gate::denies('create_product_categories'), 403);

        $rules = [
            'category_code' => ['required', 'string', 'max:15', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:categories,category_code'],
            'category_name' => ['required', 'string', 'max:100', 'unique:categories,category_name'],
            'status' => ['nullable', 'boolean'],
        ];

        $messages = [
            'category_code.regex' => 'Brand Code may only contain letters, numbers, hyphen and underscore.',
            'category_code.max' => 'Brand Code may not be greater than 15 characters.',
            'category_name.max' => 'Brand Name may not be greater than 100 characters.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            // Prefer friendly Brand messages for unique failures, otherwise show validator text
            $failed = $validator->failed();

            if (isset($failed['category_code']) && array_key_exists('Unique', $failed['category_code'])) {
                toast('Brand Code already exists.', 'error');
            } elseif ($validator->errors()->has('category_code')) {
                toast($validator->errors()->first('category_code'), 'error');
            }

            if (isset($failed['category_name']) && array_key_exists('Unique', $failed['category_name'])) {
                toast('Brand Name already exists.', 'error');
            } elseif ($validator->errors()->has('category_name')) {
                toast($validator->errors()->first('category_name'), 'error');
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $category = Category::create([
                'category_code' => $request->category_code,
                'category_name' => $request->category_name,
                'status' => true, // default active on create
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle unique constraint violation (concurrent insert)
            $codeExists = Category::where('category_code', $request->category_code)->exists();
            $nameExists = Category::where('category_name', $request->category_name)->exists();

            $errors = [];
            if ($codeExists) {
                $errors['category_code'] = 'Brand Code already exists.';
                toast('Brand Code already exists.', 'error');
            }
            if ($nameExists) {
                $errors['category_name'] = 'Brand Name already exists.';
                toast('Brand Name already exists.', 'error');
            }

            if (empty($errors)) {
                $errors['category_code'] = 'Brand Code or Brand Name already exists.';
                toast('Brand Code or Brand Name already exists.', 'error');
            }

            if ($request->wantsJson() || $request->ajax()) {
                $jsonErrors = array_map(function($v){ return is_array($v) ? $v : [$v]; }, $errors);
                return response()->json(['errors' => $jsonErrors], 422);
            }

            return redirect()->back()->withInput()->withErrors($errors);
        }

        toast('Brand Created!', 'success');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'id' => $category->id,
                'category_name' => $category->category_name,
                'category_code' => $category->category_code,
            ], 201);
        }

        return redirect()->back();
    }


    public function edit($id) {
        abort_if(Gate::denies('edit_product_categories'), 403);

        $category = Category::findOrFail($id);

        return view('product::categories.edit', compact('category'));
    }

    public function show($id) {
        abort_if(Gate::denies('show_product_categories'), 403);

        $category = Category::withCount('products')->findOrFail($id);

        return view('product::categories.show', compact('category'));
    }


    public function update(Request $request, $id) {
        abort_if(Gate::denies('edit_product_categories'), 403);

        $category = Category::findOrFail($id);

        $rules = [
            'category_code' => ['required', 'string', 'max:15', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:categories,category_code,' . $category->id],
            'category_name' => ['required', 'string', 'max:100', 'unique:categories,category_name,' . $category->id],
            'status' => ['nullable', 'boolean'],
        ];

        $messages = [
            'category_code.regex' => 'Brand Code may only contain letters, numbers, hyphen and underscore.',
            'category_code.max' => 'Brand Code may not be greater than 15 characters.',
            'category_name.max' => 'Brand Name may not be greater than 100 characters.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            $failed = $validator->failed();

            if (isset($failed['category_code']) && array_key_exists('Unique', $failed['category_code'])) {
                toast('Brand Code already exists.', 'error');
            } elseif ($validator->errors()->has('category_code')) {
                toast($validator->errors()->first('category_code'), 'error');
            }

            if (isset($failed['category_name']) && array_key_exists('Unique', $failed['category_name'])) {
                toast('Brand Name already exists.', 'error');
            } elseif ($validator->errors()->has('category_name')) {
                toast($validator->errors()->first('category_name'), 'error');
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Prevent inactivation via edit if there are products or subcategories associated
        if ($request->has('status') && !(bool)$request->status) {
            if ($category->products()->exists()) {
                toast("Can't inactivate because there are products associated with this brand.", 'error');
                return redirect()->back()->withInput();
            }
            if ($category->subcategories()->where('status', true)->exists()) {
                toast("Can't inactivate because there are sub-categories associated with this brand.", 'error');
                return redirect()->back()->withInput();
            }
        }

        try {
            $category->update([
                'category_code' => $request->category_code,
                'category_name' => $request->category_name,
                'status' => $request->has('status') ? (bool)$request->status : $category->status,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Determine which field conflicts (exclude current record)
            $codeExists = Category::where('category_code', $request->category_code)->where('id', '!=', $category->id)->exists();
            $nameExists = Category::where('category_name', $request->category_name)->where('id', '!=', $category->id)->exists();

            $errors = [];
            if ($codeExists) {
                $errors['category_code'] = 'Brand Code already exists.';
                toast('Brand Code already exists.', 'error');
            }
            if ($nameExists) {
                $errors['category_name'] = 'Brand Name already exists.';
                toast('Brand Name already exists.', 'error');
            }

            if (empty($errors)) {
                $errors['category_code'] = 'Brand Code or Brand Name already exists.';
                toast('Brand Code or Brand Name already exists.', 'error');
            }

            return redirect()->back()->withInput()->withErrors($errors);
        }

        toast('Brand Updated!', 'info');

        return redirect()->route('product-categories.index');
    }


    public function destroy($id) {
        abort_if(Gate::denies('delete_product_categories'), 403);

        $category = Category::findOrFail($id);
        // Prevent inactivation if there are products or subcategories associated
        if ($category->products()->exists()) {
            toast("Can't inactivate because there are products associated with this brand.", 'error');
            return redirect()->back();
        }
        if ($category->subcategories()->where('status', true)->exists()) {
            toast("Can't inactivate because there are sub-categories associated with this brand.", 'error');
            return redirect()->back();
        }

        // Instead of hard-deleting, mark the brand inactive.
        $category->status = false;
        $category->save();

        toast('Brand Inactivated!', 'warning');

        return redirect()->route('product-categories.index');
    }

    public function deleteCategory(Request $request, Category $category) {
        abort_if(Gate::denies('edit_product_categories'), 403);

        // Toggle or set status via AJAX. Expecting status param (1 or 0). Default to 0 (inactive).
        $status = $request->input('status', 0);

        // Prevent inactivation if products or subcategories are associated
        if (!(bool)$status) {
            if ($category->products()->exists()) {
                return response()->json(['error' => "Can't inactivate because there are products associated with this brand."], 422);
            }
            if ($category->subcategories()->where('status', true)->exists()) {
                return response()->json(['error' => "Can't inactivate because there are sub-categories associated with this brand."], 422);
            }
        }

        $category->status = (bool)$status;
        $category->save();

        return response()->json(['success' => 'Brand status updated successfully.']);
    }
}
