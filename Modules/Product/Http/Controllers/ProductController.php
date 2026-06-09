<?php

namespace Modules\Product\Http\Controllers;

use Modules\Product\DataTables\ProductDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\ProductCode;
use Modules\Product\Http\Requests\StoreProductRequest;
use Modules\Product\Http\Requests\UpdateProductRequest;
use Modules\Upload\Entities\Upload;
use Modules\Rack\Entities\Rack;
use Illuminate\Database\QueryException;

class ProductController extends Controller
{

    public function index(ProductDataTable $dataTable) {
        abort_if(Gate::denies('access_products'), 403);

        $categories = \Modules\Product\Entities\Category::where('status', true)
            ->orderBy('category_name')
            ->get(['id', 'category_name']);

        $subcategories = \Modules\Product\Entities\Subcategory::where('status', true)
            ->orderBy('subcategory_name')
            ->get(['id', 'subcategory_name', 'category_id']);

        return $dataTable->render('product::products.index', compact('categories', 'subcategories'));
    }


    public function create() {
        abort_if(Gate::denies('create_products'), 403);
        $racks = Rack::where('status', 'Active')->distinct()->orderBy('rack_id')->pluck('rack_id');
        $bins = \Modules\Bin\Entities\Bin::where('status', 'active')->distinct()->orderBy('bin_id')->pluck('bin_id');
        $suppliers = \Modules\People\Entities\Supplier::where('status', 'active')->orderBy('supplier_name')->pluck('supplier_name', 'id');

        return view('product::products.create', compact('racks', 'bins', 'suppliers'));
    }

    /**
     * AJAX: Check whether a product code already exists.
     * Query params: code, exclude_id (optional)
     */
    public function checkCode(Request $request)
    {
        // Supports either a single `code` or an array `codes[]`.
        $exclude = $request->get('exclude_id');

        $codes = $request->has('codes') ? (array) $request->get('codes') : [];
        if (empty($codes) && $request->has('code')) {
            $codes = [ (string) $request->get('code') ];
        }

        $codes = array_values(array_filter(array_map('trim', $codes), fn($c) => $c !== ''));

        if (empty($codes)) {
            return response()->json(['exists' => false, 'conflicts' => []]);
        }

        $conflicts = [];

        // Limit number of codes checked at once to avoid abuse
        $MAX_CODES = 50;
        if (count($codes) > $MAX_CODES) {
            $codes = array_slice($codes, 0, $MAX_CODES);
        }

        // Query products table in bulk
        $productQuery = Product::whereIn('product_code', $codes)->select('id', 'product_code', 'product_name');
        if ($exclude) {
            $productQuery->where('id', '!=', $exclude);
        }
        $products = $productQuery->get()->keyBy('product_code');

        // Query product_codes table in bulk and eager-load product name
        $pcQuery = ProductCode::whereIn('code', $codes)->select('id', 'product_id', 'code');
        if ($exclude) {
            $pcQuery->where('product_id', '!=', $exclude);
        }
        $pcs = $pcQuery->with('product:id,product_name')->get()->groupBy('code');

        foreach ($codes as $c) {
            if (isset($products[$c])) {
                $p = $products[$c];
                $conflicts[] = ['code' => $c, 'type' => 'product', 'id' => $p->id, 'name' => $p->product_name];
                continue;
            }

            if (isset($pcs[$c]) && $pcs[$c]->isNotEmpty()) {
                // pick first matching product_code entry
                $entry = $pcs[$c]->first();
                $p = $entry->product;
                $conflicts[] = ['code' => $c, 'type' => 'product_code', 'id' => $p->id ?? null, 'name' => $p->product_name ?? null];
            }
        }

        return response()->json(['exists' => !empty($conflicts), 'conflicts' => $conflicts]);
    }

