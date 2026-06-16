<?php

namespace Modules\PurchasesReturn\Http\Controllers;

use Modules\PurchasesReturn\DataTables\PurchaseReturnsDataTable;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\People\Entities\Supplier;
use Modules\Product\Entities\Product;
use Illuminate\Support\Facades\Log;
use App\Services\ProductCodeResolver;
use Modules\PurchasesReturn\Entities\PurchaseReturn;
use Modules\PurchasesReturn\Entities\PurchaseReturnDetail;
use Modules\PurchasesReturn\Entities\PurchaseReturnPayment;
use Modules\PurchasesReceipt\Entities\PurchasesReceipt;
use Modules\PurchasesReturn\Http\Requests\StorePurchaseReturnRequest;
use Modules\PurchasesReturn\Http\Requests\UpdatePurchaseReturnRequest;
use Illuminate\Validation\ValidationException;

class PurchasesReturnController extends Controller
{

    public function index(PurchaseReturnsDataTable $dataTable) {
        abort_if(Gate::denies('access_purchase_returns'), 403);

        return $dataTable->render('purchasesreturn::index');
    }


    public function create() {
        abort_if(Gate::denies('create_purchase_returns'), 403);

        Cart::instance('purchase_return')->destroy();

        return view('purchasesreturn::create');
    }


