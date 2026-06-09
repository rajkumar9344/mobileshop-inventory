<?php

namespace Modules\Sale\Http\Controllers;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\People\Entities\Customer;
use Modules\Product\Entities\Category;
use Modules\Product\Entities\Product;
use Modules\Sale\Entities\Sale;
use Modules\Sale\Entities\SaleDetails;
use Modules\Sale\Entities\SalePayment;
use Modules\Sale\Events\SaleFullyPaid;
use Illuminate\Support\Facades\Event;
use Modules\Sale\Http\Requests\StorePosSaleRequest;

class PosController extends Controller
{

    public function index() {
        Cart::instance('sale')->destroy();

        $customers = Customer::where('is_active', true)->orderBy('customer_name', 'asc')->get();
    $product_categories = Category::where('status', true)->get();

        return view('sale::pos.index', compact('product_categories', 'customers'));
    }


    public function store(StorePosSaleRequest $request) {
        $shouldDispatch = false;
        $dispatchSaleId = null;

        try {
            // Credit Limit Check - do this BEFORE the transaction so lock update is saved
            $customer = Customer::findOrFail($request->customer_id);
            // Include any submitted discount when computing due amount for credit checks
            $dueAmount = floatval($request->total_amount ?? 0) - floatval($request->paid_amount ?? 0) - floatval($request->discount_amount ?? 0);
            $potentialBalance = ($customer->opening_balance ?? 0) + $dueAmount;
            
            // Only enforce credit limit if one is set (> 0)
            if ((($customer->credit_limit ?? 0) > 0) && ($potentialBalance >= $customer->credit_limit)) {
                // Lock the customer - do this outside transaction so it persists
                $customer->update(['lock' => 'Yes']);
                return redirect()->back()->withErrors(['customer_id' => 'Credit Limit reached for this Customer. Customer has been locked.']);
            }

            DB::transaction(function () use ($request, &$shouldDispatch, &$dispatchSaleId, $customer) {

            // Subtract discount from due so status reflects discounts
            $due_amount = $request->total_amount - $request->paid_amount - ($request->discount_amount ?? 0);

            $payable = $request->total_amount - ($request->discount_amount ?? 0);

            if ($due_amount == $payable) {
                $payment_status = 'Unpaid';
            } elseif ($due_amount > 0) {
                $payment_status = 'Partial';
            } else {
                $payment_status = 'Paid';
            }

            $sale = Sale::createWithRetry([
                'date' => now()->format('Y-m-d'),
                'customer_id' => $request->customer_id,
                'customer_name' => Customer::findOrFail($request->customer_id)->customer_name,
                'area' => $customer->area ?? '',
                'balance' => $customer->opening_balance,
                'bill_type' => 'Cash',
                'days' => 0,
                'due_date' => now()->format('Y-m-d'),
                'phone_no' => $customer->phone_no ?? '',
                'discount_type' => null,
                'tax_percentage' => 0, // No longer using percentage
                'discount_percentage' => 0, // No longer using percentage
                'shipping_amount' => $request->shipping_amount,
                'paid_amount' => $request->paid_amount,
                'total_amount' => $request->total_amount,
                'due_amount' => $due_amount,
                'status' => 'Completed',
                'payment_status' => $payment_status,
                'payment_method' => $request->payment_method,
                'note' => $request->note,
                'tax_amount' => ($request->tax_amount ?? 0), // Store the tax amount
                'discount_amount' => ($request->discount_amount ?? 0), // Pass rupee; model will convert
                'overall_nos' => 0,
                'overall_quantity' => 0,
                'overall_gross_amount' => 0,
                'overall_taxable_amount' => 0,
                'overall_cgst' => 0,
                'overall_sgst' => 0,
                'overall_igst' => 0,
                'overall_tax_amount' => 0,
                'overall_tcs_percent' => 0,
                'overall_amount' => 0,
                'overall_other' => 0,
                'overall_adj' => 0,
                'overall_net_rate' => 0,
            ]);            foreach (Cart::instance('sale')->content() as $cart_item) {
                SaleDetails::create([
                    'sale_id' => $sale->id,
                    'product_id' => $cart_item->id,
                    'product_name' => $cart_item->name,
                    'product_code' => $cart_item->options->code,
                    'quantity' => $cart_item->qty,
                    'price' => $cart_item->price,
                    'unit_price' => $cart_item->options->unit_price,
                    'sub_total' => $cart_item->options->sub_total,
                    'product_discount_amount' => $cart_item->options->product_discount,
                    'product_discount_type' => $cart_item->options->product_discount_type,
                    'product_tax_amount' => $cart_item->options->product_tax,
                ]);

                $product = Product::findOrFail($cart_item->id);
                $product->update([
                    'product_quantity' => $product->product_quantity - $cart_item->qty
                ]);
            }

            Cart::instance('sale')->destroy();

            $shouldDispatch = false;
            $dispatchSaleId = null;

            if ($sale->paid_amount > 0) {
                SalePayment::create([
                    'date' => now()->format('Y-m-d'),
                    'reference' => 'INV/'.$sale->reference,
                    'amount' => $sale->paid_amount,
                    'sale_id' => $sale->id,
                    'payment_method' => $request->payment_method
                ]);

                // If this sale was created as Paid, mark for dispatch after commit
                if (($sale->payment_status ?? '') === 'Paid') {
                    $shouldDispatch = true;
                    $dispatchSaleId = $sale->id;
                }
            }
        });

        if ($shouldDispatch && $dispatchSaleId) {
            event(new SaleFullyPaid($dispatchSaleId));
        }

        toast('POS Sale Created!', 'success');

        return redirect()->route('sales.index');
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Credit Limit reached for this Customer') {
                return redirect()->back()->withErrors(['customer_id' => 'Credit Limit reached for this Customer']);
            }
            throw $e;
        }
    }

    /**
     * Check if customer has reached their credit limit
     * Returns JSON for AJAX validation
     */
    public function checkCreditLimit(\Illuminate\Http\Request $request)
    {
        $customerId = $request->customer_id;
        if (!$customerId) {
            return response()->json(['error' => 'Customer ID required'], 400);
        }
        $customer = Customer::find($customerId);
        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $totalAmount = floatval($request->total_amount ?? 0);
        $paidAmount = floatval($request->paid_amount ?? 0);
        $dueAmount = $totalAmount - $paidAmount - floatval($request->discount_amount ?? 0);
        $potentialBalance = ($customer->opening_balance ?? 0) + $dueAmount;

        // Only enforce credit limit if one is set (> 0)
        $reached = (($customer->credit_limit ?? 0) > 0) && ($potentialBalance >= $customer->credit_limit);
        return response()->json(['credit_limit_reached' => $reached]);
    }
}
