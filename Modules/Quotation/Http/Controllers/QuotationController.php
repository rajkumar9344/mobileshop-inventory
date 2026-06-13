<?php

namespace Modules\Quotation\Http\Controllers;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Modules\People\Entities\Customer;
use Modules\Product\Entities\Product;
use Modules\Quotation\DataTables\QuotationsDataTable;
use Modules\Quotation\Entities\Quotation;
use Modules\Quotation\Entities\QuotationDetails;
use Modules\Quotation\Http\Requests\StoreQuotationRequest;
use Modules\Quotation\Http\Requests\UpdateQuotationRequest;
use App\Services\CartItemCalculator;
use App\Services\ProductCodeResolver;
use Illuminate\Validation\ValidationException;

class QuotationController extends Controller
{

    public function index(QuotationsDataTable $dataTable) {
        abort_if(Gate::denies('access_quotations'), 403);

        return $dataTable->render('quotation::index');
    }


    public function create() {
        abort_if(Gate::denies('create_quotations'), 403);

        Cart::instance('quotation')->destroy();

        return view('quotation::create');
    }


    public function store(StoreQuotationRequest $request) {
        // Check if this is updating an existing draft
        $existingDraft = null;
        if ($request->has('draft_id') && $request->draft_id) {
            $existingDraft = Quotation::where('id', $request->draft_id)
                ->where('status', 'Draft')
                ->first();
        }

        // Safety guard: never proceed when quotation cart is empty.
        if (Cart::instance('quotation')->content()->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Please add at least one product before saving the quotation.'
            ]);
        }

        try {
            $resolver = new ProductCodeResolver();
            $resolver->preload(Cart::instance('quotation')->content()->pluck('id')->unique()->toArray());

            DB::transaction(function () use ($request, $existingDraft, $resolver) {
            if ($request->customer_type === 'existing') {
                $custId = $request->customer_id;
                $custName = Customer::findOrFail($custId)->customer_name;
            } else {
                $custId = null;
                $custName = $request->customer_name;
            }

            // Compute overall totals (centralized helper)
            $overall = $this->computeOverallFromCart($request);
            $overall_nos = $overall['overall_nos'];
            $overall_quantity = $overall['overall_quantity'];
            $overall_gross_amount = $overall['overall_gross_amount'];
            $overall_tax_amount = $overall['overall_tax_amount'];
            $overall_taxable_amount = $overall['overall_taxable_amount'];
            $overall_cgst = $overall['overall_cgst'];
            $overall_sgst = $overall['overall_sgst'];
            $overall_igst = $overall['overall_igst'];
            $overall_amount = $overall['overall_amount'];
            $submitted_discount = $overall['submitted_discount'];
            $total_amount = $overall['total_amount'];
            $overall_net_rate = $overall['overall_net_rate'];

            $quotationData = [
                'date' => $request->date,
                'customer_type' => $request->customer_type,
                'customer_id' => $custId,
                'customer_name' => $custName,
                'contact_phone' => $request->contact_phone,
                'contact_email' => $request->contact_email,
                'contact_address' => $request->contact_address,
                'tax_percentage' => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount' => $request->shipping_amount,
                'total_amount' => $request->total_amount,
                'status' => $request->status ?? 'Pending', // Default to Pending if not provided
                'note' => $request->note,
                'tax_amount' => $overall_tax_amount,
                'discount_amount' => ($submitted_discount ?? 0),
                'overall_nos' => $overall_nos,
                'overall_quantity' => $overall_quantity,
                'overall_gross_amount' => $overall_gross_amount,
                'overall_taxable_amount' => max(0, $overall_taxable_amount),
                'overall_cgst' => $overall_cgst,
                'overall_sgst' => $overall_sgst,
                'overall_igst' => $overall_igst,
                'overall_tax_amount' => $overall_tax_amount,
                'overall_tcs_percent' => $request->overall_tcs_percent ?? 0,
                'overall_amount' => $overall_amount,
                'overall_other' => $request->overall_other ?? 0,
                'overall_adj' => $request->overall_adj ?? 0,
                'overall_net_rate' => $overall_net_rate,
                'total_amount' => $total_amount,
                'reduce_stock' => $request->reduce_stock ? 1 : 0,
            ];

            // debug logs removed — kept calculations centralized in helper

            if ($existingDraft) {
                // Update existing draft to final quotation
                $quotation = $existingDraft;
                $quotation->update($quotationData);

                // Delete existing quotation details and recreate
                $quotation->quotationDetails()->delete();
            } else {
                // Create new quotation
                $quotation = Quotation::create($quotationData);
            }

            // If reduce_stock requested, validate availability first using row locks
            $cartInstance = Cart::instance('quotation');
            if ($request->reduce_stock) {
                foreach ($cartInstance->content() as $cart_item) {
                    $product = Product::lockForUpdate()->findOrFail($cart_item->id);
                    $available = ($product->open_quantity ?? 0) + ($product->purchase_quantity ?? 0);
                    if ($available < $cart_item->qty) {
                        throw new \Exception("Insufficient stock for product '{$product->product_name}'. Available: {$available}, Requested: {$cart_item->qty}");
                    }
                }
            }

                foreach ($cartInstance->content() as $cart_item) {
                $product = Product::with('category')->findOrFail($cart_item->id);
                $options = $cart_item->options;

                $vals = $this->computeItemValues($cart_item, $product);

                $pcId = $resolver->resolve($cart_item->id, $options->code ?? null);

                QuotationDetails::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $cart_item->id,
                    'product_name' => $cart_item->name,
                    'product_code' => $options->code,
                    'product_code_id' => $pcId,
                    'quantity' => $cart_item->qty,
                    'price' => $cart_item->price,
                    'unit_price' => $vals['unit_price'],
                    'sub_total' => $vals['sub_total'],
                    'product_discount_amount' => $vals['discount_amount'],
                    'product_discount_type' => $options->product_discount_type ?? 'percentage',
                    'discount_percent' => (float) ($options->product_discount_percent ?? 0),
                    'product_tax_amount' => $vals['tax_amount'],
                    'category' => $options->category ?? null,
                    'hsn' => $options->hsn ?? null,
                    'mrp' => $vals['mrp'],
                    'rate' => $vals['rate'],
                    'tax_percentage' => $vals['tax_percentage'],
                    'tax_amount' => $vals['tax_amount'],
                    'cash_discount_percentage' => $vals['cash_discount_percentage'],
                    'cash_discount_amount' => $vals['cash_discount_amount'],
                    'discount_amount' => $vals['discount_amount'],
                    'discount_type' => $options->discount_type ?? 'fixed',
                ]);

                // Reduce stock using Sale logic (decrement open_quantity first, then purchase_quantity)
                if ($request->reduce_stock) {
                    $product = \Modules\Product\Entities\Product::lockForUpdate()->findOrFail($cart_item->id);
                    $qty = $cart_item->qty;
                    $product->reserveStock($qty);
                }
            }

            $cartInstance->destroy();
            });

            toast('Quotation Created!', 'success');

            return redirect()->route('quotations.index');
        } catch (\Exception $e) {
            toast($e->getMessage(), 'error');
            return redirect()->back()->withInput();
        }
    }

    public function autoSaveDraft(Request $request) {
        try {
            // Basic validation - require cart items to save draft
            $cart_items = Cart::instance('quotation')->content();
            $hasCartItems = !$cart_items->isEmpty();

            if (!$hasCartItems) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient data to save draft'
                ], 400);
            }

            // Check for meaningful customer data (existing vs new customer logic)
            $hasCustomerData = false;
            if ($request->customer_type === 'existing' && $request->customer_id) {
                $hasCustomerData = true;
            } elseif ($request->customer_type === 'new' && $request->customer_name && trim($request->customer_name) !== '') {
                $hasCustomerData = true;
            }

            // For quotations, we allow saving draft without customer data (like sales/purchases)
            // if (!$hasCustomerData) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Customer information is required to save draft'
            //     ], 400);
            // }

            // Check if this is an update to existing draft
            $quotation = null;
            if ($request->has('draft_id') && $request->draft_id) {
                $quotation = Quotation::where('id', $request->draft_id)
                    ->where('status', 'Draft')
                    ->first();

                // If quotation exists but is not a draft, don't allow auto-save update
                if ($quotation && $quotation->status !== 'Draft') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot auto-save non-draft quotations'
                    ], 400);
                }
            }

            $resolver = new ProductCodeResolver();
            $resolver->preload($cart_items->pluck('id')->unique()->toArray());

            DB::transaction(function () use ($request, &$quotation, $cart_items, $resolver) {

                if ($request->customer_type === 'existing') {
                    $custId = $request->customer_id;
                    if ($custId) {
                        $customer = Customer::find($custId);
                        $custName = $customer ? $customer->customer_name : '';
                    } else {
                        $custId = null;
                        $custName = '';
                    }
                } else {
                    $custId = null;
                    $custName = $request->customer_name ?? '';
                }

                // Compute overall calculations from cart content
                // Compute overall totals using helper
                $overall = $this->computeOverallFromCart($request);
                $overall_nos = $overall['overall_nos'];
                $overall_quantity = $overall['overall_quantity'];
                $overall_gross_amount = $overall['overall_gross_amount'];
                $overall_tax_amount = $overall['overall_tax_amount'];
                $overall_taxable_amount = $overall['overall_taxable_amount'];
                $overall_cgst = $overall['overall_cgst'];
                $overall_sgst = $overall['overall_sgst'];
                $overall_igst = $overall['overall_igst'];
                $overall_amount = $overall['overall_amount'];
                $submitted_discount = $overall['submitted_discount'];
                $total_amount = $overall['total_amount'];
                $overall_net_rate = $overall['overall_net_rate'];

                $quotationData = [
                    'date' => $request->date,
                    'customer_type' => $request->customer_type,
                    'customer_id' => $custId,
                    'customer_name' => $custName,
                    'contact_phone' => $request->contact_phone,
                    'contact_email' => $request->contact_email,
                    'contact_address' => $request->contact_address,
                    'tax_percentage' => $request->tax_percentage,
                    'discount_percentage' => $request->discount_percentage,
                    'shipping_amount' => $request->shipping_amount,
                    'total_amount' => $total_amount,
                    'status' => 'Draft',
                    'note' => $request->note,
                    'tax_amount' => $overall_tax_amount,
                    'discount_amount' => ($submitted_discount ?? 0),
                    'overall_nos' => $overall_nos,
                    'overall_quantity' => $overall_quantity,
                    'overall_gross_amount' => $overall_gross_amount,
                    'overall_taxable_amount' => max(0, $overall_taxable_amount),
                    'overall_cgst' => $overall_cgst,
                    'overall_sgst' => $overall_sgst,
                    'overall_igst' => $overall_igst,
                    'overall_tax_amount' => $overall_tax_amount,
                    'overall_tcs_percent' => $request->overall_tcs_percent ?? 0,
                    'overall_amount' => $overall_amount,
                    'overall_other' => $request->overall_other ?? 0,
                    'overall_adj' => $request->overall_adj ?? 0,
                    'overall_net_rate' => $overall_net_rate,
                    'reduce_stock' => $request->reduce_stock ? 1 : 0,
                ];

                if ($quotation) {
                    // Update existing draft
                    $quotation->update($quotationData);

                    // Delete existing quotation details and recreate
                    $quotation->quotationDetails()->delete();
                } else {
                    // Create new draft
                    $quotation = Quotation::create($quotationData);
                }

                // Create quotation details
                foreach ($cart_items as $cart_item) {
                    $product = Product::with('category')->findOrFail($cart_item->id);
                    $vals = $this->computeItemValues($cart_item, $product);

                    $pcId = $resolver->resolve($cart_item->id, $cart_item->options->code ?? null);

                    QuotationDetails::create([
                        'quotation_id' => $quotation->id,
                        'product_id' => $cart_item->id,
                        'product_name' => $cart_item->name,
                        'product_code' => $cart_item->options->code ?? null,
                        'product_code_id' => $pcId,
                        'quantity' => $cart_item->qty,
                        'price' => $cart_item->price,
                        'unit_price' => $vals['unit_price'],
                        'sub_total' => $vals['sub_total'],
                        'product_discount_amount' => $vals['discount_amount'],
                        'product_discount_type' => $cart_item->options->product_discount_type ?? 'percentage',
                        'discount_percent' => (float) ($cart_item->options->product_discount_percent ?? 0),
                        'product_tax_amount' => $vals['tax_amount'],
                        'category' => $cart_item->options->category ?? null,
                        'hsn' => $cart_item->options->hsn ?? null,
                        'mrp' => $vals['mrp'],
                        'rate' => $vals['rate'],
                        'tax_percentage' => $vals['tax_percentage'],
                        'tax_amount' => $vals['tax_amount'],
                        'cash_discount_percentage' => $vals['cash_discount_percentage'],
                        'cash_discount_amount' => $vals['cash_discount_amount'],
                        'discount_amount' => $vals['discount_amount'],
                        'discount_type' => $cart_item->options->discount_type ?? 'fixed',
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Draft saved successfully',
                'draft_id' => $quotation->id ?? null
            ]);

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Auto-save draft DB error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to save draft. Please try again or contact support if the problem persists.'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Auto-save draft failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to save draft. Please try again.'
            ], 500);
        }
    }


    public function show(Quotation $quotation) {
        abort_if(Gate::denies('show_quotations'), 403);

        $customer = null;
        if (!empty($quotation->customer_id)) {
            $customer = Customer::find($quotation->customer_id);
        }

        // Use an isolated cart instance so reading a quotation does not overwrite
        // the 'quotation' cart used by the Create / Edit pages.
        $quotation_details = $quotation->quotationDetails;

        Cart::instance('quotation_view')->destroy();

        $cart = Cart::instance('quotation_view');

        // Batch-load all products in one query to avoid N+1
        $productIds = $quotation_details->pluck('product_id')->all();
        $products   = Product::with('category')->whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($quotation_details as $quotation_detail) {
            $product = $products->get($quotation_detail->product_id);

            $price = $quotation_detail->price;
            $mrp = $quotation_detail->mrp ?? ($product?->mrp ?? 0);
            $product_discount_amount = $quotation_detail->product_discount_amount;
            $discount_percent = $quotation_detail->discount_percent ?? null;

            $cart->add([
                'id' => $quotation_detail->product_id,
                'name' => $quotation_detail->product_name,
                'qty' => $quotation_detail->quantity,
                'price' => $price,
                'weight' => 1,
                'options' => [
                    'product_discount' => $product_discount_amount,
                    'product_discount_type' => $quotation_detail->product_discount_type,
                    'sub_total' => $quotation_detail->sub_total,
                    'code' => ($quotation_detail->productCode->code ?? $quotation_detail->product_code),
                    'stock' => $product ? $product->product_quantity : 0,
                    'product_tax' => $quotation_detail->product_tax_amount ?? 0,
                    'unit_price' => $quotation_detail->unit_price ?? $price,
                    'category' => $quotation_detail->category ?? '-',
                    'hsn' => $quotation_detail->hsn ?? '',
                    'unit' => $quotation_detail->unit ?? 'Nos',
                    'product_discount_percent' => $discount_percent ?? 0,
                    'mrp' => $mrp,
                    'cash_discount_percent' => $quotation_detail->cash_discount_percentage ?? 0,
                    'cash_discount_amount' => $quotation_detail->cash_discount_amount ?? 0,
                    'rate' => $quotation_detail->rate ?? $quotation_detail->unit_price ?? $price,
                    'rate_before_discount' => $quotation_detail->rate ?? $quotation_detail->unit_price ?? $price,
                    'rate_type' => $quotation_detail->rate_type ?? 'N',
                    'tax_percent' => $quotation_detail->tax_percentage ?? 0,
                    'gst_percent' => $quotation_detail->tax_percentage ?? 0,
                    'tax_amount' => $quotation_detail->tax_amount ?? 0,
                    'amount' => $quotation_detail->sub_total ?? 0,
                ]
            ]);
        }

        $readonly = true;
        $cartInstance = 'quotation_view';
        return view('quotation::edit', compact('quotation', 'customer', 'readonly', 'cartInstance'));
    }


    public function edit(Quotation $quotation) {
        abort_if(Gate::denies('edit_quotations'), 403);

        $quotation_details = $quotation->quotationDetails;

        Cart::instance('quotation_edit')->destroy();

        $cart = Cart::instance('quotation_edit');

        // Batch-load all products in one query to avoid N+1
        $productIds = $quotation_details->pluck('product_id')->all();
        $products   = Product::with('category')->whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($quotation_details as $quotation_detail) {
            $product = $products->get($quotation_detail->product_id);

            $price = $quotation_detail->price; // rupees (accessor)
            $mrp_rupee = $quotation_detail->mrp ?? ($product?->mrp ?? 0); // mrp accessor returns rupees if present
            $product_discount_amount = $quotation_detail->product_discount_amount; // rupees
            $product_discount_type = $quotation_detail->product_discount_type;

            // Use stored discount_percent directly from database; fall back to calculation for old records
            $stored_percent = $quotation_detail->discount_percent ?? null;
            if ($product_discount_type === 'percentage' && $stored_percent !== null && $stored_percent > 0) {
                $discount_percent = (float) $stored_percent;
            } elseif ($product_discount_type === 'percentage' && !empty($mrp_rupee) && $mrp_rupee > 0) {
                // Backward compatibility: calculate from amount for old records
                $discount_percent = round(($product_discount_amount / $mrp_rupee) * 100, 4);
            } else {
                $discount_percent = 0;
            }

            $cart->add([
                'id'      => $quotation_detail->product_id,
                'name'    => $quotation_detail->product_name,
                'qty'     => $quotation_detail->quantity,
                'price'   => $quotation_detail->price,
                'weight'  => 1,
                'options' => [
                    'product_discount' => $product_discount_amount,
                    'product_discount_type' => $product_discount_type,
                    'product_discount_percent' => $discount_percent,
                    'sub_total'   => $quotation_detail->sub_total,
                    'code'        => ($quotation_detail->productCode->code ?? $quotation_detail->product_code),
                    'stock'       => $product?->product_quantity ?? 0,
                    'product_tax' => $quotation_detail->product_tax_amount,
                    'unit_price'  => $quotation_detail->unit_price,
                    'category' => $quotation_detail->category ?: optional($product?->category)->category_name,
                    'hsn' => $quotation_detail->hsn ?? $product?->hsn,
                    'mrp' => $quotation_detail->mrp ?: $product?->mrp,
                    'rate' => $quotation_detail->rate,
                    'rate_before_discount' => $quotation_detail->rate ?? ($quotation_detail->mrp ?: $product?->mrp),
                    'tax_percent' => $quotation_detail->tax_percentage,
                    'gst_percent' => $quotation_detail->tax_percentage,
                    'tax_amount' => $quotation_detail->tax_amount,
                    'cash_discount_percent' => $quotation_detail->cash_discount_percentage,
                    'cash_discount_amount' => $quotation_detail->cash_discount_amount,
                    'discount_amount' => $quotation_detail->discount_amount,
                    'discount_type' => $quotation_detail->discount_type,
                ]
            ]);
        }

        $cartInstance = 'quotation_edit';
        return view('quotation::edit', compact('quotation', 'cartInstance'));
    }


    public function update(UpdateQuotationRequest $request, Quotation $quotation) {
        // Safety guard: abort update when edit cart is empty.
        // Prevents deleting existing quotation details and recreating zero rows.
        if (Cart::instance('quotation_edit')->content()->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Cannot update quotation with an empty cart. Please add products and try again.'
            ]);
        }

        try {
            $resolver = new ProductCodeResolver();
            $resolver->preload(Cart::instance('quotation_edit')->content()->pluck('id')->unique()->toArray());

            DB::transaction(function () use ($request, $quotation, $resolver) {
            // Capture previous details & reduce_stock state
            $previousDetails = $quotation->quotationDetails->pluck('quantity', 'product_id')->toArray();
            $wasReduced = (bool) ($quotation->reduce_stock ?? false);

            // Build new details map from current cart (for reference only)
            $cartContents = Cart::instance('quotation_edit')->content();

            // Stock adjustments: follow Sale approach
            // If previously reduced, first restore previous quantities to purchase_quantity
            if ($wasReduced) {
                foreach ($previousDetails as $pid => $qty) {
                    $product = \Modules\Product\Entities\Product::lockForUpdate()->find($pid);
                    if ($product) {
                        $product->addPurchaseStock($qty);
                    }
                }
            }

            // If the new request asks to reduce stock, we will decrement when we recreate the new details below.
            // This mirrors the SaleController update flow where previous quantities are restored first and
            // then the new sale details decrement stock deterministically during creation.

            // Delete old details
            foreach ($quotation->quotationDetails as $quotation_detail) {
                $quotation_detail->delete();
            }

            if ($request->customer_type === 'existing') {
                $custId = $request->customer_id;
                $custName = Customer::findOrFail($custId)->customer_name;
            } else {
                $custId = null;
                $custName = $request->customer_name;
            }

            // Compute overall totals using helper
            $overall = $this->computeOverallFromCart($request, 'quotation_edit');
            $overall_nos = $overall['overall_nos'];
            $overall_quantity = $overall['overall_quantity'];
            $overall_gross_amount = $overall['overall_gross_amount'];
            $overall_tax_amount = $overall['overall_tax_amount'];
            $overall_taxable_amount = $overall['overall_taxable_amount'];
            $overall_cgst = $overall['overall_cgst'];
            $overall_sgst = $overall['overall_sgst'];
            $overall_igst = $overall['overall_igst'];
            $overall_amount = $overall['overall_amount'];
            $submitted_discount = $overall['submitted_discount'];
            $total_amount = $overall['total_amount'];

            $quotation->update([
                'date' => $request->date,
                'reference' => $request->reference,
                'customer_type' => $request->customer_type,
                'customer_id' => $custId,
                'customer_name' => $custName,
                'contact_phone' => $request->contact_phone,
                'contact_email' => $request->contact_email,
                'contact_address' => $request->contact_address,
                'tax_percentage' => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount' => $request->shipping_amount,
                'total_amount' => $total_amount,
                'status' => $request->status,
                'note' => $request->note,
                'tax_amount' => $overall_tax_amount,
                'discount_amount' => ($submitted_discount ?? 0),
                'overall_nos' => $overall_nos,
                'overall_quantity' => $overall_quantity,
                'overall_gross_amount' => $overall_gross_amount,
                'overall_taxable_amount' => max(0, $overall_taxable_amount),
                'overall_cgst' => $overall_cgst,
                'overall_sgst' => $overall_sgst,
                'overall_igst' => $overall_igst,
                'overall_tax_amount' => $overall_tax_amount,
                'overall_tcs_percent' => $request->overall_tcs_percent ?? 0,
                'overall_amount' => $overall_amount,
                'overall_other' => $request->overall_other ?? 0,
                'overall_adj' => $request->overall_adj ?? 0,
                'overall_net_rate' => ($request->overall_net_rate ?? $total_amount),
                'reduce_stock' => $request->reduce_stock ? 1 : 0,
            ]);

                // If reduce_stock requested, validate availability first using row locks
                if ($request->reduce_stock) {
                    foreach ($cartContents as $cart_item) {
                        $product = Product::lockForUpdate()->findOrFail($cart_item->id);
                        $available = ($product->open_quantity ?? 0) + ($product->purchase_quantity ?? 0);
                        if ($available < $cart_item->qty) {
                            throw new \Exception("Insufficient stock for product '{$product->product_name}'. Available: {$available}, Requested: {$cart_item->qty}");
                        }
                    }
                }

                foreach ($cartContents as $cart_item) {
                    $product = Product::with('category')->findOrFail($cart_item->id);
                    $vals = $this->computeItemValues($cart_item, $product);

                    $pcId = $resolver->resolve($cart_item->id, $cart_item->options->code ?? null);

                    QuotationDetails::create([
                        'quotation_id' => $quotation->id,
                        'product_id' => $cart_item->id,
                        'product_name' => $cart_item->name,
                        'product_code' => $cart_item->options->code ?? null,
                        'product_code_id' => $pcId,
                        'quantity' => $cart_item->qty,
                        'price' => $cart_item->price,
                        'unit_price' => $vals['unit_price'],
                        'sub_total' => $vals['sub_total'],
                        'product_discount_amount' => $vals['discount_amount'],
                        'product_discount_type' => $cart_item->options->product_discount_type ?? 'percentage',
                        'discount_percent' => (float) ($cart_item->options->product_discount_percent ?? 0),
                        'product_tax_amount' => $vals['tax_amount'],
                        'category' => $cart_item->options->category ?? null,
                        'hsn' => $cart_item->options->hsn ?? null,
                        'mrp' => $vals['mrp'],
                        'rate' => $vals['rate'],
                        'tax_percentage' => $vals['tax_percentage'],
                        'tax_amount' => $vals['tax_amount'],
                        'cash_discount_percentage' => $vals['cash_discount_percentage'],
                        'cash_discount_amount' => $vals['cash_discount_amount'],
                        'discount_amount' => $vals['discount_amount'],
                        'discount_type' => $cart_item->options->discount_type ?? 'fixed',
                    ]);

                // If new request asks to reduce stock, decrement using Sale logic (open first then purchase)
                if ($request->reduce_stock) {
                    $product = \Modules\Product\Entities\Product::lockForUpdate()->findOrFail($cart_item->id);
                    $qty = $cart_item->qty;
                    $open_qty = $product->open_quantity ?? 0;
                    $purchase_qty = $product->purchase_quantity ?? 0;

                    if ($open_qty >= $qty) {
                        $product->update(['open_quantity' => $open_qty - $qty]);
                    } else {
                        $product->update([
                            'open_quantity' => 0,
                            'purchase_quantity' => max(0, $purchase_qty - ($qty - $open_qty))
                        ]);
                    }

                    $product->recalculateProductQuantity();
                }
            }

            Cart::instance('quotation_edit')->destroy();
            });

            toast('Quotation Updated!', 'info');

            return redirect()->route('quotations.index');
        } catch (\Exception $e) {
            toast($e->getMessage(), 'error');
            return redirect()->back()->withInput();
        }
    }


    public function destroy(Quotation $quotation) {
        abort_if(Gate::denies('delete_quotations'), 403);

        // If this quotation had reduced stock, restore quantities before deletion
        DB::transaction(function () use ($quotation) {
            try {
                if ($quotation->reduce_stock) {
                    foreach ($quotation->quotationDetails as $detail) {
                        try {
                            $product = Product::lockForUpdate()->find($detail->product_id);
                            if ($product) {
                                $product->addPurchaseStock($detail->quantity);
                            }
                        } catch (\Exception $e) {
                            Log::error('Failed to restore product quantity while deleting quotation', ['quotation_id' => $quotation->id, 'product_id' => $detail->product_id, 'error' => $e->getMessage()]);
                        }
                    }
                }

                $quotation->delete();
            } catch (\Exception $e) {
                Log::error('Failed to delete quotation with stock restore', ['quotation_id' => $quotation->id, 'error' => $e->getMessage()]);
                throw $e;
            }
        });

        toast('Quotation Deleted!', 'warning');

        return redirect()->route('quotations.index');
    }

    /**
     * Compute item-level values for a cart item (rate, tax, unit price, subtotal, discount).
     */
    private function computeItemValues($cart_item, $product = null): array
    {
        $vals = CartItemCalculator::compute($cart_item, $product);

        // VAT% comes from the BRD VAT% input (gst_percent), not the legacy tax_percent.
        $gstPct = (float) ($cart_item->options->gst_percent ?? $vals['tax_percent']);
        $vals['tax_percent']     = $gstPct;
        $vals['tax_amount']      = round($vals['sub_total'] * $gstPct / 100, 2);

        // Add aliases matching QuotationDetails column names
        $vals['tax_percentage']          = $gstPct;
        $vals['cash_discount_percentage'] = $vals['cash_discount_percent'];

        return $vals;
    }

    /**
     * Compute overall totals for a quotation from cart and request values.
     */
    private function computeOverallFromCart($request, string $cartInstanceName = 'quotation')
    {
        $cart       = Cart::instance($cartInstanceName);
        $cart_items = $cart->content();
        $overall_nos = $cart_items->count();
        $overall_quantity = $cart_items->sum('qty');

        $overall_gross_amount   = ($request->overall_gross_amount   ?? $cart->total());
        $overall_tax_amount     = ($request->overall_tax_amount     ?? $cart->tax());
        $overall_taxable_amount = ($request->overall_taxable_amount ?? ($cart->total() - $cart->tax()));
        $overall_cgst           = ($request->overall_cgst           ?? ($cart->tax() / 2));
        $overall_sgst           = ($request->overall_sgst           ?? ($cart->tax() / 2));
        $overall_igst           = ($request->overall_igst           ?? 0.00);
        $overall_amount         = ($request->overall_amount         ?? $overall_taxable_amount);

        $rawOverallNet = $request->overall_net_rate ?? $request->total_amount ?? 0;
        $base_total = floatval(str_replace([',', settings()->currency->symbol], '', (string)$rawOverallNet));

        $submitted_discount = 0;
        if (isset($request->discount_amount) && is_numeric(str_replace(',', '', (string)$request->discount_amount)) && (float)str_replace(',', '', (string)$request->discount_amount) > 0) {
            $submitted_discount = (float) str_replace(',', '', (string)$request->discount_amount);
        } else {
            $cartDiscount = (float) $cart->discount();
            if ($cartDiscount > 0) {
                $submitted_discount = $cartDiscount;
            }
        }

        $total_amount = $base_total - $submitted_discount;
        // Keep 2 decimals so the list/grand total matches the cart (e.g. 787.50, not 788).
        $total_amount = round($total_amount, 2);

        // Ensure overall_net_rate is numeric before passing to round()
        $rawOverallNet2 = $request->overall_net_rate ?? $total_amount;
        $overall_net_rate = round(floatval(str_replace([',', settings()->currency->symbol], '', (string)$rawOverallNet2)), 2);

        return [
            'overall_nos' => $overall_nos,
            'overall_quantity' => $overall_quantity,
            'overall_gross_amount' => $overall_gross_amount,
            'overall_tax_amount' => $overall_tax_amount,
            'overall_taxable_amount' => $overall_taxable_amount,
            'overall_cgst' => $overall_cgst,
            'overall_sgst' => $overall_sgst,
            'overall_igst' => $overall_igst,
            'overall_amount' => $overall_amount,
            'submitted_discount' => $submitted_discount,
            'total_amount' => $total_amount,
            'overall_net_rate' => $overall_net_rate,
        ];
    }
}