    public function store(StorePurchaseReturnRequest $request) {
        $resolver = new ProductCodeResolver();
        $resolver->preload(Cart::instance('purchase_return')->content()->pluck('id')->unique()->toArray());

        DB::transaction(function () use ($request, $resolver) {
            $cart = Cart::instance('purchase_return');
            // Set default values for nullable fields
            $paid_amount = $request->paid_amount ?? 0;
            $due_amount = $request->total_amount - $paid_amount;
            
            // Default payment status based on paid amount
            if ($paid_amount == 0) {
                $payment_status = 'Unpaid';
            } elseif ($due_amount > 0) {
                $payment_status = 'Partial';
            } else {
                $payment_status = 'Paid';
            }

            // Load the supplier once: used for the stored name and the frozen Bill Balance
            // snapshot (sum of unpaid dues as it is just before this return is created).
            $supplier = Supplier::findOrFail($request->supplier_id);

            $purchase_return = PurchaseReturn::create([
                'date' => $request->date,
                'supplier_id' => $request->supplier_id,
                'supplier_name' => $supplier->supplier_name,
                'area' => $request->area,
                'balance' => $request->balance,
                'bill_balance_before' => (float) ($supplier->bill_balance ?? 0),
                'invoice_no' => $request->invoice_no,
                'invoice_date' => $request->invoice_date,
                'excess_amount' => $request->excess_amount,
                'tax_percentage' => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount' => $request->shipping_amount,
                'paid_amount' => $paid_amount,
                'total_amount' => $request->total_amount,
                // Overall calculations
                'overall_nos' => $request->overall_nos ?? 0,
                'overall_quantity' => $request->overall_quantity ?? 0,
                'overall_gross_amount' => $request->overall_gross_amount ?? 0,
                'overall_taxable_amount' => $request->overall_taxable_amount ?? 0,
                'overall_tax_amount' => $request->overall_tax_amount ?? 0,
                'overall_amount' => $request->overall_amount ?? 0,
                'due_amount' => $due_amount,
                'status' => $request->status ?? 'Pending',
                'payment_status' => $payment_status,
                'payment_method' => $request->payment_method ?? null,
                'note' => $request->note,
                'tax_amount' => $cart->tax(),
                'discount_amount' => $cart->discount(),
            ]);

            foreach ($cart->content() as $cart_item) {
                // Prefer DOM-submitted rate_before_discount when present to avoid Livewire race
                $_submittedRateRaw = $request->input("submitted_rates.{$cart_item->id}", null);
                if ($_submittedRateRaw !== null && $_submittedRateRaw !== '') {
                    $_rateBeforeDiscount = floatval(str_replace([',', settings()->currency->symbol], '', (string)$_submittedRateRaw));
                } else {
                    // Fallback: prefer explicit stored rate_before_discount, then compute from MRP and tax%,
                    // finally fall back to existing rate option only if nothing else is available.
                    $taxPct = floatval($cart_item->options->tax_percent ?? 0);
                    $optMrp = isset($cart_item->options->mrp) ? floatval($cart_item->options->mrp) : null;
                    if (isset($cart_item->options->rate_before_discount) && $cart_item->options->rate_before_discount !== null) {
                        $_rateBeforeDiscount = floatval($cart_item->options->rate_before_discount);
                    } elseif ($optMrp !== null && $optMrp > 0) {
                        $_rateBeforeDiscount = $taxPct > 0 ? ($optMrp / (1 + $taxPct / 100)) : $optMrp;
                    } else {
                        $_rateBeforeDiscount = isset($cart_item->options->rate) ? floatval($cart_item->options->rate) : null;
                    }
                }

                // Compute derived values from authoritative rate_before_discount
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

                $pcId = $resolver->resolve($cart_item->id, $cart_item->options->code ?? null);

                PurchaseReturnDetail::create([
                    'purchase_return_id' => $purchase_return->id,
                    'product_id' => $cart_item->id,
                    'product_name' => $cart_item->name,
                    'product_code' => $cart_item->options->code,
                    'product_code_id' => $pcId,
                    'quantity' => $cart_item->qty,
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
                    // New product-level fields
                    'category' => $cart_item->options->category ?? null,
                    'unit' => $cart_item->options->unit ?? null,
                    'mrp' => ($cart_item->options->mrp ?? 0),
                    'rate_before_discount' => $_rateBeforeDiscount,
                    'rate' => $computedRate,
                    'rate_type' => $cart_item->options->rate_type ?? 'N',
                    'tax_percent' => $taxPercent,
                    'tax_amount' => $computedTaxAmount,
                    'amount' => $computedAmount,
                ]);

                // Reduce quantity for purchase returns only if receipt is to be created
                if ($request->boolean('create_receipt')) {
                    // Use row lock to avoid concurrent stock races
                    $product = Product::lockForUpdate()->findOrFail($cart_item->id);
                    if (($product->purchase_quantity ?? 0) < $cart_item->qty) {
                        Log::warning('Purchase return store - clamped removal', [
                            'product_id' => $product->id,
                            'product_name' => $product->product_name,
                            'requested_removal' => $cart_item->qty,
                            'available_purchase_qty' => $product->purchase_quantity ?? 0,
                        ]);
                    }
                    // removePurchaseStock floors at zero
                    $product->removePurchaseStock($cart_item->qty);
                    // MRP persistence removed from Purchase Return flow; purchases handle MRP updates.
                }
            }

            $cart->destroy();
            if ($purchase_return->paid_amount > 0) {
                PurchaseReturnPayment::create([
                    'date'               => $request->date,
                    'reference'          => 'INV/' . $purchase_return->reference,
                    'amount'             => $purchase_return->paid_amount,
                    'purchase_return_id' => $purchase_return->id,
                    'payment_method'     => $request->payment_method ?? 'Cash'
                ]);
            }
            // persist user's choice to auto-create a lineless purchases receipt
            $purchase_return->create_receipt = $request->boolean('create_receipt');
            $purchase_return->save();

            // Create or sync a lineless PurchasesReceipt if requested
            if ($request->boolean('create_receipt')) {
                $receiptAmount = floatval($purchase_return->total_amount ?? 0);
                $this->createOrSyncReceipt($purchase_return, $receiptAmount);
            }
        });

        toast('Purchase Return Created!', 'success');

        return redirect()->route('purchase-returns.index');
    }


    public function show(PurchaseReturn $purchase_return) {
        abort_if(Gate::denies('show_purchase_returns'), 403);

        $supplier = Supplier::findOrFail($purchase_return->supplier_id);
        $this->populatePurchaseReturnCart($purchase_return, 'purchase_return_view');
        $cartInstance = 'purchase_return_view';

        $readonly = true;
        return view('purchasesreturn::edit', compact('purchase_return', 'supplier', 'readonly', 'cartInstance'));
    }


    public function edit(PurchaseReturn $purchase_return) {
        abort_if(Gate::denies('edit_purchase_returns'), 403);

        $this->populatePurchaseReturnCart($purchase_return);

        return view('purchasesreturn::edit', compact('purchase_return'));
    }


