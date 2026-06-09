<?php

namespace Modules\Sale\Http\Controllers;

use Modules\Sale\DataTables\SalesDataTable;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Modules\People\Entities\Customer;
use Modules\SalesReceipt\Entities\SalesReceipt;
use Modules\SalesReceipt\Entities\SalesReceiptLine;
use Modules\Product\Entities\Product;
use App\Services\ProductCodeResolver;
use Modules\Product\Entities\ProductCode;
use Modules\Sale\Entities\Sale;
use Modules\Sale\Entities\SaleDetails;
use Modules\Sale\Entities\SalePayment;
use Modules\Sale\Events\SaleFullyPaid;
use Modules\SalesReceipt\Listeners\CreateSalesReceiptForPaidSale;
use Modules\Sale\Http\Requests\StoreSaleRequest;
use App\Services\CartItemCalculator;
use Modules\Sale\Http\Requests\UpdateSaleRequest;

class SaleController extends Controller
{

    // Use ProductCodeResolver service for code lookups (preload + resolve)

    public function index(SalesDataTable $dataTable) {
        abort_if(Gate::denies('access_sales'), 403);

        return $dataTable->render('sale::index');
    }

    // ProductCode resolution is handled by App\Services\ProductCodeResolver

    /**
     * Aggregated totals for listing (supports optional year/month filters).
     */
    public function totals() {
        abort_if(Gate::denies('access_sales'), 403);

        // Build base query with optional filters. Support new start_date/end_date
        $start = request()->get('start_date');
        $end = request()->get('end_date');
        $year = request()->get('year');
        $month = request()->get('month');

        $base = Sale::query();

        if ($start || $end) {
            try {
                if ($start && $end) {
                    $base->whereBetween('date', [date('Y-m-d', strtotime($start)), date('Y-m-d', strtotime($end))]);
                } elseif ($start) {
                    $base->whereDate('date', '>=', date('Y-m-d', strtotime($start)));
                } elseif ($end) {
                    $base->whereDate('date', '<=', date('Y-m-d', strtotime($end)));
                }
            } catch (\Exception $e) {
                // ignore invalid dates and fall back to no date filter
            }
        } else {
            if ($year) {
                $base->whereYear('date', $year);
            }

            if ($month) {
                $base->whereMonth('date', str_pad($month, 2, '0', STR_PAD_LEFT));
            }
        }

        // Apply additional filters forwarded from DataTable (e.g., customer search or customer_id)
        $customerFilter = request()->get('customer_id');
        if ($customerFilter) {
            $base->where('customer_id', $customerFilter);
        }

        $searchFilter = request()->get('search');
        if ($searchFilter) {
            $term = trim($searchFilter);
            $base->where(function($q) use ($term) {
                $q->where('customer_name', 'like', "%{$term}%")
                  ->orWhere('reference', 'like', "%{$term}%")
                  ->orWhere('area', 'like', "%{$term}%");
            });
        }

        // Exclude Draft sales from aggregated totals (they should not affect financial summaries)
        $base->where(function($q){
            $q->whereNull('status')->orWhere('status', '!=', 'Draft');
        });

        $totals = [];
        $totals['overall_count'] = $base->count();

        // Use COALESCE to sum overall_amount with fallback to total_amount
        $totals['overall_total_amount'] = $base->sum(DB::raw('COALESCE(overall_amount, total_amount)')) / 100;

        // Outstanding (sum of positive due_amounts)
        $totals['overall_outstanding'] = $base->sum(DB::raw('CASE WHEN due_amount > 0 THEN due_amount ELSE 0 END')) / 100;

        // Advances (sum of absolute negative due_amounts)
        $totals['overall_advances'] = $base->sum(DB::raw('CASE WHEN due_amount < 0 THEN -due_amount ELSE 0 END')) / 100;

        // Net balance (outstanding - advances) -- also equal to sum(due_amount)/100
        $totals['overall_balance'] = $base->sum(DB::raw('COALESCE(due_amount,0)')) / 100;

        $totals['overall_received_amount'] = $base->sum(DB::raw('COALESCE(paid_amount,0)')) / 100;
        $totals['overall_cgst'] = $base->sum(DB::raw('COALESCE(overall_cgst,0)')) / 100;
        $totals['overall_sgst'] = $base->sum(DB::raw('COALESCE(overall_sgst,0)')) / 100;
        $totals['overall_igst'] = $base->sum(DB::raw('COALESCE(overall_igst,0)')) / 100;
        $totals['overall_tax_amount'] = $base->sum(DB::raw('COALESCE(overall_tax_amount, tax_amount,0)')) / 100;

        return response()->json($totals);
    }


    public function create() {
        abort_if(Gate::denies('create_sales'), 403);

        Cart::instance('sale')->destroy();

        return view('sale::create');
    }

    public function getNextReference(\Illuminate\Http\Request $request) {
        $billDate = $request->query('date');
        $number = Sale::getNextSaleNumber($billDate);
        $reference = Sale::generateSaleReference($number, $billDate);
        return response()->json(['reference' => $reference]);
    }

