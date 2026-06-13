<?php

namespace Modules\SalesReturn\Http\Controllers;

use Modules\SalesReturn\DataTables\SaleReturnsDataTable;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\People\Entities\Customer;
use Modules\Product\Entities\Product;
use Modules\SalesReturn\Entities\SaleReturn;
use Modules\SalesReturn\Entities\SaleReturnDetail;
use Modules\SalesReturn\Entities\SaleReturnPayment;
use Modules\SalesReceipt\Entities\SalesReceipt;
use Modules\SalesReturn\Http\Requests\StoreSaleReturnRequest;
use Modules\SalesReturn\Http\Requests\UpdateSaleReturnRequest;
use App\Services\CartItemCalculator;
use App\Services\ProductCodeResolver;
use Illuminate\Validation\ValidationException;

class SalesReturnController extends Controller
{

    public function index(SaleReturnsDataTable $dataTable) {
        abort_if(Gate::denies('access_sale_returns'), 403);

        return $dataTable->render('salesreturn::index');
    }


    public function create() {
        abort_if(Gate::denies('create_sale_returns'), 403);

        Cart::instance('sale_return')->destroy();

        return view('salesreturn::create');
    }


    public function store(StoreSaleReturnRequest $request) {
        // Safety guard: never create a sale return with an empty cart.
        if (Cart::instance('sale_return')->content()->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Please add at least one product before saving the sale return.'
            ]);
        }

        $resolver = new ProductCodeResolver();
        $resolver->preload(Cart::instance('sale_return')->content()->pluck('id')->unique()->toArray());

        DB::transaction(function () use ($request, $resolver) {
            $cart = Cart::instance('sale_return');

            // Round overall payable to nearest whole rupee for customer-facing amounts
            $total_amount = round(floatval(str_replace([',', settings()->currency->symbol], '', (string)($request->total_amount ?? 0))), 2);
            $paid_amount = $request->paid_amount ?? 0;
            $payment_method = $request->payment_method ?? null;

            $due_amount = $total_amount - $paid_amount;

            // If no payment method was provided and no paid amount, leave payment_status NULL
            if (empty($payment_method) && ($paid_amount == 0 || $paid_amount === null)) {
                $payment_status = null;
            } else {
                if ($due_amount == $total_amount) {
                    $payment_status = 'Unpaid';
                } elseif ($due_amount > 0) {
                    $payment_status = 'Partial';
                } else {
                    $payment_status = 'Paid';
                }
            }

            $sale_return = SaleReturn::create([
                'date' => $request->date,
                'customer_id' => $request->customer_id,
                'customer_name' => Customer::findOrFail($request->customer_id)->customer_name,
                'area' => $request->area,
                'balance' => $request->opening_balance,
                'phone_no' => $request->phone,
                'excess_amount' => $request->excess_amount,
                'tax_percentage' => $request->tax_percentage ?? 0,
                'discount_percentage' => $request->discount_percentage ?? 0,
                'shipping_amount' => ($request->shipping_amount ?? 0),
                // overall calculation fields (stored in rupees; model converts to paise)
                'overall_nos' => $request->overall_nos ?? null,
                'overall_quantity' => $request->overall_quantity ?? null,
                'overall_gross_amount' => $request->overall_gross_amount ?? null,
                'overall_taxable_amount' => $request->overall_taxable_amount ?? null,
                'overall_tax_amount' => $request->overall_tax_amount ?? null,
                'overall_amount' => $request->overall_amount ?? null,
                'create_receipt' => $request->boolean('create_receipt'),
                'paid_amount' => ($paid_amount),
                'total_amount' => ($total_amount),
                'due_amount' => ($due_amount),
                'status' => $request->status,
                'payment_status' => $payment_status,
                'payment_method' => $payment_method,
                'note' => $request->note,
                'tax_amount' => $cart->tax(),
                'discount_amount' => $cart->discount(),
            ]);

            foreach ($cart->content() as $cart_item) {
                $product  = Product::with('category')->lockForUpdate()->findOrFail($cart_item->id);
                $options  = $cart_item->options;
                $vals     = CartItemCalculator::compute($cart_item, $product);

                $pcId = $resolver->resolve($cart_item->id, $options->code ?? null);
                // VAT% comes from the BRD VAT% input (gst_percent).
                $_sr_gst_pct = (float)($options->gst_percent ?? $vals['tax_percent']);
                $_sr_tax_amt = round($vals['sub_total'] * $_sr_gst_pct / 100, 2);

                SaleReturnDetail::create([
                    'sale_return_id'           => $sale_return->id,
                    'product_id'               => $cart_item->id,
                    'product_name'             => $cart_item->name,
                    'product_code'             => $options->code ?? null,
                    'product_code_id'          => $pcId,
                    'category'                 => $options->category ?? null,
                    'unit'                     => $options->unit ?? null,
                    'mrp'                      => $vals['mrp'],
                    'rate'                     => $vals['rate'],
                    'tax_percent'              => intval($_sr_gst_pct),
                    'product_tax_amount'       => $_sr_tax_amt,
                    'tax_amount'               => $_sr_tax_amt,
                    'product_discount_amount'  => $vals['discount_amount'],
                    'product_discount_type'    => $options->product_discount_type ?? 'percentage',
                    'quantity'                 => $cart_item->qty,
                    'price'                    => $cart_item->price,
                    'unit_price'               => $vals['unit_price'],
                    'sub_total'                => $vals['sub_total'],
                    'amount'                   => $vals['sub_total'],
                ]);

                // Increase quantity for sale returns only if receipt is to be created
                if ($request->boolean('create_receipt')) {
                    $product->addPurchaseStock($cart_item->qty);
                }
            }

            $cart->destroy();

            // Only create a payment record if there is a paid amount and a payment method provided
            if ($sale_return->paid_amount > 0 && !empty($payment_method)) {
                SaleReturnPayment::create([
                    'date' => $request->date,
                    'reference' => 'INV/'.$sale_return->reference,
                    'amount' => $sale_return->paid_amount,
                    'sale_return_id' => $sale_return->id,
                    'payment_method' => $payment_method
                ]);
            }

            if ($request->boolean('create_receipt')) {
                // Auto-create or sync a single lineless SalesReceipt using overall net rate
                $receiptAmount = floatval($sale_return->overall_amount ?? $sale_return->total_amount ?? 0); // rupees
                if ($receiptAmount > 0) {
                    // try to find an existing receipt linked to this sale_return
                    $existing = SalesReceipt::where('sale_return_id', $sale_return->id)->first();

                    if ($existing) {
                        $existing->total_amount = $receiptAmount;
                        $existing->payment_mode = null;
                        $existing->particular = 'From Sales return';
                        $existing->save();
                    } else {
                        // create new receipt (lineless)
                        $customer = Customer::findOrFail($sale_return->customer_id);
                        $receipt = SalesReceipt::create([
                            'date' => $sale_return->date,
                            'customer_id' => $sale_return->customer_id,
                            'particular' => 'From Sales return',
                            'payment_mode' => null,
                            'total_amount' => $receiptAmount,
                            'total_discount' => 0,
                            'created_by' => auth()->id(),
                            'sale_return_id' => $sale_return->id,
                        ]);

                        $receipt->reference = 'RE' . str_pad($receipt->id, 5, '0', STR_PAD_LEFT);
                        // For sale return receipts, no balance adjustment is made
                        $receipt->customer_balance_before = (int) round(floatval($customer->opening_balance ?? 0) * 100);
                        $receipt->applied_to_customer = 0; // No balance adjustment
                        $receipt->save();
                    }
                }
            }
        });

        toast('Sale Return Created!', 'success');

        return redirect()->route('sale-returns.index');
    }


    public function show(SaleReturn $sale_return) {
        abort_if(Gate::denies('show_sale_returns'), 403);

        $customer = Customer::findOrFail($sale_return->customer_id);
        $selectedCustomer = $sale_return->customer_id;

        // Use an isolated cart instance so reading a return does not overwrite
        // the 'sale_return' cart used by the Create / Edit pages.
        $this->populateSaleReturnCart($sale_return, 'sale_return_view');

        $readonly = true;
        $cartInstance = 'sale_return_view';
        return view('salesreturn::edit', compact('sale_return', 'customer', 'readonly', 'cartInstance', 'selectedCustomer'));
    }


    public function edit(SaleReturn $sale_return) {
        abort_if(Gate::denies('edit_sale_returns'), 403);

        $this->populateSaleReturnCart($sale_return);

        $selectedCustomer = $sale_return->customer_id;

        return view('salesreturn::edit', compact('sale_return', 'selectedCustomer'));
    }


    private function populateSaleReturnCart(SaleReturn $sale_return, string $instance = 'sale_return'): void
    {
        $sale_return_details = $sale_return->saleReturnDetails;

        // Batch-load all products in one query to avoid N+1
        $productIds = $sale_return_details->pluck('product_id')->all();
        $products   = Product::whereIn('id', $productIds)->get(['id', 'product_quantity', 'product_cost'])->keyBy('id');

        Cart::instance($instance)->destroy();
        $cart = Cart::instance($instance);

        foreach ($sale_return_details as $sale_return_detail) {
            $price = $sale_return_detail->price; // rupees
            $mrp   = $sale_return_detail->mrp !== null ? $sale_return_detail->mrp : $price;
            $product_discount_amount = $sale_return_detail->product_discount_amount; // rupees
            $discount_percent        = $sale_return_detail->discount_percent;        // percent or null

            // If the stored discount is percentage-based, ensure the cart option 'product_discount'
            // matches the percentage relative to the current price so Livewire's computed
            // display (product_discount / price) matches the stored percent.
            if ($sale_return_detail->product_discount_type === 'percentage' && is_numeric($discount_percent)) {
                $product_discount_for_cart = round(($discount_percent / 100) * $price, 2);
            } else {
                $product_discount_for_cart = $product_discount_amount;
            }

            $product = $products->get($sale_return_detail->product_id);

            $cart->add([
                'id'      => $sale_return_detail->product_id,
                'name'    => $sale_return_detail->product_name,
                'qty'     => $sale_return_detail->quantity,
                'price'   => $price,
                'weight'  => 1,
                'options' => [
                    'product_discount'         => $product_discount_for_cart,
                    'product_discount_type'    => $sale_return_detail->product_discount_type,
                    'product_discount_percent' => $discount_percent,
                    'sub_total'                => $sale_return_detail->sub_total,
                    'code'                     => ($sale_return_detail->productCode->code ?? $sale_return_detail->product_code),
                    'stock'                    => $product?->product_quantity ?? 0,
                    'product_tax'              => $sale_return_detail->product_tax_amount,
                    'unit_price'               => $sale_return_detail->unit_price,
                    'mrp'                      => $mrp,
                    'rate'                     => $sale_return_detail->rate !== null ? $sale_return_detail->rate : null,
                    'rate_before_discount'     => $sale_return_detail->rate ?? $mrp,
                    'tax_percent'              => $sale_return_detail->tax_percent,
                    'gst_percent'              => $sale_return_detail->tax_percent,
                    'category'                 => $sale_return_detail->category ?? '-',
                    'unit'                     => $sale_return_detail->unit ?? 'Nos',
                    'tax_amount'               => $sale_return_detail->tax_amount ?? $sale_return_detail->product_tax_amount,
                    'amount'                   => $sale_return_detail->amount ?? $sale_return_detail->sub_total,
                    'product_cost'             => (float)($product?->product_cost ?? 0),
                ]
            ]);
        }
    }


    public function update(UpdateSaleReturnRequest $request, SaleReturn $sale_return) {
        // Safety guard: abort update when cart is empty.
        // Prevents deleting existing return details and recreating zero rows.
        if (Cart::instance('sale_return')->content()->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Cannot update sale return with an empty cart. Please add products and try again.'
            ]);
        }

        $resolver = new ProductCodeResolver();
        $resolver->preload(Cart::instance('sale_return')->content()->pluck('id')->unique()->toArray());

        DB::transaction(function () use ($request, $sale_return, $resolver) {
            $cart = Cart::instance('sale_return');

            $total_amount = $request->total_amount ?? 0;
            $paid_amount = $request->paid_amount ?? 0;
            $payment_method = $request->payment_method ?? null;

            $due_amount = $total_amount - $paid_amount;

            // If no payment method was provided and no paid amount, leave payment_status NULL
            if (empty($payment_method) && ($paid_amount == 0 || $paid_amount === null)) {
                $payment_status = null;
            } else {
                if ($due_amount == $total_amount) {
                    $payment_status = 'Unpaid';
                } elseif ($due_amount > 0) {
                    $payment_status = 'Partial';
                } else {
                    $payment_status = 'Paid';
                }
            }

            // Reverse stock changes if the return previously had a receipt
            if ($sale_return->create_receipt) {
                foreach ($sale_return->saleReturnDetails as $sale_return_detail) {
                    $product = Product::lockForUpdate()->findOrFail($sale_return_detail->product_id);
                    $product->removePurchaseStock($sale_return_detail->quantity);
                    $sale_return_detail->delete();
                }
            } else {
                // If no receipt, just delete details without stock reversal
                foreach ($sale_return->saleReturnDetails as $sale_return_detail) {
                    $sale_return_detail->delete();
                }
            }

            $sale_return->update([
                'date' => $request->date,
                'reference' => $request->reference,
                'customer_id' => $request->customer_id,
                'customer_name' => Customer::findOrFail($request->customer_id)->customer_name,
                'area' => $request->area,
                'balance' => $request->opening_balance,
                'phone_no' => $request->phone,
                'excess_amount' => $request->excess_amount,
                'tax_percentage' => $request->tax_percentage ?? 0,
                'discount_percentage' => $request->discount_percentage ?? 0,
                'shipping_amount' => ($request->shipping_amount ?? 0),
                // persist overall calculation fields so edit page shows Other/Adj/etc.
                'overall_nos' => $request->overall_nos ?? null,
                'overall_quantity' => $request->overall_quantity ?? null,
                'overall_gross_amount' => $request->overall_gross_amount ?? null,
                'overall_taxable_amount' => $request->overall_taxable_amount ?? null,
                'overall_tax_amount' => $request->overall_tax_amount ?? null,
                'overall_amount' => $request->overall_amount ?? null,
                'create_receipt' => $request->boolean('create_receipt'),
                'paid_amount' => ($paid_amount),
                'total_amount' => ($total_amount),
                'due_amount' => ($due_amount),
                'status' => $request->status,
                'payment_status' => $payment_status,
                'payment_method' => $payment_method,
                'note' => $request->note,
                'tax_amount' => $cart->tax(),
                'discount_amount' => $cart->discount(),
            ]);

            foreach ($cart->content() as $cart_item) {
                $product  = Product::with('category')->lockForUpdate()->findOrFail($cart_item->id);
                $options  = $cart_item->options;
                $vals     = CartItemCalculator::compute($cart_item, $product);

                $pcId = $resolver->resolve($cart_item->id, $options->code ?? null);
                // VAT% comes from the BRD VAT% input (gst_percent).
                $_sr_gst_pct = (float)($options->gst_percent ?? $vals['tax_percent']);
                $_sr_tax_amt = round($vals['sub_total'] * $_sr_gst_pct / 100, 2);

                SaleReturnDetail::create([
                    'sale_return_id'           => $sale_return->id,
                    'product_id'               => $cart_item->id,
                    'product_name'             => $cart_item->name,
                    'product_code'             => $options->code ?? null,
                    'product_code_id'          => $pcId,
                    'category'                 => $options->category ?? null,
                    'unit'                     => $options->unit ?? null,
                    'mrp'                      => $vals['mrp'],
                    'rate'                     => $vals['rate'],
                    'tax_percent'              => intval($_sr_gst_pct),
                    'product_tax_amount'       => $_sr_tax_amt,
                    'tax_amount'               => $_sr_tax_amt,
                    'product_discount_amount'  => $vals['discount_amount'],
                    'product_discount_type'    => $options->product_discount_type ?? 'percentage',
                    'quantity'                 => $cart_item->qty,
                    'price'                    => $cart_item->price,
                    'unit_price'               => $vals['unit_price'],
                    'sub_total'                => $vals['sub_total'],
                    'amount'                   => $vals['sub_total'],
                ]);

                // Increase quantity for sale returns only if receipt is to be created
                if ($request->boolean('create_receipt')) {
                    $product->addPurchaseStock($cart_item->qty);
                }
            }

            $cart->destroy();
            // receipt handling for update is performed via helper below
            if ($request->boolean('create_receipt')) {
                $this->createOrSyncReceipt($sale_return, floatval($sale_return->overall_amount ?? $sale_return->total_amount ?? 0));
            } else {
                $this->deleteReceiptAndRestore($sale_return);
            }

        });

        toast('Sale Return Updated!', 'info');

        return redirect()->route('sale-returns.index');
    }


    public function destroy(SaleReturn $sale_return) {
        abort_if(Gate::denies('delete_sale_returns'), 403);

        // Prevent deletion if a linked SalesReceipt exists for this sale return
        $existing = SalesReceipt::where('sale_return_id', $sale_return->id)->first();
        if ($existing) {
            toast('Cannot delete Sale Return: linked Sales Receipt exists. Delete the receipt first.', 'error');
            return redirect()->route('sale-returns.index');
        }

        DB::transaction(function () use ($sale_return) {
            // Reverse stock changes only if the return had a receipt
            if ($sale_return->create_receipt) {
                foreach ($sale_return->saleReturnDetails as $detail) {
                    $product = Product::lockForUpdate()->findOrFail($detail->product_id);
                    $product->removePurchaseStock($detail->quantity);
                }
            }

            // If there's a linked receipt, delete it and restore previous customer's balance
            $this->deleteReceiptAndRestore($sale_return);

            $sale_return->delete();
        });

        toast('Sale Return Deleted!', 'warning');

        return redirect()->route('sale-returns.index');
    }

    /**
     * Create or sync a lineless SalesReceipt for the given SaleReturn.
     */
    private function createOrSyncReceipt(SaleReturn $sale_return, float $receiptAmount)
    {
        if ($receiptAmount <= 0) return;

        // lock customer row to avoid concurrent balance updates
        $customer = Customer::lockForUpdate()->findOrFail($sale_return->customer_id);

        $existing = SalesReceipt::where('sale_return_id', $sale_return->id)->lockForUpdate()->first();

        if ($existing) {
            $oldTotal = floatval($existing->total_amount ?? 0);
            if ($existing->customer_id != $sale_return->customer_id) {
                $custIds = [$existing->customer_id, $sale_return->customer_id];
                sort($custIds);
                $first = Customer::lockForUpdate()->find($custIds[0]);
                $second = Customer::lockForUpdate()->find($custIds[1]);

                if ($first && $first->id == $existing->customer_id) {
                    // Removed balance adjustment
                    if ($second) {
                        // Removed balance adjustment
                        $existing->customer_id = $sale_return->customer_id;
                        $existing->customer_balance_before = (int) round(floatval($second->opening_balance ?? 0) * 100);
                        $existing->applied_to_customer = 0; // No adjustment
                    }
                } else {
                    // Removed balance adjustment
                    if ($second && $second->id == $existing->customer_id) {
                        // Removed balance adjustment
                        $existing->customer_id = $sale_return->customer_id;
                        $existing->customer_balance_before = (int) round(floatval($first->opening_balance ?? 0) * 100);
                        $existing->applied_to_customer = 0; // No adjustment
                    }
                }
            } else {
                // Removed balance adjustment
                $customer = Customer::findOrFail($sale_return->customer_id);
                $existing->customer_balance_before = (int) round(floatval($customer->opening_balance ?? 0) * 100);
                $existing->applied_to_customer = 0; // No adjustment
            }

            $existing->total_amount = $receiptAmount;
            $existing->payment_mode = null;
            $existing->particular = 'From Sales return';
            $existing->save();
        } else {
            // Removed balance calculation
            $customer = Customer::findOrFail($sale_return->customer_id);
            $receipt = SalesReceipt::create([
                'date' => $sale_return->date,
                'customer_id' => $sale_return->customer_id,
                'particular' => 'From Sales return',
                'payment_mode' => null,
                'total_amount' => $receiptAmount,
                'total_discount' => 0,
                'created_by' => auth()->id(),
                'sale_return_id' => $sale_return->id,
            ]);

            $receipt->reference = 'RE' . str_pad($receipt->id, 5, '0', STR_PAD_LEFT);
            $receipt->customer_balance_before = (int) round(floatval($customer->opening_balance ?? 0) * 100);
            $receipt->applied_to_customer = 0; // No balance adjustment
            $receipt->save();

            // Removed balance adjustment
        }
    }

    /**
     * Delete any linked SalesReceipt and restore the full amount to the previous customer's opening balance.
     */
    private function deleteReceiptAndRestore(SaleReturn $sale_return)
    {
        $existing = SalesReceipt::where('sale_return_id', $sale_return->id)->lockForUpdate()->first();
        if (!$existing) return;

        // Removed balance restore since no adjustment was done
        $existing->delete();
    }
}