    /**
     * Populate the purchase_return cart instance from return details.
     * Batch-loads all products in a single query to avoid N+1 queries.
     */
    private function populatePurchaseReturnCart(PurchaseReturn $purchase_return, string $instance = 'purchase_return'): void
    {
        $details = $purchase_return->purchaseReturnDetails;

        // Batch-load all referenced products in one query (avoids 2×N findOrFail calls)
        $productIds = $details->pluck('product_id')->unique()->filter()->values()->all();
        $products   = Product::whereIn('id', $productIds)
                        ->get(['id', 'purchase_quantity', 'product_quantity'])
                        ->keyBy('id');

        Cart::instance($instance)->destroy();
        $cart = Cart::instance($instance);

        foreach ($details as $d) {
            $product = $products->get($d->product_id);
            $stock   = $product ? ($product->purchase_quantity ?? $product->product_quantity ?? 0) : 0;

            // Prefer stored discount_percent whenever present for legacy compatibility.
            // Older rows may still have product_discount_type='fixed' even though
            // discount_percent contains the authoritative value.
            $storedPercent = $d->discount_percent ?? null;
            $discountType  = $d->product_discount_type;
            $discountPct   = ($storedPercent !== null && (float)$storedPercent > 0)
                ? (float)$storedPercent
                : (float)($d->discount_percent ?? 0);
            if ($storedPercent !== null && (float)$storedPercent > 0) {
                $discountType = 'percentage';
            }

            $cart->add([
                'id'      => $d->product_id,
                'name'    => $d->product_name,
                'qty'     => $d->quantity,
                'price'   => $d->price,
                'weight'  => 1,
                'options' => [
                    'product_discount'         => $d->product_discount_amount,
                    'product_discount_type'    => $discountType,
                    'product_discount_percent' => $discountPct,
                    'sub_total'                => $d->sub_total,
                    'code'                     => ($d->productCode->code ?? $d->product_code),
                    'stock'                    => $stock,
                    'product_tax'              => $d->product_tax_amount,
                    'unit_price'               => $d->unit_price,
                    'category'                 => $d->category ?? '-',
                    'unit'                     => $d->unit ?? 'Nos',
                    'mrp'                      => $d->mrp ?? $d->price,
                    'rate_before_discount'     => $d->rate_before_discount ?? $d->rate ?? $d->unit_price,
                    'rate'                     => $d->rate ?? $d->unit_price,
                    'rate_type'                => $d->rate_type ?? 'N',
                    'tax_percent'              => $d->tax_percent ?? 0,
                    'gst_percent'              => $d->tax_percent ?? 0,
                    'tax_amount'               => $d->tax_amount ?? $d->product_tax_amount,
                    'amount'                   => $d->amount ?? $d->sub_total,
                ]
            ]);
        }
    }