    /**
     * Auto-save sale as draft via AJAX (called on page unload, back button, periodic save)
     */
    public function autoSaveDraft(\Illuminate\Http\Request $request) {
        try {
            // Basic validation - require cart items to save draft
            $cartContent = Cart::instance('sale')->content();
            if ($cartContent->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Insufficient data to save draft']);
            }

            // Check if we're updating an existing draft or creating a new one
            $existingDraftId = $request->input('draft_id');
            
            // Prefetch product codes for cart items to avoid N+1 DB queries
            $resolver = new ProductCodeResolver();
            $resolver->preload($cartContent->pluck('id')->unique()->toArray());

            DB::transaction(function () use ($request, &$existingDraftId, $cartContent, $resolver) {
                // Calculate totals from Cart session (more reliable than form values)
                $overall_nos = $cartContent->count();
                $overall_quantity = 0;
                $overall_gross_amount = 0;
                $overall_tax_amount = 0;
                
                $overall_taxable_amount = 0;
                foreach ($cartContent as $item) {
                    $overall_quantity += $item->qty;
                    $_opts          = $item->options;
                    $_mrp           = (float) ($_opts->mrp ?? $item->price);
                    $_taxPct        = (float) ($_opts->tax_percent ?? 0);
                    $_discPct       = (float) ($_opts->product_discount_percent ?? 0);
                    $_cashDiscPct   = (float) ($_opts->cash_discount_percent ?? 0);
                    $_cashDiscAmt   = (float) ($_opts->cash_discount_amount  ?? 0);
                    $_afterPct      = $_mrp * (1 - $_discPct / 100);
                    $_cashTotal     = $_afterPct * ($_cashDiscPct / 100) + $_cashDiscAmt;
                    $_netRate       = max(0.0, round($_afterPct - $_cashTotal, 2));
                    $_rowTaxable    = round($_netRate * $item->qty, 2);
                    $_rowTax        = round($_rowTaxable * $_taxPct / 100, 2);
                    // Gross = MRP × qty (mirrors the display "Gross(Amount)" column)
                    $overall_gross_amount  += round($_mrp * $item->qty, 2);
                    $overall_taxable_amount += $_rowTaxable;
                    $overall_tax_amount    += $_rowTax;
                }
                $overall_taxable_amount = round($overall_taxable_amount, 2);
                $overall_cgst = $overall_tax_amount / 2;
                $overall_sgst = $overall_tax_amount / 2;
                $overall_igst = 0;
                // Match the Livewire front-end: for sale modules, "Amount" = taxable base (pre-tax net total).
                // Tax is shown separately; overall_net_rate is the customer-payable amount (= Amount + adj + other).
                $overall_amount = $overall_taxable_amount;
                $overall_other = (float) ($request->overall_other ?? 0);
                $overall_adj = (float) ($request->overall_adj ?? 0);
                $overall_net_rate = $overall_amount + $overall_adj + $overall_other;
                // Round customer-payable overall net rate to nearest whole rupee
                $overall_net_rate = round($overall_net_rate, 0);
                
                // Use calculated values, fallback to request values only if calculated is 0
                $base_total = $overall_net_rate > 0 ? $overall_net_rate : ($request->overall_net_rate ?? $request->total_amount ?? 0);
                $submitted_discount = 0;
                if (isset($request->discount_amount) && is_numeric($request->discount_amount) && (float)$request->discount_amount > 0) {
                    $submitted_discount = (float) $request->discount_amount;
                }
                // The `total_amount` represents the bill's canonical amount (do not reduce by
                // the global discount here). Keep discounts stored separately in
                // `discount_amount` so reports/ledgers can show discounts independently.
                $total_amount = $base_total;
                // Round total as customer-payable whole rupee
                $total_amount = round($total_amount, 0);

                $saleData = [
                    'date' => $request->date ?? now()->format('Y-m-d'),
                    'due_date' => $request->due_date,
                    'customer_id' => $request->customer_id,
                    'customer_name' => $request->customer_id ? (Customer::find($request->customer_id)->customer_name ?? 'Draft Customer') : 'Draft Customer',
                    'area' => $request->area,
                    'balance' => $request->opening_balance ?? 0,
                    'bill_type' => $request->bill_type ?? 'Cash',
                    'days' => $request->days ?? 0,
                    'phone_no' => $request->phone,
                    'vehicle_name' => $request->vehicle_name ?? null,
                    'vehicle_no' => $request->vehicle_no ?? null,
                    'discount_type' => $request->discount_type,
                    'tax_percentage' => $request->tax_percentage ?? 0,
                    'discount_percentage' => $request->discount_percentage ?? 0,
                    'shipping_amount' => $request->shipping_amount ?? 0,
                    'paid_amount' => 0,
                    'total_amount' => $total_amount ?? 0,
                    'due_amount' => $total_amount ?? 0,
                    'status' => 'Draft',
                    'payment_status' => 'Unpaid',
                    'payment_method' => $request->payment_method,
                    'note' => $request->note,
                    'tax_amount' => $overall_tax_amount,
                    'discount_amount' => ($submitted_discount ?? 0),
                    'overall_nos' => $overall_nos,
                    'overall_quantity' => $overall_quantity,
                    'overall_gross_amount' => $overall_gross_amount,
                    'overall_taxable_amount' => $overall_taxable_amount,
                    'overall_cgst' => $overall_cgst,
                    'overall_sgst' => $overall_sgst,
                    'overall_igst' => $overall_igst,
                    'overall_tax_amount' => $overall_tax_amount,
                    'overall_tcs_percent' => $request->overall_tcs_percent ?? 0,
                    'overall_amount' => $overall_amount,
                    'overall_other' => $overall_other,
                    'overall_adj' => $overall_adj,
                    'overall_net_rate' => $overall_net_rate,
                ];

                if ($existingDraftId) {
                    // Update existing draft
                    $sale = Sale::where('id', $existingDraftId)->where('status', 'Draft')->first();
                    if ($sale) {
                        $sale->update($saleData);
                        // Delete existing sale details and recreate
                        SaleDetails::where('sale_id', $sale->id)->delete();
                    } else {
                        // Draft not found or not a draft anymore, create new
                        $sale = Sale::createWithRetry($saleData);
                        $existingDraftId = $sale->id;
                    }
                } else {
                    // Create new draft
                    $sale = Sale::createWithRetry($saleData);
                    $existingDraftId = $sale->id;
                }

                // Save cart items as sale details
                foreach ($cartContent as $cart_item) {
                    $product = Product::with('category')->find($cart_item->id);
                    if (!$product) continue;

                    $options      = $cart_item->options;
                    $vals         = CartItemCalculator::compute($cart_item, $product);
                    $categoryName = $options->category
                        ?? optional($product->category)->category_name
                        ?? optional($product->category)->name;
                    $hsn = $options->hsn ?? $product->hsn;

                    $pcId = $resolver->resolve($cart_item->id, $options->code ?? null);

                    SaleDetails::create([
                        'sale_id'                  => $sale->id,
                        'product_id'               => $cart_item->id,
                        'product_name'             => $cart_item->name,
                        'product_code'             => $options->code,
                        'product_code_id'          => $pcId,
                        'mrp'                      => $vals['mrp'],
                        'rate'                     => $vals['rate'],
                        'tax_percentage'           => $vals['tax_percent'],
                        'tax_amount'               => $vals['tax_amount'],
                        'cash_discount_percentage' => $vals['cash_discount_percent'],
                        'cash_discount_amount'     => $vals['cash_discount_amount'],
                        'discount_amount'          => $vals['discount_amount'],
                        'discount_type'            => $options->product_discount_type,
                        'discount_percent'         => (float) ($options->product_discount_percent ?? 0),
                        'category'                 => $categoryName,
                        'hsn'                      => $hsn,
                        'quantity'                 => $cart_item->qty,
                        'price'                    => (float) $cart_item->price,
                        'unit_price'               => $vals['unit_price'],
                        'sub_total'                => $vals['sub_total'],
                        'product_discount_amount'  => $vals['discount_amount'],
                        'product_discount_type'    => $options->product_discount_type,
                        'product_tax_amount'       => $vals['tax_amount'],
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Draft saved automatically',
                'draft_id' => $existingDraftId
            ]);

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Auto-save draft DB error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Unable to save draft. Please try again or contact support if the problem persists.'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Auto-save draft failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Unable to save draft. Please try again.'
            ], 500);
        }
    }


    public function store(StoreSaleRequest $request) {
    $shouldDispatch = false;
    $dispatchSaleId = null;
    $isDraft = $request->input('is_draft', 0) == 1;

    // Safety guard: never save a sale with an empty cart.
    // This prevents delete/recreate flows from leaving zero detail rows.
    if (Cart::instance('sale')->content()->isEmpty()) {
        toast('Cannot save sale without products', 'error');
        return redirect()->back();
    }

    try {

    // Credit Limit Check - skip for drafts or when no customer selected
    // Do this BEFORE the transaction so lock update is saved even if we abort
    if (!$isDraft && $request->customer_id) {
        $customer = Customer::findOrFail($request->customer_id);
        $dueAmount = floatval($request->total_amount ?? 0) - floatval($request->paid_amount ?? 0);
        $potentialBalance = ($customer->opening_balance ?? 0) + $dueAmount;

        // Only enforce credit limit if one is set (> 0)
        $limit = floatval($customer->credit_limit ?? 0);
        $grace = 1000; // rupees

        // Block (lock) only when potential balance exceeds credit_limit + grace
        if ($limit > 0 && $potentialBalance > ($limit + $grace)) {
            // Lock the customer - do this outside transaction so it persists
            $customer->update(['lock' => 'Yes']);
            return redirect()->back()->withErrors(['customer_id' => 'Credit Limit reached for this Customer. Customer has been locked.']);
        }
        // If potentialBalance is between limit and limit+grace, allow but frontend should warn.
    }

        // Prefetch product codes for the active sale cart to reduce lookups
        $resolver = new ProductCodeResolver();
        $resolver->preload(Cart::instance('sale')->content()->pluck('id')->unique()->toArray());

        DB::transaction(function () use ($request, &$shouldDispatch, &$dispatchSaleId, $isDraft, $resolver) {
            $cart = Cart::instance('sale');

            // Determine the base total from overall_net_rate or hidden total_amount.
            // Coerce posted strings (may contain commas/currency) to numeric floats.
            $rawOverallNet = $request->overall_net_rate ?? $request->total_amount ?? 0;
            $base_total = floatval(str_replace([',', settings()->currency->symbol], '', (string) $rawOverallNet));

            // Prefer an explicit discount_amount submitted via the form (global discount).
            // If not present, fall back to any cart-level discount value.
            // Do NOT infer a discount by reconciling the posted net-rate with the cart total.
            $submitted_discount = 0;
            if (isset($request->discount_amount) && is_numeric(str_replace(',', '', (string)$request->discount_amount)) && (float)str_replace(',', '', (string)$request->discount_amount) > 0) {
                // Use explicit discount input when provided (numeric)
                $submitted_discount = (float) str_replace(',', '', (string)$request->discount_amount);
            } else {
                // Try cart-level discount first
                $cartDiscount = (float) $cart->discount();
                if ($cartDiscount > 0) {
                    $submitted_discount = $cartDiscount;
                }
            }

            // The `total_amount` represents the canonical bill amount (do not reduce by
            // the global discount here). Discounts are recorded separately in
            // `discount_amount` so they don't alter the bill's original total.
            $total_amount = $base_total;
            // Round customer-payable amounts to whole-rupee (nearest)
            $total_amount = round($total_amount, 0);

            // Prevent saving empty drafts
            if ($total_amount <= 0 && $isDraft) {
                if ($existingDraftId = $request->input('draft_id')) {
                    Sale::find($existingDraftId)?->delete();
                }
                throw new \DomainException('EMPTY_DRAFT'); // rolls back transaction cleanly
            }

            // Coerce paid_amount to numeric (may be posted as masked/display string)
            $paidNumeric = floatval(str_replace([',', settings()->currency->symbol], '', (string) ($request->paid_amount ?? 0)));
            // Subtract submitted global discount from due amount so customer's balance reflects discounts
            $due_amount = $total_amount - $paidNumeric - ($submitted_discount ?? 0);

            // Handle payment status calculation
            if ($isDraft) {
                // For drafts, do not apply any payments
                $initialPaid = 0;
                $initialDue = $total_amount ?? 0;
                $payment_status = 'Unpaid';
            } else {
                // Decide whether this payment should be applied immediately.
                // Settled logic has been moved to the receipt module; here we apply payments
                // whenever a positive paid_amount is submitted.
                $paymentShouldApplyReq = ($request->paid_amount > 0);

                // If a paid amount was provided, persist it as the initial paid amount.
                $initialPaid = $paymentShouldApplyReq ? $request->paid_amount : 0;
                $initialDue = $paymentShouldApplyReq ? $due_amount : $total_amount;

                if ($initialDue == $total_amount) {
                    $payment_status = 'Unpaid';
                } elseif ($initialDue > 0) {
                    $payment_status = 'Partial';
                } else {
                    $payment_status = 'Paid';
                }
            }

            $saleData = [
                'date' => $request->date,
                'due_date' => $request->due_date,
                'customer_id' => $request->customer_id,
                'customer_name' => $request->customer_id ? Customer::findOrFail($request->customer_id)->customer_name : 'Draft Customer',
                'area' => $request->area,
                'balance' => $request->opening_balance ?? 0,
                'bill_type' => $request->bill_type ?? 'Cash',
                'days' => $request->days ?? 0,
                'phone_no' => $request->phone,
                'vehicle_name' => $request->vehicle_name ?? null,
                'vehicle_no' => $request->vehicle_no ?? null,
                'discount_type' => $request->discount_type,
                'tax_percentage' => $request->tax_percentage ?? 0,
                'discount_percentage' => $request->discount_percentage ?? 0,
                'shipping_amount' => $request->shipping_amount ?? 0,
                'paid_amount' => $initialPaid,
                'total_amount' => $total_amount ?? 0,
                'due_amount' => $initialDue,
                'status' => $isDraft ? 'Draft' : ($request->status ?? 'Pending'),
                'payment_status' => $payment_status,
                'payment_method' => $request->payment_method,
                'note' => $request->note,
                'tax_amount' => $cart->tax(),
                // Persist the submitted global discount (in paise). If none, this will be 0.
                'discount_amount' => ($submitted_discount ?? 0),
                // Overall Calculations
                'overall_nos'      => $request->overall_nos      ?? $cart->content()->count(),
                'overall_quantity'  => $request->overall_quantity  ?? $cart->content()->sum('qty'),
                'overall_gross_amount' => ($request->overall_gross_amount ?? $cart->total()),
                'overall_taxable_amount' => ($request->overall_taxable_amount ?? ($cart->total() - $cart->tax())),
                'overall_cgst' => ($request->overall_cgst ?? ($cart->tax() / 2)),
                'overall_sgst' => ($request->overall_sgst ?? ($cart->tax() / 2)),
                'overall_igst' => ($request->overall_igst ?? 0),
                'overall_tax_amount' => ($request->overall_tax_amount ?? $cart->tax()),
                'overall_tcs_percent' => $request->overall_tcs_percent ?? 0,
                'overall_amount' => ($request->overall_amount ?? $total_amount),
                'overall_other' => ($request->overall_other ?? 0),
                'overall_adj' => ($request->overall_adj ?? 0),
                // Persist rounded customer-payable overall net rate
                'overall_net_rate' => round(str_replace(',', '', $request->overall_net_rate ?? $total_amount), 0),
            ];
            
            // Check if we're updating an existing draft (from auto-save)
            $existingDraftId = $request->input('draft_id');
            $sale = null;
            
            if ($existingDraftId) {
                // Try to find and update the existing draft
                $sale = Sale::where('id', $existingDraftId)->where('status', 'Draft')->first();
                if ($sale) {
                    $sale->update($saleData);
                    // Delete existing sale details to recreate them
                    SaleDetails::where('sale_id', $sale->id)->delete();
                }
            }
            
            // If no existing draft found, create new sale
            if (!$sale) {
                $sale = Sale::createWithRetry($saleData);
            }

            // Validate stock availability for non-draft sales
            if (!$isDraft) {
                foreach ($cart->content() as $cart_item) {
                    $product = Product::lockForUpdate()->findOrFail($cart_item->id);
                    $availableStock = ($product->open_quantity ?? 0) + ($product->purchase_quantity ?? 0);
                    
                    if ($availableStock < $cart_item->qty) {
                        throw new \Exception("The Requested Quantity is not available in Stock for product '{$product->product_name}'. Available: {$availableStock}, Requested: {$cart_item->qty}");
                    }
                }
            }

            foreach ($cart->content() as $cart_item) {
                $product      = Product::lockForUpdate()->with('category')->findOrFail($cart_item->id);
                $options      = $cart_item->options;
                $vals         = CartItemCalculator::compute($cart_item, $product);
                $categoryName = $options->category
                    ?? optional($product->category)->category_name
                    ?? optional($product->category)->name;
                $hsn = $options->hsn ?? $product->hsn;

                $pcId = $resolver->resolve($cart_item->id, $options->code ?? null);

                SaleDetails::create([
                    'sale_id'                  => $sale->id,
                    'product_id'               => $cart_item->id,
                    'product_name'             => $cart_item->name,
                    'product_code'             => $options->code,
                    'product_code_id'          => $pcId,
                    'mrp'                      => $vals['mrp'],
                    'rate'                     => $vals['rate'],
                    'tax_percentage'           => $vals['tax_percent'],
                    'tax_amount'               => $vals['tax_amount'],
                    'cash_discount_percentage' => $vals['cash_discount_percent'],
                    'cash_discount_amount'     => $vals['cash_discount_amount'],
                    'discount_amount'          => $vals['discount_amount'],
                    'discount_type'            => $options->product_discount_type,
                    'discount_percent'         => (float) ($options->product_discount_percent ?? 0),
                    'category'                 => $categoryName,
                    'hsn'                      => $hsn,
                    'quantity'                 => $cart_item->qty,
                    'price'                    => (float) $cart_item->price,
                    'unit_price'               => $vals['unit_price'],
                    'sub_total'                => $vals['sub_total'],
                    'product_discount_amount'  => $vals['discount_amount'],
                    'product_discount_type'    => $options->product_discount_type,
                    'product_tax_amount'       => $vals['tax_amount'],
                ]);

                // Only decrement product quantity for non-draft sales
                // Drafts should not reserve stock until they are completed
                if (!$isDraft) {
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

            $cart->destroy();

            // If the sale has any outstanding due, add it to the customer's opening balance
            // Skip for drafts, as they should not affect balances until completed
            if (!$isDraft && ($sale->due_amount ?? 0) > 0) {
                $customer = Customer::lockForUpdate()->find($sale->customer_id);
                if ($customer) {
                    $customer->opening_balance = ($customer->opening_balance ?? 0) + ($sale->due_amount ?? 0);
                    // Set outstanding flag based on new opening balance
                    $customer->outstanding = (($customer->opening_balance ?? 0) > 0) ? 'Yes' : 'No';
                    $customer->save();
                }
            }

            // Determine whether the initial payment should be applied immediately.
            // Settled handling has moved to receipts; here we apply the payment whenever
            // a positive paid amount is submitted.
            $paymentShouldApply = ($request->paid_amount > 0);

            if (!$isDraft && $paymentShouldApply) {
                // Create SalePayment for the applied amount. Do NOT modify customer.opening_balance here.
                // opening_balance represents the customer's outstanding before this sale and is
                // already adjusted by the sale's due_amount (total - paid) above.
                // Ensure `payment_method` is not null (DB column is NOT NULL). If the form
                // did not provide one but a paid amount is present, default to the sale
                // bill type (usually 'Cash') or fall back to 'Cash'. This avoids SQL errors
                // when the user enters an amount but doesn't select a payment method.
                $paymentMethod = $request->payment_method ?? null;
                if (empty($paymentMethod) && ($request->paid_amount ?? 0) > 0) {
                    $paymentMethod = $request->bill_type ?? Sale::BILL_CASH;
                    if (empty($paymentMethod)) {
                        $paymentMethod = Sale::BILL_CASH;
                    }
                }

                $createdPayment = SalePayment::create([
                    'date' => $request->date,
                    'reference' => 'INV/'.$sale->reference,
                    'amount' => $sale->paid_amount,
                    'sale_id' => $sale->id,
                    'payment_method' => $paymentMethod
                ]);

                // If this newly created sale is fully paid, mark for dispatch after commit
                // so the SaleFullyPaid listener can create a receipt. Regardless of paid/partial,
                // always call createReceiptForPayment for the created payment after commit —
                // it is idempotent and will link the payment to any created receipt.
                if (($sale->payment_status ?? '') === 'Paid') {
                    $shouldDispatch = true;
                    $dispatchSaleId = $sale->id;
                }
                DB::afterCommit(function () use ($createdPayment) {
                    try {
                        app(CreateSalesReceiptForPaidSale::class)->createReceiptForPayment($createdPayment);
                    } catch (\Exception $e) {
                        Log::error('CreateSalesReceiptForPaidSale::createReceiptForPayment failed (store)', ['payment_id' => $createdPayment->id, 'error' => $e->getMessage()]);
                    }
                });
            }

            // If payment is not applied (e.g., Cheque and not settled), still create a receipt
            // record with an unsettled line so the receipt exists and shows the payment as pending.
            // Do not apply any payments or change customer.opening_balance here.
            if (!$isDraft && ! $paymentShouldApply && ($request->paid_amount ?? 0) > 0) {
                try {
                    // Idempotent: skip if a receipt line already exists for this sale
                    if (! SalesReceiptLine::where('sale_id', $sale->id)->exists()) {
                        $receipt = SalesReceipt::create([
                            'date' => $sale->date ?? now()->format('Y-m-d'),
                            'customer_id' => $sale->customer_id,
                            'particular' => $sale->customer_name ?? ('Sale '.$sale->reference),
                            'payment_mode' => $sale->payment_method ?? null,
                            'total_amount' => $request->paid_amount ?? 0,
                            'total_discount' => 0,
                        ]);

                        $receipt->reference = 'RE' . str_pad($receipt->id, 5, '0', STR_PAD_LEFT);
                        $receipt->save();

                        SalesReceiptLine::create([
                            'sales_receipt_id' => $receipt->id,
                            'sale_id' => $sale->id,
                            'bill_ref' => $sale->reference,
                            'bill_date' => $sale->date ?? now()->format('Y-m-d'),
                            'bill_amount' => $sale->total_amount ?? 0,
                            // received_before should reflect already-applied amounts (if any)
                            'received_before' => $sale->paid_amount ?? 0,
                            'balance_before' => ($sale->total_amount ?? 0) - ($sale->paid_amount ?? 0),
                            // payment_amount is the requested (but unsettled) amount
                            'payment_amount' => $request->paid_amount ?? 0,
                            'discount_amount' => 0,
                            'final_balance' => $sale->due_amount ?? 0,
                            'is_settled' => false,
                            'settled_at' => null,
                            'settled_by' => null,
                        ]);
                        // mark the sale as locked because a receipt referencing it exists
                        try {
                            $sale->locked = true;
                            $sale->save();
                        } catch (\Exception $e) {
                            // ignore
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to create unsettled receipt for cheque payment on sale create', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
                }
            }
        });

        if ($shouldDispatch && $dispatchSaleId) {
            event(new SaleFullyPaid($dispatchSaleId));
        }

        toast('Sale Created!', 'success');

        return redirect()->route('sales.index');
    } catch (\Exception $e) {
        if ($e->getMessage() === 'Credit Limit reached for this Customer') {
            return redirect()->back()->withErrors(['customer_id' => 'Credit Limit reached for this Customer']);
        }
        if ($e instanceof \DomainException && $e->getMessage() === 'EMPTY_DRAFT') {
            toast('Cannot save empty draft', 'error');
            return redirect()->back();
        }
        throw $e;
    }
    }


    public function show(Sale $sale) {
        abort_if(Gate::denies('show_sales'), 403);

        $customer = $sale->customer_id ? Customer::findOrFail($sale->customer_id) : null;

        return view('sale::show', compact('sale', 'customer'));
    }

    /**
     * Read-only view for a sale (UI-friendly, not print-focused)
     */
    public function view(Sale $sale) {
        // Use a dedicated permission for the read-only view page
        abort_if(Gate::denies('view_sales'), 403);

        $customer = $sale->customer_id ? Customer::findOrFail($sale->customer_id) : null;

        // eager load sale details with product->category and payments to avoid N+1 queries
        $sale->load('saleDetails.product.category', 'salePayments');

        // Use an isolated cart instance for read-only views so the active 'sale' cart
        // (used by the Create Sale / Edit Sale pages) is never overwritten by viewing a bill.
        $this->populateSaleCart($sale, 'sale_view');

        $readonly = true;
        $cartInstance = 'sale_view';
        return view('sale::edit', compact('sale', 'customer', 'readonly', 'cartInstance'));
    }


    public function edit(Sale $sale) {
        abort_if(Gate::denies('edit_sales'), 403);

        // Prevent editing if the sale is locked by a receipt
        if (!empty($sale->locked)) {
            toast('This sale is locked because a receipt exists for it. You cannot edit the bill until the receipt is removed.', 'error');
            return redirect()->route('sales.index');
        }

        // Prevent editing if the customer is locked due to credit limit
        // Skip this check for draft sales without customers
        if ($sale->customer_id) {
            $customer = Customer::findOrFail($sale->customer_id);
            if (!empty($customer->lock) && $customer->lock === 'Yes') {
                toast('This sale cannot be edited because the customer is locked due to credit limit.', 'error');
                return redirect()->route('sales.index');
            }
        }

        // Ensure sale details and nested product category are available to avoid N+1 queries
        $sale->load('saleDetails.product.category');

        // Populate cart from sale details so Livewire/cart components render correctly
        $this->populateSaleCart($sale, 'sale_edit');
        $cartInstance = 'sale_edit';

        return view('sale::edit', compact('sale', 'cartInstance'));
    }


    public function update(UpdateSaleRequest $request, Sale $sale) {
        $shouldDispatch = false;
        $dispatchSaleId = null;
        $isDraft = $request->input('is_draft', 0) == 1;

        // Prevent updating if the sale is locked by a receipt
        if (!empty($sale->locked)) {
            toast('This sale is locked because a receipt exists for it. You cannot update the bill until the receipt is removed.', 'error');
            return redirect()->route('sales.index');
        }

        // Prevent saving/updating a sale with no products in cart
        if (Cart::instance('sale_edit')->content()->isEmpty()) {
            toast('Cannot save sale without products', 'error');
            return redirect()->back();
        }

        // Prefetch product codes for the edit cart to avoid repeated lookups
        $resolver = new ProductCodeResolver();
        $resolver->preload(Cart::instance('sale_edit')->content()->pluck('id')->unique()->toArray());

        DB::transaction(function () use ($request, $sale, &$shouldDispatch, &$dispatchSaleId, $isDraft, $resolver) {
            $cart = Cart::instance('sale_edit');

            // Determine the base total from overall_net_rate or hidden total_amount.
            // Coerce posted strings (may contain commas/currency) to numeric floats.
            $rawOverallNet = $request->overall_net_rate ?? $request->total_amount ?? 0;
            $base_total = floatval(str_replace([',', settings()->currency->symbol], '', (string) $rawOverallNet));

            // Prefer an explicit discount_amount submitted via the form (global discount).
            // If not present, fall back to any cart-level discount value.
            // Do NOT infer a discount by reconciling the posted net-rate with the cart total.
            $submitted_discount = 0;
            if (isset($request->discount_amount) && is_numeric(str_replace(',', '', (string)$request->discount_amount)) && (float)str_replace(',', '', (string)$request->discount_amount) > 0) {
                // Use explicit discount input when provided (numeric)
                $submitted_discount = (float) str_replace(',', '', (string)$request->discount_amount);
            } else {
                // Try cart-level discount first
                $cartDiscount = (float) $cart->discount();
                if ($cartDiscount > 0) {
                    $submitted_discount = $cartDiscount;
                }
            }

            // The `total_amount` represents the canonical bill amount. Do not reduce
            // it by `discount_amount` here — discounts are stored separately so
            // reports and ledgers can display them independently.
            $total_amount = $base_total;

            $prevStatus = $sale->payment_status;

            // Capture previous due, customer, and status so we can adjust customer opening balance
            $oldDue = $sale->due_amount ?? 0;
            $oldCustomerId = $sale->customer_id;
            $oldStatus = $sale->status;

            // Coerce paid_amount to numeric (may be posted as masked/display string)
            $paidNumeric = floatval(str_replace([',', settings()->currency->symbol], '', (string) ($request->paid_amount ?? 0)));

            // Determine whether the submitted payment should be applied now.
            // Settled logic handled in receipts; here we apply the payment whenever a paid amount is present.
            $shouldApplyReq = ($paidNumeric > 0);

            // Effective paid/due on the sale should reflect only applied payments. If the payment is not
            // being applied now, keep the existing sale paid amount.
            $effectivePaid = $shouldApplyReq ? $paidNumeric : ($sale->paid_amount ?? 0);
            // Include submitted global discount when calculating effective due so listing shows correct balance
            $effectiveDue = $total_amount - $effectivePaid - ($submitted_discount ?? 0);

            if ($effectiveDue == $total_amount) {
                $payment_status = 'Unpaid';
            } elseif ($effectiveDue > 0) {
                $payment_status = 'Partial';
            } else {
                $payment_status = 'Paid';
            }

            // For drafts, do not apply any payments
            if ($isDraft) {
                $effectivePaid = 0;
                $effectiveDue = $total_amount;
                $payment_status = 'Unpaid';
            }

            // Restore product quantities for existing sale details before re-creating them.
            // Only restore stock for sales that actually had their stock decremented (non-drafts).
            // Drafts don't decrement stock, so no restoration is needed.
            if ($sale->status !== 'Draft') {
                foreach ($sale->saleDetails as $sale_detail) {
                    $product = Product::lockForUpdate()->findOrFail($sale_detail->product_id);
                    $product->restoreStock($sale_detail->quantity);
                    $sale_detail->delete();
                }
            } else {
                // For drafts, just delete the sale details without restoring stock
                foreach ($sale->saleDetails as $sale_detail) {
                    $sale_detail->delete();
                }
            }

            $reference = $sale->reference;
            $targetFy = Sale::financialYearLabel($request->date);
            $referenceFy = Sale::extractFinancialYearFromReference($reference);
            if (!$referenceFy || $referenceFy !== $targetFy) {
                $nextNumber = Sale::getNextSaleNumber($request->date, $sale->id);
                $reference = Sale::generateSaleReference($nextNumber, $request->date);
            }

            $sale->update([
                'date' => $request->date,
                'due_date' => $request->due_date,
                'reference' => $reference,
                'customer_id' => $request->customer_id,
                'customer_name' => $request->customer_id ? Customer::findOrFail($request->customer_id)->customer_name : 'Draft Customer',
                'area' => $request->area,
                'balance' => ($request->opening_balance ?? 0),
                'bill_type' => $request->bill_type,
                'days' => $request->days,
                'phone_no' => $request->phone,
                'vehicle_name' => $request->vehicle_name ?? null,
                'vehicle_no' => $request->vehicle_no ?? null,
                'discount_type' => $request->discount_type,
                'tax_percentage' => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount' => ($request->shipping_amount ?? 0),
                'paid_amount' => $effectivePaid,
                'total_amount' => $total_amount,
                'due_amount' => $effectiveDue,
                'status' => $isDraft ? 'Draft' : 'Pending',
                'payment_status' => $payment_status,
                'payment_method' => $request->payment_method,
                'note' => $request->note,
                'tax_amount' => $cart->tax(),
                'discount_amount' => ($submitted_discount ?? 0),
                'overall_nos' => $request->overall_nos ?? 0,
                'overall_quantity' => ($request->overall_quantity ?? 0),
                'overall_gross_amount' => ($request->overall_gross_amount ?? 0),
                'overall_taxable_amount' => ($request->overall_taxable_amount ?? 0),
                'overall_cgst' => ($request->overall_cgst ?? 0),
                'overall_sgst' => ($request->overall_sgst ?? 0),
                'overall_igst' => ($request->overall_igst ?? 0),
                'overall_tax_amount' => ($request->overall_tax_amount ?? 0),
                'overall_tcs_percent' => $request->overall_tcs_percent ?? 0,
                'overall_amount' => ($request->overall_amount ?? 0),
                'overall_other' => ($request->overall_other ?? 0),
                'overall_adj' => ($request->overall_adj ?? 0),
                // Persist rounded customer-payable overall net rate
                'overall_net_rate' => round(str_replace(',', '', $request->overall_net_rate ?? $total_amount), 0),
            ]);

            // Reconcile customer opening_balance for change in outstanding due.
            // Skip for drafts without customers
            if (!$isDraft && $request->customer_id) {
                try {
                    $newDue = $effectiveDue;
                    $newCustomerId = $request->customer_id;
                    $wasDraft = $oldStatus === 'Draft';

                    if ($oldCustomerId == $newCustomerId) {
                        if ($wasDraft) {
                            // For draft completion, add full newDue since oldDue was never added
                            $customer = Customer::lockForUpdate()->find($newCustomerId);
                            if ($customer) {
                                $customer->opening_balance = ($customer->opening_balance ?? 0) + $newDue;
                                $customer->outstanding = (($customer->opening_balance ?? 0) > 0) ? 'Yes' : 'No';
                                $customer->save();
                            }
                        } else {
                            // Normal update: add the difference
                            if (abs($newDue - $oldDue) > 0.0001) {
                                $customer = Customer::lockForUpdate()->find($newCustomerId);
                                if ($customer) {
                                    $customer->opening_balance = ($customer->opening_balance ?? 0) + ($newDue - $oldDue);
                                    $customer->outstanding = (($customer->opening_balance ?? 0) > 0) ? 'Yes' : 'No';
                                    $customer->save();
                                }
                            }
                        }
                    } else {
                        // customer changed
                        $customers = Customer::whereIn('id', [$oldCustomerId, $newCustomerId])->lockForUpdate()->get()->keyBy('id');
                        if (!$wasDraft && isset($customers[$oldCustomerId])) {
                            // Only subtract oldDue if the previous sale was not a draft (since drafts don't add to balance)
                            $c = $customers[$oldCustomerId];
                            $c->opening_balance = ($c->opening_balance ?? 0) - ($oldDue ?? 0);
                            $c->outstanding = (($c->opening_balance ?? 0) > 0) ? 'Yes' : 'No';
                            $c->save();
                        }
                        if (isset($customers[$newCustomerId])) {
                            // Always add full newDue to the new customer
                            $c2 = $customers[$newCustomerId];
                            $c2->opening_balance = ($c2->opening_balance ?? 0) + $newDue;
                            $c2->outstanding = (($c2->opening_balance ?? 0) > 0) ? 'Yes' : 'No';
                            $c2->save();
                        }
                    }
                } catch (\Exception $e) {
                    // Do not fail the whole sale update for balance adjust issues; log for investigation.
                    Log::error('Failed to reconcile customer opening balance on sale update', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
                }
            }

            // After reconciling due amounts, ensure SalePayment rows reflect the sale's paid_amount.
            // Settled / cheque handling is now the responsibility of the receipts module.
            // Compute desired applied amount in rupees (based on the sale record we just updated)
            $desiredApplied = ($sale->paid_amount ?? 0);

            // Sum existing sale payments (DB stores minor units)
            $existingTotalPaise = \Modules\Sale\Entities\SalePayment::where('sale_id', $sale->id)->sum('amount');
            $existingTotal = ($existingTotalPaise ?? 0) / 100;

            // If totals differ, reconcile: remove existing payments and recreate a single payment matching desiredApplied
            if (!$isDraft && abs($existingTotal - $desiredApplied) > 0.0001) {
                // Remove existing sale payments for this sale and recreate a single payment matching desiredApplied.
                // Do NOT adjust customer.opening_balance here – opening_balance is reconciled above based on due amounts
                // (newDue - oldDue) which reflects the customer's net outstanding after the update.
                \Modules\Sale\Entities\SalePayment::where('sale_id', $sale->id)->delete();

                if ($desiredApplied > 0) {
                    // Avoid inserting NULL into non-nullable `payment_method` column.
                    $paymentMethod = $request->payment_method ?? null;
                    if (empty($paymentMethod) && ($desiredApplied ?? 0) > 0) {
                        $paymentMethod = $request->bill_type ?? Sale::BILL_CASH;
                        if (empty($paymentMethod)) {
                            $paymentMethod = Sale::BILL_CASH;
                        }
                    }

                    $createdPayment = \Modules\Sale\Entities\SalePayment::create([
                        'date' => $request->date,
                        'reference' => 'INV/'.$sale->reference,
                        'amount' => $desiredApplied,
                        'sale_id' => $sale->id,
                        'payment_method' => $paymentMethod
                    ]);

                    // Always schedule createReceiptForPayment after commit; it is idempotent.
                    DB::afterCommit(function () use ($createdPayment) {
                        try {
                            app(CreateSalesReceiptForPaidSale::class)->createReceiptForPayment($createdPayment);
                        } catch (\Exception $e) {
                            Log::error('CreateSalesReceiptForPaidSale::createReceiptForPayment failed (update)', ['payment_id' => $createdPayment->id, 'error' => $e->getMessage()]);
                        }
                    });
                }

                // Unsettled receipt creation is handled by the receipts module; no action here.
            }

            // If payment status transitioned to Paid and the payment is applied, mark to dispatch after commit.
            if (!$isDraft && $payment_status === 'Paid' && $prevStatus !== 'Paid' && ($desiredApplied > 0)) {
                $shouldDispatch = true;
                $dispatchSaleId = $sale->id;
            }

            // Validate stock availability for non-draft sales
            if (!$isDraft) {
                foreach ($cart->content() as $cart_item) {
                    $product = Product::lockForUpdate()->findOrFail($cart_item->id);
                    $availableStock = ($product->open_quantity ?? 0) + ($product->purchase_quantity ?? 0);
                    
                    if ($availableStock < $cart_item->qty) {
                        throw new \Exception("The Requested Quantity is not available in Stock for product '{$product->product_name}'. Available: {$availableStock}, Requested: {$cart_item->qty}");
                    }
                }
            }

            foreach ($cart->content() as $cart_item) {
                $product      = Product::with('category')->findOrFail($cart_item->id);
                $options      = $cart_item->options;
                $vals         = CartItemCalculator::compute($cart_item, $product);
                $categoryName = $options->category
                    ?? optional($product->category)->category_name
                    ?? optional($product->category)->name;
                $hsn = $options->hsn ?? $product->hsn;

                $pcId = $resolver->resolve($cart_item->id, $options->code ?? null);

                SaleDetails::create([
                    'sale_id'                  => $sale->id,
                    'product_id'               => $cart_item->id,
                    'product_name'             => $cart_item->name,
                    'product_code'             => $options->code,
                    'product_code_id'          => $pcId,
                    'mrp'                      => $vals['mrp'],
                    'rate'                     => $vals['rate'],
                    'tax_percentage'           => $vals['tax_percent'],
                    'tax_amount'               => $vals['tax_amount'],
                    'cash_discount_percentage' => $vals['cash_discount_percent'],
                    'cash_discount_amount'     => $vals['cash_discount_amount'],
                    'discount_amount'          => $vals['discount_amount'],
                    'discount_type'            => $options->product_discount_type,
                    'discount_percent'         => (float) ($options->product_discount_percent ?? 0),
                    'category'                 => $categoryName,
                    'hsn'                      => $hsn,
                    'quantity'                 => $cart_item->qty,
                    'price'                    => (float) $cart_item->price,
                    'unit_price'               => $vals['unit_price'],
                    'sub_total'                => $vals['sub_total'],
                    'product_discount_amount'  => $vals['discount_amount'],
                    'product_discount_type'    => $options->product_discount_type,
                    'product_tax_amount'       => $vals['tax_amount'],
                ]);

                // Only decrement product quantity for non-draft sales
                if (!$isDraft) {
                    $product->reserveStock($cart_item->qty);
                }
            }

            $cart->destroy();
        });

        if ($shouldDispatch && $dispatchSaleId) {
            event(new SaleFullyPaid($dispatchSaleId));
        }

        toast('Sale Updated!', 'info');

        return redirect()->route('sales.index');
    }

    /**
     * Populate the shopping cart instance from a Sale's saleDetails.
     *
     * @param  Sale    $sale
     * @param  string  $instance  Cart instance name. Use 'sale_view' for read-only views
     *                            so they do not contaminate the active 'sale' create/edit cart.
     */
    private function populateSaleCart(Sale $sale, string $instance = 'sale')
    {
        $sale_details = $sale->saleDetails;

        Cart::instance($instance)->destroy();
        $cart = Cart::instance($instance);

        foreach ($sale_details as $sale_detail) {
            // Use the already-loaded product relationship to avoid extra queries
            $product = $sale_detail->product ?? null;

            $taxPercent = $sale_detail->tax_percentage ?? ($product->product_order_tax ?? 0);
            $mrp = $sale_detail->mrp ?? ($product->mrp ?? 0);
            $rate = $sale_detail->rate ?? ($taxPercent ? $mrp / (1 + ($taxPercent / 100)) : $mrp);

            $price = $sale_detail->price; // rupees (accessor)
            $product_discount_amount = $sale_detail->product_discount_amount;
            $product_discount_type = $sale_detail->product_discount_type;

            // Use stored discount_percent directly from database; fall back to calculation for old records
            $stored_percent = $sale_detail->discount_percent ?? null;
            if ($product_discount_type === 'percentage' && $stored_percent !== null && $stored_percent > 0) {
                $discount_percent = (float) $stored_percent;
            } elseif ($product_discount_type === 'percentage' && !empty($rate) && $rate > 0) {
                // Backward compatibility: calculate from amount for old records
                $discount_percent = round(($product_discount_amount / $rate) * 100, 4);
            } else {
                $discount_percent = 0;
            }

            $cart->add([
                'id'      => $sale_detail->product_id,
                'name'    => $sale_detail->product_name,
                'qty'     => $sale_detail->quantity,
                'price'   => $sale_detail->price,
                'weight'  => 1,
                'options' => [
                    'product_discount'       => $product_discount_amount,
                    'product_discount_type'  => $product_discount_type,
                    'product_discount_percent' => $discount_percent,
                    'sub_total'              => $sale_detail->sub_total,
                    'code'                   => ($sale_detail->productCode->code ?? $sale_detail->product_code),
                    'stock'                  => $product->product_quantity ?? null,
                    'unit'                   => $product->product_unit ?? 'Nos',
                    'product_tax'            => $sale_detail->product_tax_amount,
                    'unit_price'             => $sale_detail->unit_price,
                    'category'               => $sale_detail->category ?: optional($product->category)->category_name,
                    'hsn'                    => $sale_detail->hsn ?: ($product->hsn ?? null),
                    'tax_percent'            => $taxPercent,
                    'mrp'                    => $mrp,
                    'rate'                   => round($rate, 2),
                    'cash_discount_percent'  => $sale_detail->cash_discount_percentage ?? 0,
                    'cash_discount_amount'   => $sale_detail->cash_discount_amount ?? 0,
                ]
            ]);
        }
    }


    public function destroy(Sale $sale) {
        abort_if(Gate::denies('delete_sales'), 403);
        // Prevent deletion when a receipt exists for this sale. This mirrors the
        // edit() protection which prevents editing locked sales.
        if (!empty($sale->locked)) {
            toast('This sale is locked because a receipt exists for it. You cannot delete the bill until the receipt is removed.', 'error');
            return redirect()->route('sales.index');
        }
        // Restore product quantities for the sale being deleted, then delete the sale.
        // Only restore stock for sales that actually had their stock decremented (non-drafts).
        // Drafts don't decrement stock, so no restoration is needed.
        DB::transaction(function () use ($sale) {
            try {
                if ($sale->status !== 'Draft') {
                    foreach ($sale->saleDetails as $sale_detail) {
                        try {
                            $product = Product::lockForUpdate()->find($sale_detail->product_id);
                            if ($product) {
                                $product->addPurchaseStock($sale_detail->quantity);
                            }
                        } catch (\Exception $e) {
                            // Log and continue restoring other products; do not abort the whole transaction
                            Log::error('Failed to restore product quantity while deleting sale', ['sale_id' => $sale->id, 'product_id' => $sale_detail->product_id, 'error' => $e->getMessage()]);
                        }
                    }
                }

                // Reverse customer balance adjustment for completed sales
                // Only completed sales (non-drafts) had their due amounts added to customer balance
                if ($sale->status !== 'Draft' && $sale->customer_id && ($sale->due_amount ?? 0) > 0) {
                    try {
                        $customer = Customer::lockForUpdate()->find($sale->customer_id);
                        if ($customer) {
                            $customer->opening_balance = ($customer->opening_balance ?? 0) - ($sale->due_amount ?? 0);
                            $customer->outstanding = (($customer->opening_balance ?? 0) > 0) ? 'Yes' : 'No';
                            $customer->save();
                        }
                    } catch (\Exception $e) {
                        // Log but don't fail the deletion for balance adjust issues
                        Log::error('Failed to reverse customer opening balance while deleting sale', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
                    }
                }

                // Now delete the sale (will remove sale details if cascade configured)
                $sale->delete();
            } catch (\Exception $e) {
                Log::error('Failed to delete sale with stock restore', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
                throw $e;
            }
        });

        toast('Sale Deleted!', 'warning');

        return redirect()->route('sales.index');
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
        $dueAmount = $totalAmount - $paidAmount;

        // If sale_id is provided (editing an existing sale), subtract the existing sale due
        // from customer's opening_balance to avoid double-counting the same sale.
        $saleId = $request->input('sale_id');
        $openingBalance = ($customer->opening_balance ?? 0);
        if ($saleId) {
            try {
                $existingSale = Sale::find($saleId);
                if ($existingSale && $existingSale->customer_id == $customerId && ($existingSale->status ?? '') !== 'Draft') {
                    $openingBalance = $openingBalance - ($existingSale->due_amount ?? 0);
                }
            } catch (\Exception $e) {
                // ignore and use original opening balance
            }
        }

        $potentialBalance = $openingBalance + $dueAmount;

        $grace = 1000;
        $limit = floatval($customer->credit_limit ?? 0);

        // credit_limit_reached: potentialBalance >= limit (warning)
        $reached = ($limit > 0) && ($potentialBalance >= $limit);
        // credit_limit_blocked: potentialBalance > limit + grace (block and lock)
        $blocked = ($limit > 0) && ($potentialBalance > ($limit + $grace));

        return response()->json([
            'credit_limit_reached' => $reached,
            'credit_limit_blocked' => $blocked,
            'potential_balance' => $potentialBalance,
            'credit_limit' => $limit,
            'grace' => $grace
        ]);
    }
}
