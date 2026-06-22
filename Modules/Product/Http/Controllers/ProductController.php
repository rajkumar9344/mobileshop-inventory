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
use Illuminate\Database\QueryException;

class ProductController extends Controller
{

    public function index(ProductDataTable $dataTable) {
        abort_if(Gate::denies('access_products'), 403);

        $categories = \Modules\Product\Entities\Category::where('status', true)
            ->orderBy('category_name')
            ->get(['id', 'category_name']);

        return $dataTable->render('product::products.index', compact('categories'));
    }


    public function create() {
        abort_if(Gate::denies('create_products'), 403);
        return view('product::products.create');
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
        // Exclude transient form-only fields that are not DB columns
        $data = $request->except(['document', 'additional_codes', 'product_code']);

        // Ensure numeric fields are integers where applicable
        $data['open_quantity'] = (int) $request->input('open_quantity', 0);
        $data['purchase_quantity'] = 0;
        $data['product_quantity'] = $data['open_quantity'] + $data['purchase_quantity'];
        $data['product_stock_alert'] = isset($data['product_stock_alert']) ? (int) $data['product_stock_alert'] : 0;

        $data['re_order'] = 0;
        if ($data['product_stock_alert'] > $data['product_quantity']) {
            $data['re_order'] = $data['product_stock_alert'] - $data['product_quantity'];
        }

        try {
            $product = null;

            DB::transaction(function () use (&$product, $data) {
                $product = Product::create($data);

                // Auto-generate product code from the assigned ID
                $autoCode = 'P-' . str_pad($product->id, 5, '0', STR_PAD_LEFT);
                $product->product_code = $autoCode;
                $product->save();

                ProductCode::insert([
                    'product_id' => $product->id,
                    'code'       => $autoCode,
                    'is_primary' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            if ($request->has('document')) {
                foreach ($request->input('document', []) as $file) {
                    $product->addMedia(Storage::path('temp/dropzone/' . basename((string) $file)))->toMediaCollection('images');
                }
            }

            toast('Product Created!', 'success');

            return redirect()->route('products.index');
        } catch (QueryException $e) {
            \Log::error('Error creating product: ' . $e->getMessage());

            $errors = [];
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
    $productCodes = $product->productCodes()->orderByDesc('is_primary')->get();

    return view('product::products.edit', compact('product', 'productCodes'));
    }


    public function update(UpdateProductRequest $request, Product $product) {
        // Never overwrite the auto-generated product_code
        $data = $request->except(['document', 'additional_codes', 'product_code']);
        $data['open_quantity'] = (int) $request->input('open_quantity', 0);

        $data['purchase_quantity'] = $product->purchase_quantity ?? 0;
        $data['product_quantity'] = $data['open_quantity'] + $data['purchase_quantity'];
        $data['product_stock_alert'] = isset($data['product_stock_alert']) ? (int) $data['product_stock_alert'] : $product->product_stock_alert;
        $data['re_order'] = 0;
        if ($data['product_stock_alert'] > $data['product_quantity']) {
            $data['re_order'] = $data['product_stock_alert'] - $data['product_quantity'];
        }

        try {
            DB::transaction(function () use ($product, $data) {
                $product->update($data);
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
                        // basename() prevents path traversal via user-supplied filenames
                        $product->addMedia(Storage::path('temp/dropzone/' . basename((string) $file)))->toMediaCollection('images');
                    }
                }
            }

            toast('Product Updated!', 'info');

            return redirect()->route('products.index');
        } catch (QueryException $e) {
            \Log::error('Error updating product: ' . $e->getMessage());

            $errors = [];
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