    public function update(UpdatePurchaseReturnRequest $request, PurchaseReturn $purchase_return) {
        // Safety guard: abort update when cart is empty.
        // This prevents deleting existing return detail rows and saving zero rows.
        if (Cart::instance('purchase_return')->content()->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Cannot update purchase return with an empty cart. Please add products and try again.'
            ]);
        }

        $resolver = new ProductCodeResolver();
        $resolver->preload(Cart::instance('purchase_return')->content()->pluck('id')->unique()->toArray());

        DB::transaction(function () use ($request, $purchase_return, $resolver) {
            $cart = Cart::instance('purchase_return');
            // Set default values for nullable fields
            $paid_amount = $request->paid_amount ?? $purchase_return->paid_amount ?? 0;
            $due_amount = $request->total_amount - $paid_amount;

            if ($due_amount == $request->total_amount) {
                $payment_status = 'Unpaid';
            } elseif ($due_amount > 0) {
                $payment_status = 'Partial';
            } else {
                $payment_status = 'Paid';
            }

            // Reverse stock changes if the return previously had a receipt
            if ($purchase_return->create_receipt) {
                foreach ($purchase_return->purchaseReturnDetails as $purchase_return_detail) {
                    $product = Product::lockForUpdate()->findOrFail($purchase_return_detail->product_id);
                    $product->addPurchaseStock($purchase_return_detail->quantity);
                    Log::info('Purchase return update - restored purchase stock before update', [
                        'purchase_return_id' => $purchase_return->id,
                        'product_id' => $product->id,
                        'restored_qty' => $purchase_return_detail->quantity,
                    ]);
                    $purchase_return_detail->delete();
                }
            } else {
                // If no receipt, just delete details without stock reversal
                foreach ($purchase_return->purchaseReturnDetails as $purchase_return_detail) {
                    $purchase_return_detail->delete();
                }
            }

            $purchase_return->update([
                'date' => $request->date,
                'reference' => $request->reference,
                'supplier_id' => $request->supplier_id,
                'supplier_name' => Supplier::findOrFail($request->supplier_id)->supplier_name,
                'area' => $request->area,
                'balance' => $request->balance,
                'invoice_no' => $request->invoice_no,
                'invoice_date' => $request->invoice_date,
                'excess_amount' => $request->excess_amount,
                'tax_percentage' => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount' => $request->shipping_amount,
                'paid_amount' => $paid_amount,
                'total_amount' => $request->total_amount,
                // Overall calculations
                'overall_nos' => $request->overall_nos ?? 0,
                'overall_quantity' => $request->overall_quantity ?? 0,
                'overall_gross_amount' => $request->overall_gross_amount ?? 0,
                'overall_taxable_amount' => $request->overall_taxable_amount ?? 0,
                'overall_tax_amount' => $request->overall_tax_amount ?? 0,
                'overall_amount' => $request->overall_amount ?? 0,
                'due_amount' => $due_amount,
                'status' => $request->status ?? 'Pending',
                'payment_status' => $payment_status,
                'payment_method' => $request->payment_method ?? null,
                'note' => $request->note,
                'tax_amount' => $cart->tax(),
                'discount_amount' => $cart->discount(),
            ]);

            foreach ($cart->content() as $cart_item) {
                // Prefer DOM-submitted rate_before_discount when present to avoid Livewire race
                $_submittedRateRaw = $request->input("submitted_rates.{$cart_item->id}", null);
                if ($_submittedRateRaw !== null && $_submittedRateRaw !== '') {
                    $_rateBeforeDiscount = floatval(str_replace([',', settings()->currency->symbol], '', (string)$_submittedRateRaw));
                } else {
                    $taxPct = floatval($cart_item->options->tax_percent ?? 0);
                    $optMrp = isset($cart_item->options->mrp) ? floatval($cart_item->options->mrp) : null;
                    if (isset($cart_item->options->rate_before_discount) && $cart_item->options->rate_before_discount !== null) {
                        $_rateBeforeDiscount = floatval($cart_item->options->rate_before_discount);
                    } elseif ($optMrp !== null && $optMrp > 0) {
                        $_rateBeforeDiscount = $taxPct > 0 ? ($optMrp / (1 + $taxPct / 100)) : $optMrp;
                    } else {
                        $_rateBeforeDiscount = isset($cart_item->options->rate) ? floatval($cart_item->options->rate) : null;
                    }
                }

                // Compute derived values from authoritative rate_before_discount
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

                $pcId = $resolver->resolve($cart_item->id, $cart_item->options->code ?? null);

                PurchaseReturnDetail::create([
                    'purchase_return_id' => $purchase_return->id,
                    'product_id' => $cart_item->id,
                    'product_name' => $cart_item->name,
                    'product_code' => $cart_item->options->code,
                    'product_code_id' => $pcId,
                    'quantity' => $cart_item->qty,
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
                    // New product-level fields
                    'category' => $cart_item->options->category ?? null,
                    'unit' => $cart_item->options->unit ?? null,
                    'mrp' => ($cart_item->options->mrp ?? 0),
                    'rate_before_discount' => $_rateBeforeDiscount,
                    'rate' => $computedRate,
                    'rate_type' => $cart_item->options->rate_type ?? 'N',
                    'tax_percent' => $taxPercent,
                    'tax_amount' => $computedTaxAmount,
                    'amount' => $computedAmount,
                ]);

                // Reduce quantity for purchase returns only if receipt is to be created
                if ($request->boolean('create_receipt')) {
                    $product = Product::lockForUpdate()->findOrFail($cart_item->id);
                    if (($product->purchase_quantity ?? 0) < $cart_item->qty) {
                        Log::warning('Purchase return update - clamped removal', [
                            'purchase_return_id' => $purchase_return->id,
                            'product_id' => $product->id,
                            'product_name' => $product->product_name,
                            'requested_removal' => $cart_item->qty,
                            'available_purchase_qty' => $product->purchase_quantity ?? 0,
                        ]);
                    }
                    $product->removePurchaseStock($cart_item->qty);
                    // MRP persistence removed from Purchase Return update flow; purchases handle MRP updates.
                }
            }

            $cart->destroy();
                // on update: either create/sync or delete/restore based on checkbox
                if ($request->boolean('create_receipt')) {
                    $purchase_return->create_receipt = true;
                    $purchase_return->save();
                    $this->createOrSyncReceipt($purchase_return, floatval($purchase_return->total_amount ?? 0));
                } else {
                    $purchase_return->create_receipt = false;
                    $purchase_return->save();
                    $this->deleteReceiptAndRestore($purchase_return);
                }
        });

        toast('Purchase Return Updated!', 'info');

        return redirect()->route('purchase-returns.index');
    }


    public function destroy(PurchaseReturn $purchase_return) {
        abort_if(Gate::denies('delete_purchase_returns'), 403);
        // Prevent deletion if a linked PurchasesReceipt exists for this purchase return
        $existing = PurchasesReceipt::where('purchase_return_id', $purchase_return->id)->first();
        if ($existing) {
            toast('Cannot delete Purchase Return: linked Purchases Receipt exists. Delete the receipt first.', 'error');
            return redirect()->route('purchase-returns.index');
        }

        DB::transaction(function () use ($purchase_return) {
            // Reverse stock changes only if the return had a receipt
            if ($purchase_return->create_receipt) {
                foreach ($purchase_return->purchaseReturnDetails as $detail) {
                    $product = Product::lockForUpdate()->findOrFail($detail->product_id);
                    $product->addPurchaseStock($detail->quantity);
                    // restored purchase stock (info log removed)
                }
            }

            // delete linked receipt (if any) and restore supplier balance
            $this->deleteReceiptAndRestore($purchase_return);

            $purchase_return->delete();
        });

        toast('Purchase Return Deleted!', 'warning');

        return redirect()->route('purchase-returns.index');
    }

    /**
     * Create or sync a lineless PurchasesReceipt for the given PurchaseReturn.
     */
    private function createOrSyncReceipt(PurchaseReturn $purchase_return, float $receiptAmount)
    {
        if ($receiptAmount <= 0) return;

        $supplier = Supplier::findOrFail($purchase_return->supplier_id);

        $existing = PurchasesReceipt::where('purchase_return_id', $purchase_return->id)->first();

        if ($existing) {
            if ($existing->supplier_id != $purchase_return->supplier_id) {
                $existing->supplier_id = $purchase_return->supplier_id;
                $existing->supplier_balance_before = (int) round(floatval($supplier->open_balance ?? 0) * 100);
                $existing->applied_to_supplier = 0; // No adjustment
            } else {
                $existing->supplier_balance_before = (int) round(floatval($supplier->open_balance ?? 0) * 100);
                $existing->applied_to_supplier = 0; // No adjustment
            }

            $existing->total_amount = $receiptAmount;
            $existing->payment_mode = null;
            $existing->particular = 'From Purchase return';
            $existing->save();
        } else {
            $receipt = PurchasesReceipt::create([
                'date' => $purchase_return->date,
                'supplier_id' => $purchase_return->supplier_id,
                'particular' => 'From Purchase return',
                'payment_mode' => null,
                'total_amount' => $receiptAmount,
                'total_discount' => 0,
                'created_by' => auth()->id(),
                'purchase_return_id' => $purchase_return->id,
            ]);

            $receipt->reference = 'PU-RE' . str_pad($receipt->id, 5, '0', STR_PAD_LEFT);
            $receipt->supplier_balance_before = (int) round(floatval($supplier->open_balance ?? 0) * 100);
            $receipt->applied_to_supplier = 0; // No balance adjustment
            $receipt->save();
        }
    }

    /**
     * Delete any linked PurchasesReceipt and restore the full amount to the previous supplier's open balance.
     */
    private function deleteReceiptAndRestore(PurchaseReturn $purchase_return)
    {
        $existing = PurchasesReceipt::where('purchase_return_id', $purchase_return->id)->first();
        if (!$existing) return;

        // Removed balance restore since no adjustment was done
        $existing->delete();
    }
}
