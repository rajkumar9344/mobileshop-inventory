<?php

namespace Modules\Purchase\Http\Controllers;

use Modules\Purchase\DataTables\PurchaseDataTable;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Modules\People\Entities\Supplier;
use Modules\Product\Entities\Product;
use App\Services\ProductCodeResolver;
use Modules\Purchase\Entities\Purchase;
use Modules\Purchase\Entities\PurchaseDetail;
use Modules\Purchase\Entities\PurchasePayment;
use Modules\PurchasesReceipt\Entities\PurchasesReceipt;
use Modules\PurchasesReceipt\Entities\PurchasesReceiptLine;
use Modules\Purchase\Http\Requests\StorePurchaseRequest;
use Modules\Purchase\Http\Requests\UpdatePurchaseRequest;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{

    public function index(PurchaseDataTable $dataTable) {
        abort_if(Gate::denies('access_purchases'), 403);

        return $dataTable->render('purchase::index');
    }

    /**
     * Aggregated totals for listing (supports optional year/month/day filters).
     */
    public function totals() {
        abort_if(Gate::denies('access_purchases'), 403);

        $year = request()->get('year');
        $month = request()->get('month');
        $day = request()->get('day');
        $start = request()->get('start_date');
        $end = request()->get('end_date');

        // Build base query with optional filters
        $base = Purchase::query();

        // Accept supplier_id and search term forwarded from DataTable filters (so totals match filtered listing)
        $supplierFilter = request()->get('supplier_id');
        if ($supplierFilter) {
            $base->where('supplier_id', $supplierFilter);
        }

        $searchFilter = request()->get('search');
        if ($searchFilter) {
            $term = trim($searchFilter);
            $base->where(function($q) use ($term) {
                $q->where('supplier_name', 'like', "%{$term}%")
                  ->orWhere('reference', 'like', "%{$term}%")
                  ->orWhere('area', 'like', "%{$term}%");
            });
        }

        // Exclude Draft purchases from aggregated totals (they should not affect financial summaries)
        $base->where(function($q){
            $q->whereNull('status')->orWhere('status', '!=', 'Draft');
        });

        // If a start/end date range is provided, use that (expected format: YYYY-MM-DD)
        if ($start && $end) {
            try {
                $s = \Carbon\Carbon::parse($start)->startOfDay()->toDateString();
                $e = \Carbon\Carbon::parse($end)->endOfDay()->toDateString();
                $base->whereBetween('date', [$s, $e]);
            } catch (\Exception $ex) {
                // invalid dates - fall back to legacy year/month/day
            }
        } else {
            if ($year) {
                $base->whereYear('date', $year);
            }

            if ($month) {
                $base->whereMonth('date', str_pad($month, 2, '0', STR_PAD_LEFT));
            }

            if ($day) {
                $base->whereDay('date', str_pad($day, 2, '0', STR_PAD_LEFT));
            }
        }

        $totals = [];
        $totals['overall_count'] = $base->count();

        $totals['overall_total_amount'] = $base->sum(DB::raw('COALESCE(overall_amount, total_amount)')) / 100;

        // Outstanding (sum of positive due_amounts)
        $totals['overall_outstanding'] = $base->sum(DB::raw('CASE WHEN due_amount > 0 THEN due_amount ELSE 0 END')) / 100;

        // Advances (sum of absolute negative due_amounts)
        $totals['overall_advances'] = $base->sum(DB::raw('CASE WHEN due_amount < 0 THEN -due_amount ELSE 0 END')) / 100;

        // Net balance (outstanding - advances) -- also equal to sum(due_amount)/100
        $totals['overall_balance'] = $base->sum(DB::raw('COALESCE(due_amount,0)')) / 100;

        $totals['overall_paid_amount'] = $base->sum(DB::raw('COALESCE(paid_amount,0)')) / 100;
        $totals['overall_tax_amount'] = $base->sum(DB::raw('COALESCE(overall_tax_amount, tax_amount,0)')) / 100;
        // Count distinct suppliers in the filtered set
        $totals['overall_supplier_count'] = $base->distinct()->count('supplier_id');

        return response()->json($totals);
    }


    public function create() {
        abort_if(Gate::denies('create_purchases'), 403);

        Cart::instance('purchase')->destroy();

        return view('purchase::create');
    }


    public function store(StorePurchaseRequest $request) {
        // Check if this is updating an existing draft (status can be 'Draft' or 'Pending')
        $existingDraft = null;
        if ($request->has('draft_id') && $request->draft_id) {
            $existingDraft = Purchase::where('id', $request->draft_id)
                ->whereIn('status', ['Draft', 'Pending'])
                ->first();
        }

        // Safety guard: never proceed when cart is empty.
        // Without this, existing draft details can be deleted and recreated as zero rows.
        if (Cart::instance('purchase')->content()->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Please add at least one product before saving the purchase.'
            ]);
        }

        $resolver = new ProductCodeResolver();
        $resolver->preload(Cart::instance('purchase')->content()->pluck('id')->unique()->toArray());

        DB::transaction(function () use ($request, $existingDraft, $resolver) {
            $cart = Cart::instance('purchase');
            $days = max(0, (int) ($request->days ?? 0));
            $dueDate = null;
            try {
                $dueDate = !empty($request->due_date)
                    ? \Carbon\Carbon::parse($request->due_date)->format('Y-m-d')
                    : \Carbon\Carbon::parse($request->ref_date)->addDays($days)->format('Y-m-d');
            } catch (\Throwable $e) {
                $dueDate = null;
            }
            // Coerce posted strings (may contain commas/currency) to numeric floats
            $total_amount = floatval(str_replace([',', settings()->currency->symbol], '', (string) ($request->total_amount ?? 0)));
            $paid_amount = floatval(str_replace([',', settings()->currency->symbol], '', (string) ($request->paid_amount ?? 0)));
            $discount_amount = floatval(str_replace([',', settings()->currency->symbol], '', (string) ($request->discount_amount ?? 0)));
            
            $due_amount = $total_amount - $paid_amount - $discount_amount;
            if ($due_amount == $total_amount) {
                $payment_status = 'Unpaid';
            } elseif ($due_amount > 0) {
                $payment_status = 'Partial';
            } else {
                $payment_status = 'Paid';
            }

            if ($existingDraft) {
                // Update existing draft to completed purchase
                $existingDraft->update([
                    'date' => $request->ref_date,
                    'reference' => $request->reference,
                    'supplier_id' => $request->supplier_id,
                    'supplier_name' => Supplier::findOrFail($request->supplier_id)->supplier_name,
                    'area' => $request->area,
                    'balance' => $request->balance,
                    'invoice_no' => $request->invoice_no,
                    'invoice_date' => $request->invoice_date,
                    'days' => $days,
                    'due_date' => $dueDate,
                    'tax_percentage' => $request->tax_percentage,
                    'discount_percentage' => $request->discount_percentage,
                    'shipping_amount' => $request->shipping_amount,
                    'paid_amount' => $paid_amount,
                    'total_amount' => $total_amount,
                    'due_amount' => $due_amount,
                    'status' => $request->status ?? 'Pending',
                    'payment_status' => $payment_status,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note,
                    'tax_amount' => $cart->tax(),
                    'discount_amount' => $discount_amount,
                    // Overall calculations
                    'overall_nos' => $request->overall_nos ?? 0,
                    'overall_quantity' => $request->overall_quantity ?? 0,
                    'overall_gross_amount' => $request->overall_gross_amount ?? 0,
                    'overall_taxable_amount' => $request->overall_taxable_amount ?? 0,
                    'overall_tax_amount' => $request->overall_tax_amount ?? 0,
                    'overall_amount' => $request->overall_amount ?? 0,
                ]);
                $purchase = $existingDraft;

                // Delete existing purchase details and recreate
                $purchase->purchaseDetails()->delete();
            } else {
                // Create new purchase
                $purchase = Purchase::create([
                    'date' => $request->ref_date,
                    'reference' => $request->reference,
                    'supplier_id' => $request->supplier_id,
                    'supplier_name' => Supplier::findOrFail($request->supplier_id)->supplier_name,
                    'area' => $request->area,
                    'balance' => $request->balance,
                    'invoice_no' => $request->invoice_no,
                    'invoice_date' => $request->invoice_date,
                    'days' => $days,
                    'due_date' => $dueDate,
                    'tax_percentage' => $request->tax_percentage,
                    'discount_percentage' => $request->discount_percentage,
                    'shipping_amount' => $request->shipping_amount,
                    'paid_amount' => $paid_amount,
                    'total_amount' => $total_amount,
                    'due_amount' => $due_amount,
                    'status' => $request->status ?? 'Pending',
                    'payment_status' => $payment_status,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note,
                    'tax_amount' => $cart->tax(),
                    'discount_amount' => $discount_amount,
                    // Overall calculations
                    'overall_nos' => $request->overall_nos ?? 0,
                    'overall_quantity' => $request->overall_quantity ?? 0,
                    'overall_gross_amount' => $request->overall_gross_amount ?? 0,
                    'overall_taxable_amount' => $request->overall_taxable_amount ?? 0,
                    'overall_tax_amount' => $request->overall_tax_amount ?? 0,
                    'overall_amount' => $request->overall_amount ?? 0,
                ]);
            }

            foreach ($cart->content() as $cart_item) {
                // Use rate submitted directly from the DOM (bypasses Livewire async race)
                $_submittedRateRaw = $request->input("submitted_rates.{$cart_item->id}", null);
                if ($_submittedRateRaw !== null && $_submittedRateRaw !== '') {
                    $_rateBeforeDiscount = floatval(str_replace([',', settings()->currency->symbol], '', (string)$_submittedRateRaw));
                } else {
                    $_rateBeforeDiscount = ($cart_item->options->rate_before_discount ?? $cart_item->options->rate ?? null);
                }

                // Use MRP submitted from DOM (same race-condition bypass as submitted_rates).
                // Falls back to the cart session option when the field was not changed.
                $_submittedMrp = $request->input("submitted_mrps.{$cart_item->id}", null);
                if ($_submittedMrp !== null && $_submittedMrp !== '') {
                    $_effectiveMrp = round(floatval(str_replace([',', settings()->currency->symbol], '', (string)$_submittedMrp)), 2);
                } else {
                    $_effectiveMrp = round(floatval($cart_item->options->mrp ?? 0), 2);
                }

                // Compute derived fields from authoritative rate_before_discount (server-side)
                $discountPercent = (float) ($cart_item->options->product_discount_percent ?? 0);
                $cashDiscountPercent = (float) ($cart_item->options->cash_discount_percent ?? 0);
                $cashDiscountAmount = (float) ($cart_item->options->cash_discount_amount ?? 0);
                $taxPercent = (float) ($cart_item->options->gst_percent ?? $cart_item->options->tax_percent ?? 0);
                $qty = $cart_item->qty;

                if ($_rateBeforeDiscount !== null) {
                    $perUnitAfterPercent = floatval($_rateBeforeDiscount) * (1 - ($discountPercent / 100));
                    $cashPercentAmtPerUnit = $perUnitAfterPercent * ($cashDiscountPercent / 100);
                    $cashTotalPerUnit = $cashPercentAmtPerUnit + $cashDiscountAmount;
                    $computedRate = round($perUnitAfterPercent - $cashTotalPerUnit, 2);
                    $computedAmount = round($computedRate * $qty, 2);
                    $computedTaxAmount = round($computedAmount * ($taxPercent / 100), 2);
                } else {
                    $computedRate = $cart_item->options->rate ?? 0;
                    $computedAmount = ($cart_item->options->rate ?? 0) * $qty;
                    $computedTaxAmount = ($cart_item->options->rate ?? 0) * (($taxPercent ?? 0) / 100) * $qty;
                }

                // Resolve product code id for this cart item
                $pcId = $resolver->resolve($cart_item->id, $cart_item->options->code ?? null);

                $detail = PurchaseDetail::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $cart_item->id,
                    'product_name' => $cart_item->name,
                    'product_code' => $cart_item->options->code,
                    'product_code_id' => $pcId,
                    'category' => $cart_item->options->category ?? '-',
                    'unit' => $cart_item->options->unit ?? 'Nos',
                    'quantity' => $cart_item->qty,
                    'mrp' => $_effectiveMrp,
                    'rate_before_discount' => $_rateBeforeDiscount,
                    // Persist computed values (so DB matches UI calculations)
                    'rate' => $computedRate,
                    'rate_type' => $cart_item->options->rate_type ?? 'N',
                    'tax_percent' => $taxPercent,
                    'tax_amount' => $computedTaxAmount,
                    'amount' => $computedAmount,
                    'price' => $cart_item->price,
                    // Persist per-unit and per-line calculations to match UI
                    'unit_price' => $computedRate,
                    'sub_total' => $computedAmount,
                    'product_discount_amount' => (
                        ($cart_item->options->product_discount_type ?? '') === 'percentage'
                        ? round((float)($_rateBeforeDiscount ?? 0) * ($discountPercent / 100) * $qty, 2)
                        : ($cart_item->options->product_discount ?? 0)
                    ),
                    'product_discount_type' => $cart_item->options->product_discount_type,
                    'product_tax_amount' => $computedTaxAmount,
                ]);
                // (removed temporary debug logging)

                // Always increment product quantity when a purchase detail is created.
                // This makes purchase stock updates consistent with sales (which always decrement stock).
                $product = Product::lockForUpdate()->findOrFail($cart_item->id);

                // Use helper to increment purchase stock
                $product->addPurchaseStock($cart_item->qty);

                // BRD: the entered Purchase Rate updates the product's cost in the Product Master.
                $newCost = round(floatval($_rateBeforeDiscount ?? 0), 2);
                if ($newCost > 0 && abs($newCost - round(floatval($product->product_cost ?? 0), 2)) >= 0.005) {
                    $product->product_cost = $newCost;
                    $product->save();
                }

                $product->refresh();
            }

            $cart->destroy();

            // If the purchase has any outstanding due, add it to the supplier's open balance
            if (($purchase->due_amount ?? 0) > 0) {
                $supplier = Supplier::lockForUpdate()->find($purchase->supplier_id);
                if ($supplier) {
                    $supplier->open_balance = ($supplier->open_balance ?? 0) + ($purchase->due_amount ?? 0);
                    $supplier->save();
                }
            }

            // If any amount was actually paid, create a PurchasesReceipt and PurchasePayment
            // so the payment is tracked as a receipt and applied to supplier/purchase.
            $paymentShouldApply = ($purchase->paid_amount > 0);
            if ($paymentShouldApply) {
                // create purchases receipt (auto-generated)
                $r = PurchasesReceipt::create([
                    'date' => $request->ref_date,
                    'supplier_id' => $purchase->supplier_id,
                    'particular' => 'Auto-generated from Purchase '.$purchase->reference,
                    'payment_mode' => $request->payment_method ?? null,
                    'total_amount' => 0,
                    'total_discount' => 0,
                    'created_by' => auth()->id()
                ]);

                $paymentAmount = floatval($purchase->paid_amount ?? 0);
                $billAmount = floatval($purchase->total_amount ?? 0);
                $paidBefore = 0; // at creation there were no previous payments
                $balanceBefore = $billAmount - $paidBefore;
                $displayFinal = $billAmount - ($paidBefore + $paymentAmount + 0);

                PurchasesReceiptLine::create([
                    'purchases_receipt_id' => $r->id,
                    'purchase_id' => $purchase->id,
                    'bill_ref' => $purchase->reference,
                    'bill_date' => $purchase->date,
                    'bill_amount' => $billAmount,
                    'paid_before' => $paidBefore,
                    'balance_before' => $balanceBefore,
                    'payment_amount' => $paymentAmount,
                    'discount_amount' => 0,
                    'final_balance' => $displayFinal,
                    'is_settled' => true,
                    'settled_at' => now(),
                    'settled_by' => auth()->id(),
                ]);

                // update receipt totals and reference
                $r->total_amount = $paymentAmount;
                $r->total_discount = 0;
                $r->save();
                $r->reference = 'PU-RE' . str_pad($r->id, 5, '0', STR_PAD_LEFT);
                $r->save();

                // create purchase payment with receipt reference
                PurchasePayment::create([
                    'date' => $request->ref_date,
                    'reference' => $r->reference,
                    'amount' => $paymentAmount,
                    'purchase_id' => $purchase->id,
                    'payment_method' => $request->payment_method
                ]);

                // adjust supplier balance by applied amount (lock row)
                $supplier = Supplier::lockForUpdate()->find($purchase->supplier_id);
                if ($supplier) {
                    // Clamp open balance to zero when subtracting payments - do not allow negative balances
                    $supplier->open_balance = max(0, ($supplier->open_balance ?? 0) - $paymentAmount);
                    $supplier->save();
                }
            }
        });

        toast('Purchase Created!', 'success');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Purchase Created Successfully!',
                'redirect' => route('purchases.index')
            ]);
        }

        return redirect()->route('purchases.index');
    }


    public function show(Purchase $purchase) {
        abort_if(Gate::denies('show_purchases'), 403);

        $supplier = Supplier::findOrFail($purchase->supplier_id);

        return view('purchase::show', compact('purchase', 'supplier'));
    }

    /**
     * Readonly view: reuse the edit UI but in readonly mode.
     */
    public function view(Purchase $purchase)
    {
        abort_if(Gate::denies('view_purchases'), 403);
        $purchase->load('purchaseDetails');
        $this->populatePurchaseCart($purchase, 'purchase_view');
        $cartInstance = 'purchase_view';

        return view('purchase::edit', compact('purchase', 'cartInstance'))->with('readonly', true);
    }


    /**
     * Auto-save purchase as draft via AJAX (called on page unload, back button, periodic save)
     */
    public function autoSaveDraft(Request $request) {
        abort_if(Gate::denies('create_purchases'), 403);

        try {
            // Basic validation - require cart items to save draft
            $cart_items = Cart::instance('purchase')->content();
            $hasCartItems = !$cart_items->isEmpty();

            if (!$hasCartItems) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient data to save draft'
                ], 400);
            }

            // Check if this is an update to existing draft
            $purchase = null;
            if ($request->has('draft_id') && $request->draft_id) {
                $purchase = Purchase::where('id', $request->draft_id)
                    ->where('status', 'Draft') // Purchase drafts are 'Draft'
                    ->first();

                // If purchase exists but is not a draft, don't allow auto-save update
                if ($purchase && $purchase->status !== 'Draft') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot auto-save non-draft purchases'
                    ], 400);
                }
            }

            $resolver = new ProductCodeResolver();
            $resolver->preload(Cart::instance('purchase')->content()->pluck('id')->unique()->toArray());

            DB::transaction(function () use ($request, &$purchase, $resolver) {
                $cart = Cart::instance('purchase');
                // Coerce posted strings (may contain commas/currency) to numeric floats
                $base_total = floatval(str_replace([',', settings()->currency->symbol], '', (string) ($request->overall_amount ?? $request->total_amount ?? 0)));
                $days = max(0, (int) ($request->days ?? 0));
                $dueDate = null;
                try {
                    $dueDate = !empty($request->due_date)
                        ? \Carbon\Carbon::parse($request->due_date)->format('Y-m-d')
                        : \Carbon\Carbon::parse($request->ref_date ?? now()->format('Y-m-d'))->addDays($days)->format('Y-m-d');
                } catch (\Throwable $e) {
                    $dueDate = null;
                }
                $submitted_discount = 0;
                if (isset($request->discount_amount) && is_numeric(str_replace(',', '', (string)$request->discount_amount)) && (float)str_replace(',', '', (string)$request->discount_amount) > 0) {
                    $submitted_discount = (float) str_replace(',', '', (string)$request->discount_amount);
                }
                $total_amount = $base_total - $submitted_discount;

                // Calculate due amount
                $paid_amount = floatval(str_replace([',', settings()->currency->symbol], '', (string) ($request->paid_amount ?? 0)));
                $due_amount = $total_amount - $paid_amount;
                if ($due_amount == $total_amount) {
                    $payment_status = 'Unpaid';
                } elseif ($due_amount > 0) {
                    $payment_status = 'Partial';
                } else {
                    $payment_status = 'Paid';
                }

                // Get supplier name
                $supplier_name = '';
                if ($request->supplier_id) {
                    $supplier = Supplier::find($request->supplier_id);
                    $supplier_name = $supplier ? $supplier->supplier_name : '';
                }

                $purchaseData = [
                    'date' => $request->ref_date ?? now()->format('Y-m-d'),
                    'reference' => $request->reference ?? 'DRAFT',
                    'supplier_id' => $request->supplier_id,
                    'supplier_name' => $supplier_name,
                    'area' => $request->area,
                    'balance' => $request->balance ?? 0,
                    'invoice_no' => $request->invoice_no,
                    'invoice_date' => $request->invoice_date,
                    'days' => $days,
                    'due_date' => $dueDate,
                    'tax_percentage' => $request->tax_percentage ?? 0,
                    'discount_percentage' => $request->discount_percentage ?? 0,
                    'shipping_amount' => $request->shipping_amount ?? 0,
                    'paid_amount' => $paid_amount,
                    'total_amount' => $total_amount ?? 0,
                    'due_amount' => $due_amount,
                    'status' => 'Draft', // Draft status for purchases
                    'payment_status' => $payment_status,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note,
                    'tax_amount' => $cart->tax(),
                    'discount_amount' => $submitted_discount,
                    // Overall calculations
                    'overall_nos' => $request->overall_nos ?? 0,
                    'overall_quantity' => $request->overall_quantity ?? 0,
                    'overall_gross_amount' => $request->overall_gross_amount ?? 0,
                    'overall_taxable_amount' => $request->overall_taxable_amount ?? 0,
                    'overall_tax_amount' => $request->overall_tax_amount ?? 0,
                    'overall_amount' => $request->overall_amount ?? 0,
                ];

                if ($purchase) {
                    // Update existing draft
                    $purchase->update($purchaseData);

                    // Delete existing purchase details and recreate
                    $purchase->purchaseDetails()->delete();
                } else {
                    // Create new draft
                    $purchase = Purchase::create($purchaseData);
                }

                // Create purchase details
                    foreach ($cart->content() as $cart_item) {
                        $pcId = $resolver->resolve($cart_item->id, $cart_item->options->code ?? null);
                        // Prefer DOM-submitted rate_before_discount when present to avoid Livewire race
                        $_submittedRateRaw = $request->input("submitted_rates.{$cart_item->id}", null);
                        if ($_submittedRateRaw !== null && $_submittedRateRaw !== '') {
                            $_rateBeforeDiscount = floatval(str_replace([',', settings()->currency->symbol], '', (string)$_submittedRateRaw));
                        } else {
                            $_rateBeforeDiscount = ($cart_item->options->rate_before_discount ?? $cart_item->options->rate ?? null);
                        }

                        // Compute derived fields
                        $discountPercent = (float) ($cart_item->options->product_discount_percent ?? 0);
                        $cashDiscountPercent = (float) ($cart_item->options->cash_discount_percent ?? 0);
                        $cashDiscountAmount = (float) ($cart_item->options->cash_discount_amount ?? 0);
                        $taxPercent = (float) ($cart_item->options->gst_percent ?? $cart_item->options->tax_percent ?? 0);
                        $qty = $cart_item->qty;

                        if ($_rateBeforeDiscount !== null) {
                            $perUnitAfterPercent = floatval($_rateBeforeDiscount) * (1 - ($discountPercent / 100));
                            $cashPercentAmtPerUnit = $perUnitAfterPercent * ($cashDiscountPercent / 100);
                            $cashTotalPerUnit = $cashPercentAmtPerUnit + $cashDiscountAmount;
                            $computedRate = round($perUnitAfterPercent - $cashTotalPerUnit, 2);
                            $computedAmount = round($computedRate * $qty, 2);
                            $computedTaxAmount = round($computedAmount * ($taxPercent / 100), 2);
                        } else {
                            $computedRate = $cart_item->options->rate ?? 0;
                            $computedAmount = ($cart_item->options->rate ?? 0) * $qty;
                            $computedTaxAmount = ($cart_item->options->rate ?? 0) * (($taxPercent ?? 0) / 100) * $qty;
                        }

                        PurchaseDetail::create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $cart_item->id,
                        'product_name' => $cart_item->name,
                        'product_code' => $cart_item->options->code,
                        'product_code_id' => $pcId,
                        'category' => $cart_item->options->category ?? '-',
                        'unit' => $cart_item->options->unit ?? 'Nos',
                        'quantity' => $cart_item->qty,
                        'mrp' => $cart_item->options->mrp,
                        'rate_before_discount' => $_rateBeforeDiscount,
                        'rate' => $computedRate,
                        'rate_type' => $cart_item->options->rate_type ?? 'N',
                        'tax_percent' => $taxPercent,
                        'tax_amount' => $computedTaxAmount,
                        'amount' => $computedAmount,
                        'price' => $cart_item->price,
                        'unit_price' => $computedRate,
                        'sub_total' => $computedAmount,
                        'product_discount_amount' => (
                            ($cart_item->options->product_discount_type ?? '') === 'percentage'
                            ? round((float)($_rateBeforeDiscount ?? 0) * ($discountPercent / 100) * $qty, 2)
                            : ($cart_item->options->product_discount ?? 0)
                        ),
                        'product_discount_type' => $cart_item->options->product_discount_type,
                        'product_tax_amount' => $computedTaxAmount,
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Draft saved successfully',
                'draft_id' => $purchase->id ?? null
            ]);

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Auto-save draft DB error: ' . $e->getMessage());

            // Return user-friendly message without exposing SQL details
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


    public function edit(Purchase $purchase) {
        abort_if(Gate::denies('edit_purchases'), 403);
        // Prevent editing if the purchase is locked by a receipt
        if (!empty($purchase->locked)) {
            toast('This purchase is locked because a receipt exists for it. You cannot edit the bill until the receipt is removed.', 'error');
            return redirect()->route('purchases.index');
        }
        $purchase->load('purchaseDetails');
        $this->populatePurchaseCart($purchase, 'purchase_edit');
        $cartInstance = 'purchase_edit';

        return view('purchase::edit', compact('purchase', 'cartInstance'));
    }

    /**
     * Populate the purchase cart instance from purchase details.
     * Uses batch product loading to avoid N+1 queries.
     */
    private function populatePurchaseCart(Purchase $purchase, string $instance = 'purchase')
    {
        $purchase_details = $purchase->purchaseDetails;

        Cart::instance($instance)->destroy();

        $cart = Cart::instance($instance);

        $productIds = $purchase_details->pluck('product_id')->unique()->filter()->values()->all();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($purchase_details as $purchase_detail) {
            $product = $products->has($purchase_detail->product_id) ? $products->get($purchase_detail->product_id) : null;

            $mrp = $purchase_detail->mrp ?? ($product->mrp ?? 0);
            
            // Prefer stored discount_percent whenever present (>0), even for legacy rows
            // where product_discount_type may still be saved as "fixed".
            $stored_percent = $purchase_detail->discount_percent ?? null;
            $product_discount_type = $purchase_detail->product_discount_type;
            if ($stored_percent !== null && (float)$stored_percent > 0) {
                // Keep as float — truncating to int loses fractional discount %.
                $discount_percent = (float) $stored_percent;
                $product_discount_type = 'percentage';
            } elseif ($product_discount_type === 'percentage' && !empty($mrp) && $mrp > 0) {
                // Backward compatibility: calculate from amount for old records
                $discount_percent = round(($purchase_detail->product_discount_amount / $mrp) * 100, 4);
            } else {
                $discount_percent = 0;
            }

            $options = [
                'product_discount' => $purchase_detail->product_discount_amount,
                'product_discount_type' => $product_discount_type,
                'product_discount_percent' => $discount_percent,
                'sub_total'   => $purchase_detail->sub_total,
                'code'        => ($purchase_detail->productCode->code ?? $purchase_detail->product_code),
                'stock'       => $product ? $product->product_quantity : 0,
                'product_tax' => $purchase_detail->product_tax_amount,
                'unit_price'  => $purchase_detail->unit_price,
                // Additional product information for cart display
                'category' => $purchase_detail->category ?? ($product && $product->category ? $product->category->category_name : '-'),
                'unit' => $purchase_detail->unit ?? ($product->product_unit ?? ''),
                'mrp' => $mrp,
                'rate' => $purchase_detail->rate ?? $purchase_detail->unit_price,
                'rate_before_discount' => (
                    $purchase_detail->rate_before_discount !== null
                        ? $purchase_detail->rate_before_discount
                        : ($purchase_detail->rate ?? $purchase_detail->unit_price)
                ),
                'tax_percent' => $purchase_detail->tax_percent ?? ($product->product_order_tax ?? 0),
                'gst_percent' => $purchase_detail->tax_percent ?? ($product->product_order_tax ?? 0),
                'tax_amount' => $purchase_detail->tax_amount ?? $purchase_detail->product_tax_amount,
                'amount' => $purchase_detail->amount ?? $purchase_detail->sub_total,
                'product_cost' => (float)($product->product_cost ?? 0),
                'rate_type' => $purchase_detail->rate_type ?? 'N'
            ];

            // (debug logging removed)

            $cart->add([
                'id'      => $purchase_detail->product_id,
                'name'    => $purchase_detail->product_name,
                'qty'     => $purchase_detail->quantity,
                'price'   => $purchase_detail->price,
                'weight'  => 1,
                'options' => $options
            ]);
        }
    }


    public function update(UpdatePurchaseRequest $request, Purchase $purchase) {
        // Prevent updating if the purchase is locked by a receipt
        if (!empty($purchase->locked)) {
            toast('This purchase is locked because a receipt exists for it. You cannot update the bill until the receipt is removed.', 'error');
            return redirect()->route('purchases.index');
        }

        // Safety guard: abort update when edit cart is empty.
        // This prevents deleting existing purchase details and saving zero rows.
        if (Cart::instance('purchase_edit')->content()->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Cannot update purchase with an empty cart. Please add products and try again.'
            ]);
        }

        $resolver = new \App\Services\ProductCodeResolver();
        $resolver->preload(Cart::instance('purchase_edit')->content()->pluck('id')->unique()->toArray());

        DB::transaction(function () use ($request, $purchase, $resolver) {
            $cart = Cart::instance('purchase_edit');
            $days = max(0, (int) ($request->days ?? 0));
            $dueDate = null;
            try {
                $dueDate = !empty($request->due_date)
                    ? \Carbon\Carbon::parse($request->due_date)->format('Y-m-d')
                    : \Carbon\Carbon::parse($request->ref_date)->addDays($days)->format('Y-m-d');
            } catch (\Throwable $e) {
                $dueDate = null;
            }
            // Coerce posted strings (may contain commas/currency) to numeric floats
            $total_amount = floatval(str_replace([',', settings()->currency->symbol], '', (string) ($request->total_amount ?? 0)));
            $paid_amount = floatval(str_replace([',', settings()->currency->symbol], '', (string) ($request->paid_amount ?? 0)));
            $discount_amount = floatval(str_replace([',', settings()->currency->symbol], '', (string) ($request->discount_amount ?? 0)));
            
            $due_amount = $total_amount - $paid_amount - $discount_amount;
            if ($due_amount == $total_amount) {
                $payment_status = 'Unpaid';
            } elseif ($due_amount > 0) {
                $payment_status = 'Partial';
            } else {
                $payment_status = 'Paid';
            }

            // Capture previous due and supplier so we can adjust supplier open balance
            $oldDue = $purchase->due_amount ?? 0;
            $oldSupplierId = $purchase->supplier_id;
            // Remember whether this purchase was a draft before update (drafts do not affect supplier balances)
            $wasDraft = ($purchase->status === 'Draft');

            // Validate that updating this purchase won't make stock go negative
            $cartItems = $cart->content();
            foreach ($cartItems as $cartItem) {
                $product = Product::lockForUpdate()->findOrFail($cartItem->id);
                $currentPurchaseQtyRaw = $product->purchase_quantity ?? 0;
                if ($currentPurchaseQtyRaw < 0) {
                    Log::warning('Product has negative purchase_quantity before update; treating as 0', [
                        'product_id' => $product->id,
                        'purchase_quantity' => $currentPurchaseQtyRaw
                    ]);
                }
                $currentPurchaseQty = max(0, $currentPurchaseQtyRaw);
                
                // Find the original quantity for this product in the purchase
                $originalDetail = $purchase->purchaseDetails->where('product_id', $cartItem->id)->first();
                $originalQty = $originalDetail ? $originalDetail->quantity : 0;
                
                // Calculate what the new purchase quantity would be
                // If original purchase was a draft, stock was not incremented, so no subtraction
                if ($purchase->status === 'Draft') {
                    $newPurchaseQty = $currentPurchaseQty + $cartItem->qty;
                } else {
                    $newPurchaseQty = $currentPurchaseQty - $originalQty + $cartItem->qty;
                }
                
                // Check if purchase_quantity itself would go negative
                if ($newPurchaseQty < 0) {
                    throw new \Exception("Cannot update purchase: Product '{$product->product_name}' would have negative purchase quantity ({$newPurchaseQty}). Current purchase stock: {$currentPurchaseQty}, original qty: {$originalQty}, new qty: {$cartItem->qty}.");
                }
                
                // Calculate final stock after this change
                $openQtyRaw = $product->open_quantity ?? 0;
                if ($openQtyRaw < 0) {
                    Log::warning('Product has negative open_quantity before update; treating as 0', [
                        'product_id' => $product->id,
                        'open_quantity' => $openQtyRaw
                    ]);
                }
                $openQty = max(0, $openQtyRaw);
                $finalStock = $openQty + $newPurchaseQty;
                
                if ($finalStock < 0) {
                    throw new \Exception("Cannot update purchase: Product '{$product->product_name}' would have negative stock ({$finalStock}). Please adjust quantities or check product adjustments.");
                }
            }

            // Restore product quantities for existing purchase details before re-creating them.
            // Only restore stock for purchases that actually had their stock incremented (non-drafts).
            // Drafts don't increment stock, so no restoration is needed.
            if ($purchase->status !== 'Draft') {
                foreach ($purchase->purchaseDetails as $purchase_detail) {
                    $product = Product::lockForUpdate()->findOrFail($purchase_detail->product_id);
                    $product->removePurchaseStock($purchase_detail->quantity);
                    $purchase_detail->delete();
                }
            } else {
                // For drafts, just delete the purchase details without restoring stock
                foreach ($purchase->purchaseDetails as $purchase_detail) {
                    $purchase_detail->delete();
                }
            }

            $purchase->update([
                'date' => $request->ref_date,
                'reference' => $request->reference,
                'supplier_id' => $request->supplier_id,
                'supplier_name' => Supplier::findOrFail($request->supplier_id)->supplier_name,
                'area' => $request->area,
                'balance' => $request->balance,
                'invoice_no' => $request->invoice_no,
                'invoice_date' => $request->invoice_date,
                'days' => $days,
                'due_date' => $dueDate,
                'tax_percentage' => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount' => $request->shipping_amount,
                'paid_amount' => $paid_amount,
                'total_amount' => $total_amount,
                'due_amount' => $due_amount,
                'status' => $request->status,
                'payment_status' => $payment_status,
                'payment_method' => $request->payment_method,
                'note' => $request->note,
                'tax_amount' => $cart->tax(),
                'discount_amount' => $discount_amount,
                // Overall calculations
                'overall_nos' => $request->overall_nos ?? 0,
                'overall_quantity' => $request->overall_quantity ?? 0,
                'overall_gross_amount' => $request->overall_gross_amount ?? 0,
                'overall_taxable_amount' => $request->overall_taxable_amount ?? 0,
                'overall_tax_amount' => $request->overall_tax_amount ?? 0,
                'overall_amount' => $request->overall_amount ?? 0,
            ]);

                foreach ($cart->content() as $cart_item) {
                $pcId = $resolver->resolve($cart_item->id, $cart_item->options->code ?? null);
                // Use rate submitted directly from the DOM (bypasses Livewire async race)
                $_submittedRateRaw = $request->input("submitted_rates.{$cart_item->id}", null);
                if ($_submittedRateRaw !== null && $_submittedRateRaw !== '') {
                    $_rateBeforeDiscount = floatval(str_replace([',', settings()->currency->symbol], '', (string)$_submittedRateRaw));
                } else {
                    $_rateBeforeDiscount = ($cart_item->options->rate_before_discount ?? $cart_item->options->rate ?? null);
                }

                // Use MRP submitted from DOM (same race-condition bypass as submitted_rates).
                // Falls back to the cart session option when the field was not changed.
                $_submittedMrp = $request->input("submitted_mrps.{$cart_item->id}", null);
                if ($_submittedMrp !== null && $_submittedMrp !== '') {
                    $_effectiveMrp = round(floatval(str_replace([',', settings()->currency->symbol], '', (string)$_submittedMrp)), 2);
                } else {
                    $_effectiveMrp = round(floatval($cart_item->options->mrp ?? 0), 2);
                }

                // Compute derived fields from authoritative rate_before_discount (server-side)
                $discountPercent = (float) ($cart_item->options->product_discount_percent ?? 0);
                $cashDiscountPercent = (float) ($cart_item->options->cash_discount_percent ?? 0);
                $cashDiscountAmount = (float) ($cart_item->options->cash_discount_amount ?? 0);
                $taxPercent = (float) ($cart_item->options->gst_percent ?? $cart_item->options->tax_percent ?? 0);
                $qty = $cart_item->qty;

                if ($_rateBeforeDiscount !== null) {
                    $perUnitAfterPercent = floatval($_rateBeforeDiscount) * (1 - ($discountPercent / 100));
                    $cashPercentAmtPerUnit = $perUnitAfterPercent * ($cashDiscountPercent / 100);
                    $cashTotalPerUnit = $cashPercentAmtPerUnit + $cashDiscountAmount;
                    $computedRate = round($perUnitAfterPercent - $cashTotalPerUnit, 2);
                    $computedAmount = round($computedRate * $qty, 2);
                    $computedTaxAmount = round($computedAmount * ($taxPercent / 100), 2);
                } else {
                    $computedRate = $cart_item->options->rate ?? 0;
                    $computedAmount = ($cart_item->options->rate ?? 0) * $qty;
                    $computedTaxAmount = ($cart_item->options->rate ?? 0) * (($taxPercent ?? 0) / 100) * $qty;
                }

                PurchaseDetail::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $cart_item->id,
                    'product_name' => $cart_item->name,
                    'product_code' => $cart_item->options->code,
                    'product_code_id' => $pcId,
                    'category' => $cart_item->options->category ?? '-',
                    'unit' => $cart_item->options->unit ?? 'Nos',
                    'quantity' => $cart_item->qty,
                    'mrp' => $_effectiveMrp,
                    'rate_before_discount' => $_rateBeforeDiscount,
                    'rate' => $computedRate,
                    'rate_type' => $cart_item->options->rate_type ?? 'N',
                    'tax_percent' => $taxPercent,
                    'tax_amount' => $computedTaxAmount,
                    'amount' => $computedAmount,
                    'price' => $cart_item->price,
                    // Persist per-unit and per-line calculations to match UI
                    'unit_price' => $computedRate,
                    'sub_total' => $computedAmount,
                    'product_discount_amount' => (
                        ($cart_item->options->product_discount_type ?? '') === 'percentage'
                        ? round((float)($_rateBeforeDiscount ?? 0) * ($discountPercent / 100) * $qty, 2)
                        : ($cart_item->options->product_discount ?? 0)
                    ),
                    'product_discount_type' => $cart_item->options->product_discount_type,
                    'product_tax_amount' => $computedTaxAmount,
                ]);
                // debug log removed

                // Only increment product quantity for completed purchases
                // Drafts should not reserve stock until they are completed
                if ($purchase->status !== 'Draft') {
                    $product = Product::lockForUpdate()->findOrFail($cart_item->id);

                    // Use helper to increment purchase stock
                    $product->addPurchaseStock($cart_item->qty);

                    // BRD: the entered Purchase Rate updates the product's cost in the Product Master.
                    $newCost = round(floatval($_rateBeforeDiscount ?? 0), 2);
                    if ($newCost > 0 && abs($newCost - round(floatval($product->product_cost ?? 0), 2)) >= 0.005) {
                        $product->product_cost = $newCost;
                        $product->save();
                    }

                    $product->refresh();
                }
            }

            $cart->destroy();

            // Reconcile supplier open_balance for change in outstanding due.
            // Special-case: Draft -> Non-Draft transition: drafts never affected supplier balances, so add full due to supplier.
            $newDue = $due_amount;
            $newSupplierId = $request->supplier_id;

            if ($wasDraft && ($purchase->status ?? '') !== 'Draft') {
                // Transitioning from Draft to completed state: add entire due to new supplier
                if (($newDue ?? 0) > 0) {
                    $supplier = Supplier::lockForUpdate()->find($newSupplierId);
                    if ($supplier) {
                        $supplier->open_balance = ($supplier->open_balance ?? 0) + ($newDue ?? 0);
                        $supplier->save();
                    }
                }
            } else {
                if ($oldSupplierId == $newSupplierId) {
                    if (abs($newDue - $oldDue) > 0.0001) {
                        $supplier = Supplier::lockForUpdate()->find($newSupplierId);
                        if ($supplier) {
                            // Adjust and clamp to zero if negative
                            $supplier->open_balance = max(0, ($supplier->open_balance ?? 0) + ($newDue - $oldDue));
                            $supplier->save();
                        }
                    }
                } else {
                    // supplier changed: subtract oldDue from old supplier, add newDue to new supplier
                    $suppliers = Supplier::whereIn('id', [$oldSupplierId, $newSupplierId])->lockForUpdate()->get()->keyBy('id');
                    if (isset($suppliers[$oldSupplierId])) {
                        $s = $suppliers[$oldSupplierId];
                        $s->open_balance = max(0, ($s->open_balance ?? 0) - ($oldDue ?? 0));
                        $s->save();
                    }
                    if (isset($suppliers[$newSupplierId])) {
                        $s2 = $suppliers[$newSupplierId];
                        $s2->open_balance = ($s2->open_balance ?? 0) + ($newDue ?? 0);
                        $s2->save();
                    }
                }
            }

            // Reconcile PurchasePayment rows to support multiple payment history.
            // Instead of replacing all payments, preserve existing payments and add new ones as needed.
            $shouldApply = ($request->paid_amount > 0);
            $desiredApplied = $shouldApply ? ($request->paid_amount ?? 0) : 0;

            // Sum existing payments (stored in paise)
            $existingTotalPaise = PurchasePayment::where('purchase_id', $purchase->id)->sum('amount');
            $existingTotal = ($existingTotalPaise ?? 0) / 100;

            // If the desired applied amount is greater than existing total, add a new payment for the difference
            if ($desiredApplied > $existingTotal + 0.0001) {
                $additionalAmount = $desiredApplied - $existingTotal;

                // create purchases receipt for the additional applied payment
                $r = PurchasesReceipt::create([
                    'date' => $request->ref_date,
                    'supplier_id' => $purchase->supplier_id,
                    'particular' => 'Auto-generated from Purchase '.$purchase->reference,
                    'payment_mode' => $request->payment_method ?? null,
                    'total_amount' => 0,
                    'total_discount' => 0,
                    'created_by' => auth()->id()
                ]);

                $billAmount = floatval($purchase->total_amount ?? 0);
                $paidBefore = floatval($existingTotal ?? 0);
                $balanceBefore = $billAmount - $paidBefore;
                $displayFinal = $billAmount - ($paidBefore + $additionalAmount + 0);

                PurchasesReceiptLine::create([
                    'purchases_receipt_id' => $r->id,
                    'purchase_id' => $purchase->id,
                    'bill_ref' => $purchase->reference,
                    'bill_date' => $purchase->date,
                    'bill_amount' => $billAmount,
                    'paid_before' => $paidBefore,
                    'balance_before' => $balanceBefore,
                    'payment_amount' => $additionalAmount,
                    'discount_amount' => 0,
                    'final_balance' => $displayFinal,
                    'is_settled' => true,
                    'settled_at' => now(),
                    'settled_by' => auth()->id(),
                ]);

                $r->total_amount = $additionalAmount;
                $r->total_discount = 0;
                $r->save();
                $r->reference = 'PU-RE' . str_pad($r->id, 5, '0', STR_PAD_LEFT);
                $r->save();

                // create the purchase payment with receipt reference
                PurchasePayment::create([
                    'date' => $request->ref_date,
                    'reference' => $r->reference,
                    'amount' => $additionalAmount,
                    'purchase_id' => $purchase->id,
                    'payment_method' => $request->payment_method
                ]);

                // subtract applied amount from supplier balance
                $supplier = Supplier::lockForUpdate()->find($purchase->supplier_id);
                if ($supplier) {
                    // Clamp to zero instead of throwing an error
                    $supplier->open_balance = max(0, ($supplier->open_balance ?? 0) - $additionalAmount);
                    $supplier->save();
                }
            }
            // If the desired applied amount is less than existing total, we keep existing payments
            // (this preserves payment history even if the total paid amount is reduced)
        });

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Purchase Updated Successfully!',
                'redirect' => route('purchases.index')
            ]);
        }

        return redirect()->route('purchases.index');
    }


    public function destroy(Purchase $purchase) {
        abort_if(Gate::denies('delete_purchases'), 403);

        // Prevent deleting if the purchase is locked by a receipt
        if (!empty($purchase->locked)) {
            toast('This purchase is locked because a receipt exists for it. You cannot delete the bill until the receipt is removed.', 'error');
            return redirect()->route('purchases.index');
        }

        // If this is a Draft, delete details and record without touching balances or stock
        if (($purchase->status ?? '') === 'Draft') {
            PurchasePayment::where('purchase_id', $purchase->id)->delete();
            foreach ($purchase->purchaseDetails as $purchase_detail) {
                $purchase_detail->delete();
            }
            $purchase->delete();

            toast('Draft Purchase Deleted!', 'warning');

            return redirect()->route('purchases.index');
        }

        // Reverse supplier balance adjustment before deleting (clamp to zero)
        if (($purchase->due_amount ?? 0) > 0) {
            $supplier = Supplier::lockForUpdate()->find($purchase->supplier_id);
            if ($supplier) {
                $supplier->open_balance = max(0, ($supplier->open_balance ?? 0) - ($purchase->due_amount ?? 0));
                $supplier->save();
            }
        }

        // Delete associated payment records before deleting the purchase
        PurchasePayment::where('purchase_id', $purchase->id)->delete();

        // Safely compute removals when deleting: clamp to available purchase_quantity and do not touch open_quantity.
            foreach ($purchase->purchaseDetails as $purchase_detail) {
            $product = Product::lockForUpdate()->findOrFail($purchase_detail->product_id);

            // Only remove up to the product's current purchase_quantity (do not reduce open_quantity here)
            $removable = min((int)($product->purchase_quantity ?? 0), (int) $purchase_detail->quantity);

            // If the requested removal exceeds available purchase_quantity, log it for investigation
            if ($removable < $purchase_detail->quantity) {
                Log::warning('Purchase delete - clamped removal', [
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'product_name' => $product->product_name,
                    'requested_removal' => $purchase_detail->quantity,
                    'actual_removed' => $removable,
                ]);
            }
        }

        // Reverse stock increments from the purchase before deleting
        foreach ($purchase->purchaseDetails as $purchase_detail) {
            $product = Product::lockForUpdate()->findOrFail($purchase_detail->product_id);

            // Only remove the amount actually available in purchase_quantity (removePurchaseStock itself clamps to zero,
            // but we compute removable explicitly for clarity and to avoid touching open_quantity.)
            $removable = min((int)($product->purchase_quantity ?? 0), (int) $purchase_detail->quantity);
            if ($removable > 0) {
                $product->removePurchaseStock($removable);
            }

            // Finally delete the purchase detail
            $purchase_detail->delete();
        }

        $purchase->delete();

        toast('Purchase Deleted!', 'warning');

        return redirect()->route('purchases.index');
    }

    public function reorder(Purchase $purchase) {
        abort_if(Gate::denies('create_purchases'), 403);

        $purchase->load('purchaseDetails');

        // Generate new reference number
        $lastPurchase = Purchase::latest('id')->first();
        $nextNumber = $lastPurchase ? $lastPurchase->id + 1 : 1;
        $newReference = 'PU' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

        // Create a new Draft purchase copying header from original
        $newPurchase = Purchase::create([
            'date'             => now()->format('Y-m-d'),
            'reference'        => $newReference,
            'supplier_id'      => $purchase->supplier_id,
            'supplier_name'    => $purchase->supplier_name,
            'invoice_no'       => '',
            'invoice_date'     => now()->format('Y-m-d'),
            'area'             => $purchase->area,
            'tax_percentage'   => 0,
            'tax_amount'       => 0,
            'discount_percentage' => 0,
            'discount_amount'  => 0,
            'shipping_amount'  => 0,
            'total_amount'     => $purchase->total_amount,
            'paid_amount'      => 0,
            'due_amount'       => $purchase->total_amount,
            'status'           => 'Draft',
            'payment_status'   => 'Unpaid',
            'note'             => 'Reorder from ' . $purchase->reference,
        ]);

        // Copy line items (no stock change — draft)
        // attributesToArray() returns only DB column values via getters (rupees, not raw paise)
        // without any eager-loaded relationships — avoids both the 'product' array and
        // the productCode relationship overwriting the product_code string column in toArray()
        foreach ($purchase->purchaseDetails as $detail) {
            $newPurchase->purchaseDetails()->create(
                collect($detail->attributesToArray())
                    ->except(['id', 'purchase_id', 'created_at', 'updated_at'])
                    ->toArray()
            );
        }

        toast('Reorder draft created from ' . $purchase->reference . '. Review and confirm below.', 'info');

        return redirect()->route('purchases.edit', $newPurchase);
    }
}