    /**
     * AJAX: product search for Select2 autocomplete.
     * Query param: q
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['results' => []]);
        }

        // Search by product_name or product_code or codes in product_codes table
        $products = Product::query()
            ->where('product_name', 'like', "%{$q}%")
            ->orWhere('product_code', 'like', "%{$q}%")
            ->select('id', 'product_code', 'product_name')
            ->limit(25)
            ->get();

        // Additionally search product_codes table for matching codes and include linked products
        $codes = \Modules\Product\Entities\ProductCode::where('code', 'like', "%{$q}%")
            ->with('product:id,product_name,product_code')
            ->limit(25)
            ->get();

        $results = [];
        foreach ($products as $p) {
            $results[] = ['id' => $p->id, 'text' => ($p->product_code ? ($p->product_code . ' — ') : '') . $p->product_name];
        }

        foreach ($codes as $c) {
            if ($c->product) {
                $results[] = ['id' => $c->product->id, 'text' => ($c->product->product_code ? ($c->product->product_code . ' — ') : '') . $c->product->product_name];
            }
        }

        // Deduplicate by id while preserving order
        $seen = [];
        $uniq = [];
        foreach ($results as $r) {
            if (isset($seen[$r['id']])) continue;
            $seen[$r['id']] = true;
            $uniq[] = $r;
            if (count($uniq) >= 25) break;
        }

        return response()->json(['results' => $uniq]);
    }


    public function store(StoreProductRequest $request) {
        // Validate subcategory_id is a valid foreign key for the selected category_id
        $subcategoryId = $request->input('subcategory_id');
        $categoryId = $request->input('category_id');
        $subcategory = \DB::table('subcategories')
            ->where('id', $subcategoryId)
            ->where('category_id', $categoryId)
            ->first();

        if (!$subcategory) {
            return back()->withInput()->withErrors(['subcategory_id' => 'Selected subcategory does not belong to the chosen category.']);
        }

        // Ensure subcategory_id is saved as integer foreign key
        // Exclude transient form-only fields that are not DB columns (e.g. hsn_unknown)
        $data = $request->except(['document', 'hsn_unknown', 'additional_codes']);
        $data['subcategory_id'] = (int) $subcategoryId;
        $data['buy_price'] = $request->input('buy_price');
        $data['list_price'] = $request->input('list_price');

        // Ensure numeric fields are integers where applicable
        $data['open_quantity'] = (int) $request->input('open_quantity', 0);
        $data['purchase_quantity'] = 0; // Initialize to 0
        $data['product_quantity'] = $data['open_quantity'] + $data['purchase_quantity'];
        $data['product_stock_alert'] = isset($data['product_stock_alert']) ? (int) $data['product_stock_alert'] : 0;

        // Compute re_order as (alert - stock) when stock is less than alert
        $data['re_order'] = 0;
        if ($data['product_stock_alert'] > $data['product_quantity']) {
            $data['re_order'] = $data['product_stock_alert'] - $data['product_quantity'];
        }

        // normalize supplier_id to null when empty
        $data['supplier_id'] = $request->input('supplier_id') ? (int) $request->input('supplier_id') : null;

        // Collect all codes (primary + additional)
        $primaryCode    = trim($data['product_code'] ?? '');
        $additionalCodes = array_filter(
            array_map('trim', $request->input('additional_codes', [])),
            fn($c) => $c !== ''
        );
        $allCodes = array_unique(array_merge([$primaryCode], array_values($additionalCodes)));

        // Check global uniqueness across product_codes table
        $codeConflict = ProductCode::whereIn('code', $allCodes)->first();
        if ($codeConflict) {
            toast('Code "' . $codeConflict->code . '" already exists.', 'error');
            return back()->withInput()->withErrors(['product_code' => 'One or more product codes already exist.']);
        }

        try {
            $product = null;

            DB::transaction(function () use (&$product, $data, $allCodes, $primaryCode) {
                // Create product and insert product_codes atomically
                $product = Product::create($data);

                $now = now();
                $rows = [];
                foreach ($allCodes as $code) {
                    $rows[] = [
                        'product_id' => $product->id,
                        'code'       => $code,
                        'is_primary' => ($code === $primaryCode) ? 1 : 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if (!empty($rows)) {
                    ProductCode::insert($rows);
                }
            });

            // Handle media after successful DB transaction to avoid orphaned files on rollback
            if ($request->has('document')) {
                foreach ($request->input('document', []) as $file) {
                    $product->addMedia(Storage::path('temp/dropzone/' . $file))->toMediaCollection('images');
                }
            }

            toast('Product Created!', 'success');

            return redirect()->route('products.index');
        } catch (QueryException $e) {
            \Log::error('Error creating product: ' . $e->getMessage());

            $errors = [];
            if (Product::where('product_code', $request->input('product_code'))->exists()) {
                $errors['product_code'] = 'Product code already exists.';
                // show specific toast like CategoriesController
                toast('Product code already exists.', 'error');
            }
            if (Product::where('product_name', $request->input('product_name'))->exists()) {
                $errors['product_name'] = 'Product name already exists.';
                toast('Product name already exists.', 'error');
            }
            if (empty($errors)) {
                $errors['error'] = 'Failed to create product: ' . $e->getMessage();
                toast('Failed to create product.', 'error');
            }

            return back()->withErrors($errors)->withInput();
        }
    }


    public function show(Product $product) {
        abort_if(Gate::denies('show_products'), 403);

        return view('product::products.show', compact('product'));
    }


    public function edit(Product $product) {
    abort_if(Gate::denies('edit_products'), 403);
    $racks = Rack::where('status', 'Active')->distinct()->orderBy('rack_id')->pluck('rack_id');
    $bins = \Modules\Bin\Entities\Bin::where('status', 'active')->distinct()->orderBy('bin_id')->pluck('bin_id');
    $suppliers = \Modules\People\Entities\Supplier::where('status', 'active')->orderBy('supplier_name')->pluck('supplier_name', 'id');
    $productCodes = $product->productCodes()->orderByDesc('is_primary')->get(); 

    return view('product::products.edit', compact('product', 'racks', 'bins', 'suppliers', 'productCodes'));
    }


    public function update(UpdateProductRequest $request, Product $product) {
        $data = $request->except(['document', 'hsn_unknown', 'additional_codes']);
        // normalize supplier_id to null when empty
        $data['supplier_id'] = $request->input('supplier_id') ? (int) $request->input('supplier_id') : null;
    $data['buy_price'] = $request->input('buy_price');
    $data['list_price'] = $request->input('list_price');
    $data['open_quantity'] = (int) $request->input('open_quantity', 0);

    // Normalize quantities to integers and recompute re_order to keep DB consistent
    $data['purchase_quantity'] = $product->purchase_quantity ?? 0; // Keep existing
    $data['product_quantity'] = $data['open_quantity'] + $data['purchase_quantity'];
    $data['product_stock_alert'] = isset($data['product_stock_alert']) ? (int) $data['product_stock_alert'] : $product->product_stock_alert;
    // Compute re_order as (alert - stock) when stock is less than alert
    $data['re_order'] = 0;
    if ($data['product_stock_alert'] > $data['product_quantity']) {
        $data['re_order'] = $data['product_stock_alert'] - $data['product_quantity'];
    }

        // Collect all codes (primary + additional)
        $primaryCodeUpd = trim($data['product_code'] ?? '');
        $additionalCodesUpd = array_filter(
            array_map('trim', $request->input('additional_codes', [])),
            fn($c) => $c !== ''
        );
        $allCodesUpd = array_unique(array_merge([$primaryCodeUpd], array_values($additionalCodesUpd)));

        // Ensure no code belongs to a DIFFERENT product
        $conflict = ProductCode::whereIn('code', $allCodesUpd)
            ->where('product_id', '!=', $product->id)
            ->first();
        if ($conflict) {
            toast('Code "' . $conflict->code . '" is already used by another product.', 'error');
            return back()->withInput()->withErrors(['product_code' => 'One or more product codes already belong to another product.']);
        }

        try {
            DB::transaction(function () use ($product, $data, $allCodesUpd, $primaryCodeUpd) {
                $product->update($data);

                // Sync product_codes: reuse deleted rows for new codes where possible to preserve IDs
                $existing = $product->productCodes()->get();
                $existingCodes = $existing->pluck('code')->toArray();
                $toDelete = array_values(array_diff($existingCodes, $allCodesUpd));
                $toAdd    = array_values(array_diff($allCodesUpd, $existingCodes));

                // Try to reuse rows that would be deleted for new codes (preserves product_code IDs)
                if (!empty($toAdd) && !empty($toDelete)) {
                    $deleteRows = ProductCode::where('product_id', $product->id)
                        ->whereIn('code', $toDelete)
                        ->get()
                        ->values();

                    foreach ($toAdd as $idx => $newCode) {
                        $reusable = $deleteRows->shift();
                        if ($reusable) {
                            $reusable->code = $newCode;
                            $reusable->is_primary = 0;
                            $reusable->save();
                            // remove one entry from toDelete so it's not deleted later
                            array_shift($toDelete);
                            unset($toAdd[$idx]);
                        }
                    }
                    // reindex $toAdd
                    $toAdd = array_values($toAdd);
                }

                // Any remaining toDelete should be removed
                if (!empty($toDelete)) {
                    ProductCode::where('product_id', $product->id)->whereIn('code', $toDelete)->delete();
                }

                // Insert any remaining new codes
                if (!empty($toAdd)) {
                    $now = now();
                    $rows = array_map(function($code) use ($product, $now) {
                        return [
                            'product_id' => $product->id,
                            'code'       => $code,
                            'is_primary' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }, $toAdd);
                    ProductCode::insert($rows);
                }

                // Reset then set is_primary
                ProductCode::where('product_id', $product->id)->update(['is_primary' => false]);
                ProductCode::where('product_id', $product->id)
                    ->where('code', $primaryCodeUpd)
                    ->update(['is_primary' => true]);
            });

            // Handle media after successful DB transaction to avoid orphaned files on rollback
            if ($request->has('document')) {
                if (count($product->getMedia('images')) > 0) {
                    foreach ($product->getMedia('images') as $media) {
                        if (!in_array($media->file_name, $request->input('document', []))) {
                            $media->delete();
                        }
                    }
                }

                $media = $product->getMedia('images')->pluck('file_name')->toArray();

                foreach ($request->input('document', []) as $file) {
                    if (count($media) === 0 || !in_array($file, $media)) {
                        $product->addMedia(Storage::path('temp/dropzone/' . $file))->toMediaCollection('images');
                    }
                }
            }

            toast('Product Updated!', 'info');

            return redirect()->route('products.index');
        } catch (QueryException $e) {
            \Log::error('Error updating product: ' . $e->getMessage());

            $errors = [];
            if (Product::where('product_code', $request->input('product_code'))->where('id', '!=', $product->id)->exists()) {
                $errors['product_code'] = 'Product code already exists.';
                toast('Product code already exists.', 'error');
            }
            if (Product::where('product_name', $request->input('product_name'))->where('id', '!=', $product->id)->exists()) {
                $errors['product_name'] = 'Product name already exists.';
                toast('Product name already exists.', 'error');
            }
            if (empty($errors)) {
                $errors['error'] = 'Failed to update product: ' . $e->getMessage();
                toast('Failed to update product.', 'error');
            }

            return back()->withErrors($errors)->withInput();
        }
    }


    public function destroy(Product $product) {
        abort_if(Gate::denies('delete_products'), 403);

        // Check if product is referenced in any transactions
        $reasons = [];
        if ($product->saleDetails()->exists()) {
            $reasons[] = 'sales';
        }
        if ($product->purchaseDetails()->exists()) {
            $reasons[] = 'purchases';
        }
        if ($product->saleReturnDetails()->exists()) {
            $reasons[] = 'sales returns';
        }
        if ($product->purchaseReturnDetails()->exists()) {
            $reasons[] = 'purchase returns';
        }
        if ($product->quotationDetails()->exists()) {
            $reasons[] = 'quotations';
        }
        // Check adjustments referencing this product
        if (\Modules\Adjustment\Entities\AdjustedProduct::where('product_id', $product->id)->exists()) {
            $reasons[] = 'adjustments';
        }

        if (!empty($reasons)) {
            $message = 'Cannot delete product because it is referenced in: ' . implode(', ', $reasons) . '. Please delete the related transactions first.';
            toast($message, 'error');
            return redirect()->route('products.index');
        }

        $product->delete();

        toast('Product Deleted!', 'warning');

        return redirect()->route('products.index');
    }
}
