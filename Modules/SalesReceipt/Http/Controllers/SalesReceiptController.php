<?php

namespace Modules\SalesReceipt\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\SalesReceipt\Http\Requests\StoreSalesReceiptRequest;
use Modules\SalesReceipt\Http\Requests\UpdateSalesReceiptRequest;
use Modules\SalesReceipt\Entities\SalesReceipt;
use Modules\SalesReceipt\Entities\SalesReceiptLine;
use Modules\Sale\Entities\Sale;
use Modules\Sale\Entities\SalePayment;
use Modules\People\Entities\Customer;
use Modules\SalesReceipt\DataTables\SalesReceiptsDataTable;
use Illuminate\Support\Facades\Log;

class SalesReceiptController extends Controller
{
    /** Tolerance for comparing floats when checking settlement status */
    protected const SETTLEMENT_TOLERANCE = 0.01;

    /** Prefix for temporary payment references before receipt ID is finalized */
    protected const TEMP_PAYMENT_PREFIX = 'REC/';

    /** Prefix for final receipt reference numbers */
    protected const REFERENCE_PREFIX = 'RE';

    public function index(SalesReceiptsDataTable $dataTable) {
        return $dataTable->render('salesreceipt::index');
    }

    /**
     * Aggregated totals for listing (supports optional filters: start_date, end_date, customer_id, payment_mode)
     */
    public function totals() {
        $start = request()->get('start_date');
        $end = request()->get('end_date');
        $customer = request()->get('customer_id');
        $paymentMode = request()->get('payment_mode');
        // Build query with optional filters
        $base = SalesReceipt::query();

        if ($start && $end) {
            try {
                $s = \Carbon\Carbon::parse($start)->startOfDay();
                $e = \Carbon\Carbon::parse($end)->endOfDay();
                $base->whereBetween('date', [$s, $e]);
            } catch (\Exception $ex) {
                // fallback to raw values if parse fails
                $base->whereBetween('date', [$start, $end]);
            }
        } elseif ($start) {
            try {
                $s = \Carbon\Carbon::parse($start)->startOfDay();
                $base->where('date', '>=', $s);
            } catch (\Exception $ex) {
                $base->whereDate('date', '>=', $start);
            }
        } elseif ($end) {
            try {
                $e = \Carbon\Carbon::parse($end)->endOfDay();
                $base->where('date', '<=', $e);
            } catch (\Exception $ex) {
                $base->whereDate('date', '<=', $end);
            }
        }

        if ($customer) {
            $base->where('customer_id', $customer);
        }

        if ($paymentMode) {
            $base->where('payment_mode', $paymentMode);
        }

        // Apply global search (from DataTable) so totals mirror the visible rows.
        $search = request()->get('search');
        $term = null;
        if (is_array($search) && isset($search['value'])) {
            $term = trim($search['value']);
        } elseif (is_string($search)) {
            $term = trim($search);
        }

        if (!empty($term)) {
            $base->applyGlobalSearch($term);
        }

        // Use model accessors by retrieving the collection so totals are in rupees regardless of raw DB storage units
        $collection = $base->get();

        // Compute requested aggregations:
        // - count: number of receipts matching filters
        // - per-payment-mode sums
        // - discount_total and final received (total - discount)
        $count = $collection->count();
        $cashTotal = 0.0;
        $chequeTotal = 0.0;
        $upiTotal = 0.0;
        $cardTotal = 0.0;
        $bankTotal = 0.0;
        $productReturnTotal = 0.0;
        $discountTotal = $collection->sum('total_discount');
        $allTotal = $collection->sum('total_amount');
        // total_amount is the actual amount received (cash/bank), so we do not subtract discount
        $finalTotal = $allTotal;

        foreach ($collection as $r) {
            $pm = strtolower(trim((string)($r->payment_mode ?? '')));
            $amt = floatval($r->total_amount ?? 0);
            if ($pm === '') {
                $bankTotal += $amt; continue;
            }
            if (strpos($pm, 'cash') !== false) { $cashTotal += $amt; continue; }
            if (strpos($pm, 'cheque') !== false || strpos($pm, 'check') !== false) { $chequeTotal += $amt; continue; }
            if (strpos($pm, 'upi') !== false) { $upiTotal += $amt; continue; }
            if (strpos($pm, 'card') !== false || strpos($pm, 'cards') !== false) { $cardTotal += $amt; continue; }
            if (strpos($pm, 'product return') !== false) { $productReturnTotal += $amt; continue; }
            if (strpos($pm, 'bank') !== false || strpos($pm, 'bank transfer') !== false) { $bankTotal += $amt; continue; }
            $bankTotal += $amt;
        }

        $totals = [
            // component-friendly keys
            'overall_count' => $count,
            'overall_total_amount' => round(floatval($allTotal), 2),
            'overall_received_amount' => round(floatval($finalTotal), 2),
            // payment mode breakdown
            'overall_cash_amount' => round(floatval($cashTotal), 2),
            'overall_cheque_amount' => round(floatval($chequeTotal), 2),
            'overall_upi_amount' => round(floatval($upiTotal), 2),
            'overall_card_amount' => round(floatval($cardTotal), 2),
            'overall_bank_amount' => round(floatval($bankTotal), 2),
            'overall_product_return_amount' => round(floatval($productReturnTotal), 2),
            // legacy keys
            'count' => $count,
            'cash' => round(floatval($cashTotal), 2),
            'cheque' => round(floatval($chequeTotal), 2),
            'discount' => round(floatval($discountTotal), 2),
            'total' => round(floatval($finalTotal), 2),
        ];

        return response()->json($totals);
    }

    public function create() {
        abort_if(Gate::denies('create_sales_receipts'), 403);

        return view('salesreceipt::create');
    }

    /**
     * Return customers for the filter Select2.
     * - If user typed q, search customers globally (name/code)
     * - Otherwise return only customers who have receipts matching the optional date/payment filters
     */
    public function customers(Request $request)
    {
        $start = $request->get('start_date');
        $end = $request->get('end_date');

        // Return only customers who have receipts matching the optional date filters.
        $customerIds = \Modules\SalesReceipt\Entities\SalesReceipt::query()
            ->when($start && $end, function($qry) use ($start, $end) { $qry->whereBetween('date', [$start, $end]); })
            ->when($start && !$end, function($qry) use ($start) { $qry->whereDate('date', '>=', $start); })
            ->when($end && !$start, function($qry) use ($end) { $qry->whereDate('date', '<=', $end); })
            ->distinct()
            ->pluck('customer_id')
            ->toArray();

        $customers = \Modules\People\Entities\Customer::whereIn('id', $customerIds)
            ->orderBy('customer_name', 'asc')
            ->get(['id', 'customer_name', 'customer_code']);

        $results = $customers->map(function($c) {
            return [
                'id' => $c->id,
                'text' => trim($c->customer_name . ($c->customer_code ? ' (' . $c->customer_code . ')' : '')),
            ];
        })->values();

        return response()->json(['results' => $results]);
    }

    public function store(StoreSalesReceiptRequest $request) {
        abort_if(Gate::denies('create_sales_receipts'), 403);
        $data = $request->validated();

        // Additional per-line business validation: ensure payment + discount <= sale due
        $errors = [];
        $submittedLines = is_array($data['lines'] ?? null) ? $data['lines'] : [];

        // Detect synthetic opening-row: single submitted line with empty sale_id while apply_to_opening checked.
        // Capture the payment+discount the user entered (so we can apply partial amounts to opening),
        // but skip sale-level validation for this synthetic row.
        $linelessApplied = 0.0;
        $isSyntheticOpening = false;
        if (!empty($data['apply_to_opening']) && count($submittedLines) === 1 && empty($submittedLines[0]['sale_id'])) {
            $isSyntheticOpening = true;
            $linelessApplied = floatval($submittedLines[0]['payment_amount'] ?? 0) + floatval($submittedLines[0]['discount_amount'] ?? 0);
        }

        // If synthetic opening requested, ensure there are no outstanding bills for this customer
        if ($isSyntheticOpening) {
            $customerId = $data['customer_id'] ?? null;
            $submittedSaleIds = [];
            if (is_array($submittedLines)) {
                foreach ($submittedLines as $l) { if (!empty($l['sale_id'])) $submittedSaleIds[] = intval($l['sale_id']); }
            }

            $hasOutstanding = Sale::where('customer_id', $customerId)
                ->where('due_amount', '>', 0)
                ->where(function($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'Draft');
                })
                ->when(!empty($submittedSaleIds), function($q) use ($submittedSaleIds) {
                    $q->whereNotIn('id', $submittedSaleIds);
                })
                ->exists();

            if ($hasOutstanding) {
                $err = ['apply_to_opening' => 'Cannot apply directly to Opening Balance while there are outstanding bills for this customer. Please settle bills first.'];
                if ($request->wantsJson() || $request->expectsJson()) {
                    return response()->json(['errors' => array_map(function($v){ return [$v]; }, $err)], 422);
                }
                return redirect()->back()->withInput()->withErrors($err);
            }
        }

        $linesForValidation = $isSyntheticOpening ? [] : $submittedLines;
        foreach ($linesForValidation as $idx => $line) {
            $saleId = $line['sale_id'] ?? null;
            if (empty($saleId)) {
                $errors["lines.{$idx}.sale_id"] = "Selected sale not found.";
                continue;
            }
            $sale = Sale::find($saleId);
            if (!$sale) {
                $errors["lines.{$idx}.sale_id"] = "Selected sale not found.";
                continue;
            }

            $paymentAmount = floatval($line['payment_amount'] ?? 0);
            $discountAmount = floatval($line['discount_amount'] ?? 0);
            $due = $sale->due_amount; // model accessor gives rupees

            if (($paymentAmount + $discountAmount) > ($due + 0.0001)) {
                $errors["lines.{$idx}.payment_amount"] = "Payment + discount (".($paymentAmount + $discountAmount).") exceeds outstanding due ({$due}) for sale {$sale->reference}.";
            }
        }

        if (!empty($errors)) {
            if ($request->wantsJson() || $request->expectsJson()) {
                // Ensure each error value is an array to be compatible with client-side expectations
                $jsonErrors = array_map(function($v){ return is_array($v) ? $v : [$v]; }, $errors);
                return response()->json(['errors' => $jsonErrors], 422);
            }
            return redirect()->back()->withInput()->withErrors($errors);
        }

    DB::transaction(function() use ($request, $data, $linelessApplied, $submittedLines, $isSyntheticOpening) {
            // lock customer row to avoid concurrent balance updates
            $customer = Customer::lockForUpdate()->findOrFail($data['customer_id']);

            // create receipt including a snapshot of the customer's opening balance
            $receipt = SalesReceipt::create([
                'date' => $data['date'],
                'customer_id' => $data['customer_id'],
                'particular' => $data['particular'],
                'payment_mode' => $data['payment_mode'] ?? null,
                'total_amount' => 0,
                'total_discount' => 0,
                // use submitted opening_balance when provided, otherwise current customer balance
                'customer_balance_before' => (int) round(floatval($data['opening_balance'] ?? $customer->opening_balance ?? 0) * 100),
                'created_by' => auth()->id()
            ]);

            $total = 0; // sum of all line payments (regardless of applied)
            $totalDiscount = 0;
            $appliedTotal = 0; // sum of payments actually applied to sales (cash or settled cheque)
            $appliedDiscount = 0;

            // Calculate total receipt amount and allocated amount for settlement logic
            $receiptAmount = floatval($data['amount'] ?? 0);
            $totalAllocated = 0;
            $lines = is_array($submittedLines) ? $submittedLines : [];
            // If synthetic opening-row was submitted, do not process it as a sale line
            $linesToProcess = $isSyntheticOpening ? [] : $lines;
            if ($isSyntheticOpening) {
                $totalAllocated = 0;
            } else {
                // settlement is based on payment amounts only (discounts do not reduce receipt allocation)
                foreach ($linesToProcess as $line) {
                    $totalAllocated += floatval($line['payment_amount'] ?? 0);
                }
            }

            // Determine if receipt is settled: receipt amount equals total allocated amount
            $isReceiptSettled = abs($receiptAmount - $totalAllocated) < self::SETTLEMENT_TOLERANCE;

            foreach ($linesToProcess as $line) {
                $result = $this->processSaleLine($line, $receipt, $isReceiptSettled, $data);
                $appliedTotal += $result['appliedTotal'];
                $appliedDiscount += $result['appliedDiscount'];
                $total += $result['total'];
                $totalDiscount += $result['totalDiscount'];
            }

            // update receipt totals, reference and ensure balance_before in a single query
            $attrs = [
                'total_amount' => $receiptAmount,
                'total_discount' => $totalDiscount,
                'reference' => self::REFERENCE_PREFIX . str_pad($receipt->id, 5, '0', STR_PAD_LEFT),
            ];
            if (is_null($receipt->customer_balance_before)) {
                $attrs['customer_balance_before'] = (int) round(floatval($customer->opening_balance ?? 0) * 100);
            }
            $receipt->update($attrs);

            // update sale_payments created with temporary REC/{id} reference to final receipt reference
            \Modules\Sale\Entities\SalePayment::where('reference', self::TEMP_PAYMENT_PREFIX.$receipt->id)
                ->update(['reference' => $receipt->reference]);
            // If user submitted a synthetic opening-row, apply only the user-entered amount (possibly partial)
            if ($isSyntheticOpening && !empty($data['apply_to_opening'])) {
                // For opening balance, only actual payment should count toward "applied to customer"
                // Discounts do not reduce receipt allocation; use payment amount only when present.
                $linelessPayment = $isSyntheticOpening && isset($submittedLines[0]['payment_amount']) ? floatval($submittedLines[0]['payment_amount']) : 0.0;
                $linelessDiscount = $isSyntheticOpening && isset($submittedLines[0]['discount_amount']) ? floatval($submittedLines[0]['discount_amount']) : 0.0;
                $appliedAmount = $linelessPayment > 0 ? $linelessPayment : $receiptAmount;
                // update applied and balance-before together
                $receipt->update([
                    'customer_balance_before' => (int) round(floatval($customer->opening_balance ?? 0) * 100),
                    // store applied amount as paise (payments-only)
                    'applied_to_customer' => (int) round($appliedAmount * 100),
                ]);

                // Persist a synthetic opening-line so opening applications are auditable
                try {
                    // $linelessPayment/$linelessDiscount already computed above; recompute balanceBefore
                    $balanceBefore = is_null($receipt->customer_balance_before) ? ($customer->opening_balance ?? 0) : ($receipt->customer_balance_before / 100);
                    // Decide settlement based on payments-only and compare in paise (integers)
                    $appliedPaise = (int) round($appliedAmount * 100);
                    $receiptPaise = (int) round($receiptAmount * 100);
                    $isSettledLineless = abs($appliedPaise - $receiptPaise) <= 1;
                    SalesReceiptLine::create([
                        'sales_receipt_id' => $receipt->id,
                        'sale_id' => null,
                        'bill_ref' => 'Opening Balance',
                        'bill_date' => null,
                        // Lineless opening rows do not represent a sale bill; store bill_amount as zero
                        'bill_amount' => 0,
                        'received_before' => 0,
                        'balance_before' => $balanceBefore,
                        'payment_amount' => $linelessPayment,
                        'discount_amount' => $linelessDiscount,
                        'final_balance' => max(0, $balanceBefore - ($linelessPayment + $linelessDiscount)),
                        'is_settled' => $isSettledLineless,
                        'settled_at' => $isSettledLineless ? now() : null,
                        'settled_by' => $isSettledLineless ? auth()->id() : null,
                    ]);
                } catch (\Exception $e) {
                    // Log and continue: migration may not yet be applied in some environments
                    Log::warning('Failed to persist synthetic opening line', ['receipt_id' => $receipt->id, 'error' => $e->getMessage()]);
                }

                // Apply payment + discount to the customer's balances so discounts reduce opening balance
                $linelessApplyForBalance = $linelessPayment + $linelessDiscount;
                if ($linelessApplyForBalance > 0) {
                    $this->applyReceiptToCustomer($customer, $linelessApplyForBalance);
                } elseif ($appliedAmount > 0) {
                    // fallback: if no explicit payment provided but full receipt should be applied
                    $this->applyReceiptToCustomer($customer, $appliedAmount);
                }

                // Add remaining excess (receiptAmount - appliedAmount) to excess_amount
                $excessAmount = $receiptAmount - $appliedAmount;
                if ($excessAmount > 0) {
                    $customer->excess_amount = floatval($customer->excess_amount ?? 0) + $excessAmount;
                    $customer->save();
                }
            } else {
                // update customer balance only for applied amounts (cash or settled cheque lines)
                $netApplied = floatval($appliedTotal + $appliedDiscount);
                if ($netApplied > 0) {
                    $this->applyReceiptToCustomer($customer, $netApplied);
                }

                // Add excess receipt amount (Receipt Amount - Applied Amount) to excess_amount
                $excessAmount = $receiptAmount - $netApplied;
                if ($excessAmount > 0) {
                    $customer->excess_amount = floatval($customer->excess_amount ?? 0) + $excessAmount;
                    $customer->save();
                }
            }
        });

        toast('Sales Receipt created', 'success');

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['success' => true, 'redirect' => route('sales-receipts.index')]);
        }

        return redirect()->route('sales-receipts.index');
    }

    public function show($id) {
        abort_if(Gate::denies('show_sales_receipts'), 403);

        // Eager-load saleDetails to avoid lazy-loading errors when rendering invoice partial
        $receipt = SalesReceipt::with('lines.sale.saleDetails')->findOrFail($id);

        // Gather sale ids referenced by this receipt and preload sale details
        $saleIds = $receipt->lines->pluck('sale_id')->filter()->unique()->values()->all();
        $sales = collect();
        $customers = collect();
        if (!empty($saleIds)) {
            $salesCollection = Sale::with('saleDetails')->whereIn('id', $saleIds)->get()->keyBy('id');
            $sales = $salesCollection;

            // Load customers for these sales (Sale stores customer_id)
            $customerIds = $salesCollection->pluck('customer_id')->filter()->unique()->values()->all();
            if (!empty($customerIds)) {
                $customersCollection = Customer::whereIn('id', $customerIds)->get()->keyBy('id');
                $customers = $customersCollection;
            }
        }

        // Pass receipt, and maps of sales/customers so the view can include the sale invoice partial
        return view('salesreceipt::show', compact('receipt', 'sales', 'customers'));
    }

    public function edit($id) {
        abort_if(Gate::denies('edit_sales_receipts'), 403);

        $receipt = SalesReceipt::with('lines')->findOrFail($id);
        return view('salesreceipt::edit', compact('receipt'));
    }

    /**
     * Read-only view for a receipt — reuse edit form in readonly mode for consistent UI
     */
    public function view($id) {
        abort_if(Gate::denies('view_sales_receipts'), 403);

        $receipt = SalesReceipt::with('lines.sale.saleDetails', 'customer')->findOrFail($id);

        // If this receipt was applied to opening balance (lineless) and no persisted lineless line exists,
        // synthesize a display line for the view (backwards compatibility for older receipts)
        $hasPersistedLineless = $receipt->lines->contains(function($l){ return empty($l->sale_id); });
        if ($receipt->applied_to_customer > 0 && ! $hasPersistedLineless) {
            $applied = $receipt->applied_to_customer / 100;
            $syntheticLine = new \stdClass();
            $syntheticLine->bill_ref = 'Opening Balance';
            $syntheticLine->bill_date = '-';
            // Lineless display rows do not represent a sale bill; show bill_amount as zero
            $syntheticLine->bill_amount = 0;
            $syntheticLine->received_before = 0;
            // prefer the balance that was stored when the receipt was created; fall back to
            // the customer's current opening balance only if the stored value is missing
            if (!is_null($receipt->customer_balance_before)) {
                $syntheticLine->balance_before = $receipt->customer_balance_before / 100;
            } else {
                $syntheticLine->balance_before = $receipt->customer->opening_balance ?? 0;
            }
            $syntheticLine->payment_amount = $applied;
            $syntheticLine->discount_amount = 0;
            // final balance for opening-row should be balance_before minus applied amounts
            $syntheticLine->final_balance = max(0, $syntheticLine->balance_before - $syntheticLine->payment_amount - $syntheticLine->discount_amount);
            $syntheticLine->sale_id = null;
            $receipt->lines->push($syntheticLine);
        }

        // Gather sales and customers referenced so the edit view (readonly) can render consistent data
        $saleIds = $receipt->lines->pluck('sale_id')->filter()->unique()->values()->all();
        $sales = collect();
        $customers = collect();
        if (!empty($saleIds)) {
            $salesCollection = Sale::with('saleDetails')->whereIn('id', $saleIds)->get()->keyBy('id');
            $sales = $salesCollection;
            $customerIds = $salesCollection->pluck('customer_id')->filter()->unique()->values()->all();
            if (!empty($customerIds)) {
                $customers = Customer::whereIn('id', $customerIds)->get()->keyBy('id');
            }
        }

        $readonly = true;
        return view('salesreceipt::edit', compact('receipt', 'sales', 'customers', 'readonly'));
    }

    public function update(UpdateSalesReceiptRequest $request, $id) {
        abort_if(Gate::denies('edit_sales_receipts'), 403);

        $data = $request->validated();

        // detect synthetic opening-row submitted during update so we can preserve its payment amount
        $submittedLines = is_array($data['lines'] ?? null) ? $data['lines'] : [];
        $isSyntheticOpening = !empty($data['apply_to_opening']) && count($submittedLines) === 1 && empty($submittedLines[0]['sale_id']);
        $linelessApplied = $isSyntheticOpening ? (floatval($submittedLines[0]['payment_amount'] ?? 0) + floatval($submittedLines[0]['discount_amount'] ?? 0)) : 0.0;

        // If synthetic opening requested during update, ensure there are no outstanding bills
        if ($isSyntheticOpening) {
            $customerId = $data['customer_id'] ?? null;
            $submittedSaleIds = [];
            if (is_array($submittedLines)) {
                foreach ($submittedLines as $l) { if (!empty($l['sale_id'])) $submittedSaleIds[] = intval($l['sale_id']); }
            }

            $hasOutstanding = Sale::where('customer_id', $customerId)
                ->where('due_amount', '>', 0)
                ->where(function($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'Draft');
                })
                ->when(!empty($submittedSaleIds), function($q) use ($submittedSaleIds) {
                    $q->whereNotIn('id', $submittedSaleIds);
                })
                ->exists();

            if ($hasOutstanding) {
                $err = ['apply_to_opening' => 'Cannot apply directly to Opening Balance while there are outstanding bills for this customer. Please settle bills first.'];
                if ($request->wantsJson() || $request->expectsJson()) {
                    return response()->json(['errors' => array_map(function($v){ return [$v]; }, $err)], 422);
                }
                return redirect()->back()->withInput()->withErrors($err);
            }
        }

        DB::transaction(function() use ($data, $id, $submittedLines, $isSyntheticOpening, $linelessApplied) {
            $receipt = SalesReceipt::with('lines')->findOrFail($id);
            // lock the customer early and keep for reuse
            $customer = Customer::lockForUpdate()->find($receipt->customer_id);

            // If this existing receipt previously applied directly to opening (lineless), revert that first
            // If this receipt previously applied directly to opening (lineless), revert that first.
            // Prefer the persisted lineless line's payment+discount (if present) otherwise fall back to
            // the stored `applied_to_customer` (payments-only) for backwards compatibility.
            $oldLinelessAmount = 0.0;
            $linelessLine = $receipt->lines->first(function($l){ return empty($l->sale_id); });
            if ($linelessLine) {
                $oldLinelessAmount = floatval(($linelessLine->payment_amount ?? 0) + ($linelessLine->discount_amount ?? 0));
            } else {
                $oldLinelessPaise = intval($receipt->applied_to_customer ?? 0);
                $oldLinelessAmount = floatval($oldLinelessPaise) / 100;
            }
            if ($oldLinelessAmount > 0 && $customer) {
                $this->revertReceiptOnCustomer($customer, $oldLinelessAmount);
                // clear stored applied fields so later logic isn't confused
                $receipt->customer_balance_before = null;
                $receipt->applied_to_customer = null;
                $receipt->save();
            }
            // capture sale ids referenced by the existing receipt so we can unlock those
            // that are removed during the update (if no other receipt references them)
            $oldSaleIds = $receipt->lines->pluck('sale_id')->toArray();

            // capture old totals for customer adjustment (only amounts that were applied)
            $oldTotal = $receipt->total_amount ?? 0;
            $oldDiscount = $receipt->total_discount ?? 0;

            $oldAppliedTotal = 0;
            $oldAppliedDiscount = 0;

            // Reverse previous effects: for each existing line, remove payments and adjust sale paid/due
            // Note: store() always created SalePayment and updated sale amounts when payment/discount > 0,
            // so on update we must reverse those changes regardless of the per-line is_settled flag.
            foreach ($receipt->lines as $oldLine) {
                $sale = Sale::lockForUpdate()->find($oldLine->sale_id);

                $oldPayment = floatval($oldLine->payment_amount ?? 0);
                $oldDiscountLine = floatval($oldLine->discount_amount ?? 0);

                $hadAmounts = ($oldPayment > 0) || ($oldDiscountLine > 0);

                if ($sale && $hadAmounts) {
                    // subtract old amounts that were previously applied
                    // paid_amount stores only payments; discount_amount stores discounts
                    $sale->paid_amount = max(0, $sale->paid_amount - $oldPayment);
                    if (isset($sale->discount_amount)) {
                        $sale->discount_amount = max(0, floatval($sale->discount_amount) - $oldDiscountLine);
                    }
                    // recompute due as total - (paid + discount)
                    $sale->due_amount = $sale->total_amount - (floatval($sale->paid_amount ?? 0) + floatval($sale->discount_amount ?? 0));
                    $sale->save();

                    $oldAppliedTotal += $oldPayment;
                    $oldAppliedDiscount += $oldDiscountLine;
                }

                // delete sale payments created with this receipt reference (match either placeholder or final reference)
                if ($hadAmounts) {
                    SalePayment::whereIn('reference', [self::TEMP_PAYMENT_PREFIX.$receipt->id, $receipt->reference])->where('sale_id', $oldLine->sale_id)->delete();
                }
            }

            // delete old lines
            SalesReceiptLine::where('sales_receipt_id', $receipt->id)->delete();

            $total = 0;
            $totalDiscount = 0;
            $appliedTotal = 0;
            $appliedDiscount = 0;

            // Calculate new settlement logic for update
            $receiptAmount = floatval($data['amount'] ?? 0);
            $lines = is_array($submittedLines) ? $submittedLines : [];
            $linesToProcess = $isSyntheticOpening ? [] : $lines;
            $totalAllocated = 0;
            // settlement is based on payment amounts only (discounts do not reduce receipt allocation)
            foreach ($linesToProcess as $line) {
                $totalAllocated += floatval($line['payment_amount'] ?? 0);
            }

            // Determine if receipt is settled: receipt amount equals total allocated amount
            $isReceiptSettled = abs($receiptAmount - $totalAllocated) < self::SETTLEMENT_TOLERANCE;

            // apply new lines (only process real sale lines; synthetic opening rows are skipped)
            foreach ($linesToProcess as $line) {
                $result = $this->processSaleLine($line, $receipt, $isReceiptSettled, $data);
                $appliedTotal += $result['appliedTotal'];
                $appliedDiscount += $result['appliedDiscount'];
                $total += $result['total'];
                $totalDiscount += $result['totalDiscount'];
            }

            // Ensure new referenced sales are locked and unlock previously referenced
            // sales which are no longer referenced by any receipt
            $newSaleIds = array_unique(array_map(function($l) { return intval($l['sale_id'] ?? 0); }, $linesToProcess));
            // remove any zero/invalid ids
            $newSaleIds = array_values(array_filter($newSaleIds, function($v){ return $v > 0; }));

            foreach ($newSaleIds as $sid) {
                try {
                    $s = Sale::find($sid);
                    if ($s) {
                        $s->locked = true;
                        $s->save();
                    }
                        } catch (\Exception $e) {
                            Log::warning('Failed to lock sale during receipt update', ['sale_id' => $sid, 'error' => $e->getMessage()]);
                        }
            }

            foreach ($oldSaleIds as $oldId) {
                if (in_array(intval($oldId), $newSaleIds)) continue;
                // if there are other receipt lines referring to this sale, keep it locked
                $exists = SalesReceiptLine::where('sale_id', $oldId)->exists();
                if (! $exists) {
                    try {
                        $s = Sale::find($oldId);
                        if ($s) {
                            $s->locked = false;
                            $s->save();
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to unlock sale during receipt update', ['sale_id' => $oldId, 'error' => $e->getMessage()]);
                    }
                }
            }

            // update basic receipt header and ensure balance_before recorded
            $updateAttrs = [
                'date' => $data['date'],
                'particular' => $data['particular'],
                'payment_mode' => $data['payment_mode'] ?? $receipt->payment_mode,
                'total_amount' => $receiptAmount,
                'total_discount' => $totalDiscount,
                // prefer opening_balance from the form if supplied (user may override on edit)
                'customer_balance_before' => (int) round(floatval($data['opening_balance'] ?? $customer->opening_balance ?? 0) * 100),
            ];
            $receipt->update($updateAttrs);

            // update customer balance by net change of *applied* amounts (we already have locked $customer)
            if ($customer) {
                // If the updated receipt requests applying to opening via synthetic row,
                // apply only the user-entered lineless amount (may be partial) and record it.
                if ($isSyntheticOpening && !empty($data['apply_to_opening'])) {
                    // For updates: compute payment-only applied amount and store it
                    $linelessPayment = $isSyntheticOpening && isset($submittedLines[0]['payment_amount']) ? floatval($submittedLines[0]['payment_amount']) : 0.0;
                    $linelessDiscount = $isSyntheticOpening && isset($submittedLines[0]['discount_amount']) ? floatval($submittedLines[0]['discount_amount']) : 0.0;
                    $appliedAmount = $linelessPayment > 0 ? $linelessPayment : $receiptAmount;
                    $receipt->update(['applied_to_customer' => (int) round($appliedAmount * 100)]);

                    // Persist a synthetic opening-line so opening applications are auditable
                    try {
                        $balanceBefore = is_null($receipt->customer_balance_before) ? ($customer->opening_balance ?? 0) : ($receipt->customer_balance_before / 100);
                        $appliedPaise = (int) round($appliedAmount * 100);
                        $receiptPaise = (int) round($receiptAmount * 100);
                        $isSettledLineless = abs($appliedPaise - $receiptPaise) <= 1;
                        SalesReceiptLine::create([
                            'sales_receipt_id' => $receipt->id,
                            'sale_id' => null,
                            'bill_ref' => 'Opening Balance',
                            'bill_date' => null,
                            // Lineless opening rows do not represent a sale bill; store bill_amount as zero
                            'bill_amount' => 0,
                            'received_before' => 0,
                            'balance_before' => $balanceBefore,
                            'payment_amount' => $linelessPayment,
                            'discount_amount' => $linelessDiscount,
                            'final_balance' => max(0, $balanceBefore - ($linelessPayment + $linelessDiscount)),
                            'is_settled' => $isSettledLineless,
                            'settled_at' => $isSettledLineless ? now() : null,
                            'settled_by' => $isSettledLineless ? auth()->id() : null,
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to persist synthetic opening line (update)', ['receipt_id' => $receipt->id, 'error' => $e->getMessage()]);
                    }

                    // Apply payment + discount to the customer's balances so discounts reduce opening balance
                    $linelessApplyForBalance = $linelessPayment + $linelessDiscount;
                    if ($linelessApplyForBalance > 0) {
                        $this->applyReceiptToCustomer($customer, $linelessApplyForBalance);
                    } elseif ($appliedAmount > 0) {
                        $this->applyReceiptToCustomer($customer, $appliedAmount);
                    }

                    // Add remaining excess (receiptAmount - appliedAmount)
                    $excessAmount = $receiptAmount - $appliedAmount;
                    if ($excessAmount > 0) {
                        $customer->excess_amount = floatval($customer->excess_amount ?? 0) + $excessAmount;
                        $customer->save();
                    }
                } else {
                    $netAppliedChange = ($appliedTotal + $appliedDiscount) - ($oldAppliedTotal + $oldAppliedDiscount);
                    if ($netAppliedChange > 0) {
                        // more applied now -> consume excess first then opening balance
                        $this->applyReceiptToCustomer($customer, $netAppliedChange);
                    } elseif ($netAppliedChange < 0) {
                        // less applied now -> restore amounts back to customer
                        $this->revertReceiptOnCustomer($customer, abs($netAppliedChange));
                    }

                    // Remove the old excess that was stored when this receipt was previously saved,
                    // then add the new excess so we never double-count.
                    $oldExcess = floatval($oldTotal) - ($oldAppliedTotal + $oldAppliedDiscount);
                    if ($oldExcess > 0) {
                        $customer->excess_amount = max(0, floatval($customer->excess_amount ?? 0) - $oldExcess);
                    }

                    // Add excess receipt amount (Receipt Amount - Applied Amount) to excess_amount
                    $newNetApplied = $appliedTotal + $appliedDiscount;
                    $excessAmount = $receiptAmount - $newNetApplied;
                    if ($excessAmount > 0) {
                        $customer->excess_amount = floatval($customer->excess_amount ?? 0) + $excessAmount;
                        $customer->save();
                    } elseif ($excessAmount < 0) {
                        // If excess amount became negative, reduce excess_amount
                        $customer->excess_amount = max(0, floatval($customer->excess_amount ?? 0) + $excessAmount);
                        $customer->save();
                    } else {
                        // excessAmount == 0: save any old-excess removal that was applied above
                        $customer->save();
                    }
                }
            }
        });

        toast('Sales Receipt updated', 'success');

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['success' => true, 'redirect' => route('sales-receipts.show', $id)]);
        }

        return redirect()->route('sales-receipts.show', $id);
    }

    public function destroy($id) {
        abort_if(Gate::denies('delete_sales_receipts'), 403);

        DB::transaction(function() use ($id) {
            $receipt = SalesReceipt::with('lines')->findOrFail($id);

            // reverse effects using helper method
            $appliedTotal = 0;
            $appliedDiscount = 0;
            $linelessAmount = 0.0;
            foreach ($receipt->lines as $line) {
                $oldPayment = floatval($line->payment_amount ?? 0);
                $oldDiscount = floatval($line->discount_amount ?? 0);
                // If this is a lineless/persisted opening line, don't call per-sale revert helper
                // (it expects a sale_id). Instead accumulate its payment+discount separately.
                if (empty($line->sale_id)) {
                    $linelessAmount += ($oldPayment + $oldDiscount);
                    continue;
                }

                $result = $this->revertReceiptLineEffects($line, $receipt->id, $receipt->reference);
                $appliedTotal += $result['payment'];
                $appliedDiscount += $result['discount'];
            }

            // update customer only for applied amounts (revert the previously applied amounts), include lineless applied if any
            $customer = Customer::lockForUpdate()->find($receipt->customer_id);
            if ($customer) {
                // If no persisted lineless line was present, fall back to legacy applied_to_customer
                if ($linelessAmount <= 0) {
                    $linelessPaise = intval($receipt->applied_to_customer ?? 0);
                    $linelessAmount = floatval($linelessPaise) / 100;
                }

                $this->revertReceiptOnCustomer($customer, floatval($appliedTotal + $appliedDiscount) + $linelessAmount);

                // Subtract excess receipt amount that was previously added to excess_amount
                $receiptAmount = floatval($receipt->total_amount ?? 0);
                $excessAmount = $receiptAmount - (($appliedTotal + $appliedDiscount) + $linelessAmount);
                if ($excessAmount > 0) {
                    $customer->excess_amount = max(0, floatval($customer->excess_amount ?? 0) - $excessAmount);
                    $customer->save();
                }
            }

            // delete lines and receipt
            SalesReceiptLine::where('sales_receipt_id', $receipt->id)->delete();
            $receipt->delete();
        });

        toast('Sales Receipt deleted', 'warning');
        return redirect()->route('sales-receipts.index');
    }

    // sales search API
    public function searchSales(Request $request) {
        $q = $request->get('q');
        $customerId = $request->get('customer_id');
        // support optional include_ids[] so the UI can ask the server to include
        // specific sale ids even if they would normally be filtered out (e.g. due_amount == 0)
        $includeIds = $request->get('include_ids', []);
        $receiptId = $request->get('receipt_id');
        $receiptLinesMap = [];
        if ($receiptId) {
            // load lines for this receipt so we can return sale amounts "before" this receipt's payments
            $lines = \Modules\SalesReceipt\Entities\SalesReceiptLine::where('sales_receipt_id', $receiptId)->get();
            foreach ($lines as $l) {
                $sid = $l->sale_id;
                if (!isset($receiptLinesMap[$sid])) $receiptLinesMap[$sid] = ['payment' => 0, 'discount' => 0];
                $receiptLinesMap[$sid]['payment'] += floatval($l->payment_amount ?? 0);
                $receiptLinesMap[$sid]['discount'] += floatval($l->discount_amount ?? 0);
            }
        }
        if (!is_array($includeIds)) {
            // support comma separated or single value
            $includeIds = $includeIds ? explode(',', $includeIds) : [];
        }

        $query = Sale::query();
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        if ($q) {
            $query->where(function($sub) use ($q) {
                $sub->where('reference', 'like', "%{$q}%")
                    ->orWhere('total_amount', 'like', "%{$q}%");
            });
        }

        // only bills with outstanding or include explicitly requested ids
        // Exclude Draft sales from the available bills list (they should not be selectable)
        $query->where(function($sub) use ($includeIds) {
            $sub->where(function($q) {
                $q->where('due_amount', '>', 0)
                  ->where(function($q2) {
                      $q2->whereNull('status')->orWhere('status', '!=', 'Draft');
                  });
            });
            if (!empty($includeIds)) {
                // still include explicitly requested ids (e.g., editing scenario)
                $sub->orWhereIn('id', $includeIds);
            }
        });

        $results = $query->limit(20)->get()->map(function($s) use ($receiptLinesMap) {
            // compute amounts adjusted for an editing receipt (if present)
            $bill = floatval($s->overall_amount ?? $s->total_amount ?? 0);
            $total = floatval($s->total_amount ?? 0);
            $paid = floatval($s->paid_amount ?? 0);
            $saleDiscount = floatval($s->discount_amount ?? 0);

            // Compute due based on (bill - sale-level discount) so balances reflect discounts
            $due = ($bill - $saleDiscount) - $paid;

            if (!empty($receiptLinesMap) && isset($receiptLinesMap[$s->id])) {
                $adj = $receiptLinesMap[$s->id];
                // When editing an existing receipt, the map contains how much this receipt
                // had previously applied to the bill (payments and discounts). The sale's
                // stored `paid_amount` contains only payments (not discounts), and
                // `discount_amount` stores discounts. To compute the "before" state we
                // must subtract only the payment portion from `paid_amount` and only the
                // discount portion from the sale-level discount when computing due.
                $paymentAdj = floatval($adj['payment'] ?? 0);
                $discountAdj = floatval($adj['discount'] ?? 0);
                $paidBefore = max(0, $paid - $paymentAdj);
                $discountBefore = max(0, $saleDiscount - $discountAdj);
                $dueBefore = ($bill - $discountBefore) - $paidBefore;
            } else {
                $paidBefore = $paid;
                $dueBefore = $due;
            }

            // format as rupees strings with 2 decimals to keep client display consistent
            $fmt = function($v) { return number_format(floatval($v), 2, '.', ''); };

            return [
                'id' => $s->id,
                'text' => $s->reference . ' (' . $s->customer_name . ')',
                'reference' => $s->reference,
                'date' => $s->date,
                // return numeric floats (rupees) so clients can format as needed
                'total_amount' => round($total, 2),
                // Prefer an explicit bill_amount (overall_net_rate when available)
                'bill_amount' => round(floatval($bill), 2),
                'paid_amount' => round($paidBefore, 2),
                'due_amount' => round($dueBefore, 2),
                // Do not prefill sale-level discount in the receipt UI; default to 0.00
                'discount_amount' => 0.00,
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Toggle settlement for a given receipt line. When settling, create SalePayment and
     * update sale paid/due and customer balance. When unsetting, reverse the payment.
     */
    public function toggleSettle(Request $request, $receiptId, $lineId)
    {
        $receipt = SalesReceipt::with('lines')->findOrFail($receiptId);
        $line = SalesReceiptLine::where('sales_receipt_id', $receiptId)->where('id', $lineId)->firstOrFail();

        // only applicable for cheque-mode receipts
        if (($receipt->payment_mode ?? '') !== 'Cheque') {
            return response()->json(['error' => 'Settlement is only applicable for cheque payment mode.'], 422);
        }

        DB::transaction(function() use ($receipt, $line) {
            if (empty($line->sale_id)) {
                // cannot settle an opening/lineless row
                throw new \Exception('Cannot settle an opening balance line');
            }

            $sale = Sale::lockForUpdate()->findOrFail($line->sale_id);
            $customer = Customer::lockForUpdate()->findOrFail($receipt->customer_id);

            // if currently not settled, apply payment
            if (!($line->is_settled ?? false)) {
                $amount = floatval($line->payment_amount ?? 0);
                $discount = floatval($line->discount_amount ?? 0);
                if ($amount > 0) {
                    SalePayment::create([
                        'date' => $receipt->date,
                        'reference' => $receipt->reference ?? ('REC/'.$receipt->id),
                        'amount' => $amount,
                        'sale_id' => $sale->id,
                        'payment_method' => $receipt->payment_mode ?? 'Cheque'
                    ]);
                }

                // update sale: paid_amount only increases by actual payment; discount tracked separately
                $sale->paid_amount = floatval($sale->paid_amount ?? 0) + $amount;
                if (isset($sale->discount_amount)) {
                    $sale->discount_amount = floatval($sale->discount_amount ?? 0) + $discount;
                }
                $sale->due_amount = $sale->total_amount - (floatval($sale->paid_amount ?? 0) + floatval($sale->discount_amount ?? 0));
                $sale->save();

                // mark line settled
                $line->is_settled = true;
                $line->settled_at = now();
                $line->settled_by = auth()->id();
                $line->save();

                // update customer balance by applied amounts (consume excess first)
                $this->applyReceiptToCustomer($customer, floatval($amount + $discount));
            } else {
                // currently settled -> unset (reverse payment)
                $amount = floatval($line->payment_amount ?? 0);
                $discount = floatval($line->discount_amount ?? 0);

                // remove associated sale payments for this receipt & sale
                SalePayment::whereIn('reference', ['REC/'.$receipt->id, $receipt->reference])->where('sale_id', $sale->id)->delete();

                // update sale by subtracting payment and discount separately
                $sale->paid_amount = max(0, floatval($sale->paid_amount ?? 0) - $amount);
                if (isset($sale->discount_amount)) {
                    $sale->discount_amount = max(0, floatval($sale->discount_amount ?? 0) - $discount);
                }
                $sale->due_amount = $sale->total_amount - (floatval($sale->paid_amount ?? 0) + floatval($sale->discount_amount ?? 0));
                $sale->save();

                // unset and clear settlement metadata
                $line->is_settled = false;
                $line->settled_at = null;
                $line->settled_by = null;
                $line->save();

                // restore customer balance (revert applied amounts)
                $this->revertReceiptOnCustomer($customer, floatval($amount + $discount));
            }
        });

        return response()->json(['success' => true]);
    }

    /**
     * Process a single sale line: create receipt line, sale payment, update sale amounts.
     *
     * @param array $line Line data with sale_id, payment_amount, discount_amount
     * @param SalesReceipt $receipt The parent receipt
     * @param bool $isReceiptSettled Whether the receipt is considered settled
     * @param array $data Request data containing date, payment_mode
     * @return array Totals: appliedTotal, appliedDiscount, total, totalDiscount
     */
    protected function processSaleLine(array $line, SalesReceipt $receipt, bool $isReceiptSettled, array $data): array
    {
        $sale = Sale::lockForUpdate()->findOrFail($line['sale_id']);

        // Use overall_net_rate (customer-payable) as authoritative bill amount
        $billAmount = $sale->overall_amount ?? $sale->total_amount;
        $receivedBefore = floatval($sale->paid_amount ?? 0);
        $saleLevelDiscount = floatval($sale->discount_amount ?? 0);

        // Compute balance_before and final_balance using post-discount base: (bill - sale_discount)
        $balanceBefore = ($billAmount - $saleLevelDiscount) - $receivedBefore;

        $paymentAmount = floatval($line['payment_amount']);
        $discountAmount = floatval($line['discount_amount'] ?? 0);

        // After applying: paid increases by paymentAmount only, discounts accumulate on discount_amount
        $paidAfter = $receivedBefore + $paymentAmount;
        $discountAfter = $saleLevelDiscount + $discountAmount;
        $newDue = ($billAmount - $discountAfter) - $paidAfter;

        SalesReceiptLine::create([
            'sales_receipt_id' => $receipt->id,
            'sale_id' => $sale->id,
            'bill_ref' => $sale->reference,
            'bill_date' => $sale->date,
            'bill_amount' => $billAmount,
            'received_before' => $receivedBefore,
            'balance_before' => $balanceBefore,
            'payment_amount' => $paymentAmount,
            'discount_amount' => $discountAmount,
            'final_balance' => max(0, ($billAmount - $saleLevelDiscount) - ($receivedBefore + $paymentAmount + $discountAmount)),
            'is_settled' => $isReceiptSettled,
            'settled_at' => $isReceiptSettled ? now() : null,
            'settled_by' => $isReceiptSettled ? auth()->id() : null,
        ]);

        // Lock sale to prevent concurrent modifications
        try {
            $sale->locked = true;
            $sale->save();
        } catch (\Exception $e) {
            Log::warning('Failed to lock sale', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
        }

        // Create sale payment record
        if ($paymentAmount > 0) {
            $payRef = $receipt->reference ?? (self::TEMP_PAYMENT_PREFIX . $receipt->id);
            SalePayment::create([
                'date' => $data['date'],
                'reference' => $payRef,
                'amount' => $paymentAmount,
                'sale_id' => $sale->id,
                'payment_method' => $data['payment_mode'] ?? 'Cash'
            ]);
        }

        // Update sale amounts
        $appliedTotal = 0;
        $appliedDiscount = 0;
        if ($paymentAmount > 0 || $discountAmount > 0) {
            // Persist paid and discount separately and recompute due/status
            $sale->paid_amount = $paidAfter;
            $sale->discount_amount = $discountAfter;
            $sale->due_amount = $newDue;
            $postDiscountBase = ($billAmount - $discountAfter);
            if ($sale->due_amount == $postDiscountBase) {
                $sale->payment_status = 'Unpaid';
            } elseif ($sale->due_amount > 0) {
                $sale->payment_status = 'Partial';
            } else {
                $sale->payment_status = 'Paid';
            }
            $appliedTotal = $paymentAmount;
            $appliedDiscount = $discountAmount;
        }

        try {
            $sale->save();
        } catch (\Exception $e) {
            Log::warning('Failed to save sale after payment', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
        }

        return [
            'appliedTotal' => $appliedTotal,
            'appliedDiscount' => $appliedDiscount,
            'total' => $paymentAmount,
            'totalDiscount' => $discountAmount,
        ];
    }

    /**
     * Revert effects of a receipt line: remove payments, restore sale amounts, unlock sale if appropriate.
     *
     * @param SalesReceiptLine $line The line to revert
     * @param int $receiptId The receipt ID
     * @param string|null $receiptReference The receipt reference
     * @return array Keys: payment, discount (amounts that were reverted)
     */
    protected function revertReceiptLineEffects(SalesReceiptLine $line, int $receiptId, ?string $receiptReference): array
    {
        $oldPayment = floatval($line->payment_amount ?? 0);
        $oldDiscount = floatval($line->discount_amount ?? 0);
        $hadAmounts = ($oldPayment > 0) || ($oldDiscount > 0);

        if (!$hadAmounts) {
            return ['payment' => 0, 'discount' => 0];
        }

        $sale = Sale::lockForUpdate()->find($line->sale_id);
        if ($sale) {
            // Subtract previously applied payment (payments only) from stored paid_amount
            $sale->paid_amount = max(0, floatval($sale->paid_amount ?? 0) - $oldPayment);

            // Also reduce the stored sale-level discount if this discount was applied via the receipt line.
            if (isset($sale->discount_amount)) {
                $sale->discount_amount = max(0, floatval($sale->discount_amount) - $oldDiscount);
            }

            // Recompute due using the authoritative bill_amount on the line (or sale fields)
            $bill = floatval($line->bill_amount ?? ($sale->overall_amount ?? $sale->total_amount ?? 0));
            $saleLevelDiscount = floatval($sale->discount_amount ?? 0);
            $postDiscountBase = ($bill - $saleLevelDiscount);
            $sale->due_amount = $postDiscountBase - floatval($sale->paid_amount ?? 0);

            if ($sale->due_amount == $postDiscountBase) {
                $sale->payment_status = 'Unpaid';
            } elseif ($sale->due_amount > 0) {
                $sale->payment_status = 'Partial';
            } else {
                $sale->payment_status = 'Paid';
            }
            $sale->save();

            // Unlock sale if no other receipts reference it
            $otherExists = SalesReceiptLine::where('sale_id', $line->sale_id)
                ->where('sales_receipt_id', '!=', $receiptId)
                ->exists();
            if (!$otherExists) {
                try {
                    $sale->locked = false;
                    $sale->save();
                } catch (\Exception $e) {
                    Log::warning('Failed to unlock sale', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
                }
            }
        }

        // Delete associated sale payments
        $refs = [self::TEMP_PAYMENT_PREFIX . $receiptId];
        if ($receiptReference) {
            $refs[] = $receiptReference;
        }
        // Delete payments created for this receipt by reference
        SalePayment::whereIn('reference', $refs)->where('sale_id', $line->sale_id)->delete();
        // Also delete or unlink payments that were linked to this receipt via sales_receipt_id
        SalePayment::where('sales_receipt_id', $receiptId)->where('sale_id', $line->sale_id)->delete();

        // If there are no remaining payments for this sale, clear its payment_method and mark status Pending
        $remaining = SalePayment::where('sale_id', $line->sale_id)->exists();
        if (! $remaining && $sale) {
            try {
                $sale->payment_method = null;
                // Determine correct payment_status based on recomputed due amount
                $bill = floatval($line->bill_amount ?? ($sale->overall_amount ?? $sale->total_amount ?? 0));
                $saleLevelDiscount = floatval($sale->discount_amount ?? 0);
                $postDiscountBase = $bill;
                if ($sale->due_amount == $postDiscountBase) {
                    $sale->payment_status = 'Unpaid';
                } elseif ($sale->due_amount > 0) {
                    $sale->payment_status = 'Partial';
                } else {
                    $sale->payment_status = 'Paid';
                }
                $sale->save();
            } catch (\Exception $e) {
                // ignore
            }
        }

        return ['payment' => $oldPayment, 'discount' => $oldDiscount];
    }

    /**
     * Apply a receipt amount to the customer according to Option 2:
     * - Subtract up to `amount` from `excess_amount` (not below 0)
     * - Also subtract up to `amount` from `opening_balance` (not below 0)
     * Note: total subtracted may be up to 2 * amount per user request.
     */
    protected function applyReceiptToCustomer(Customer $customer, float $amount)
    {
        $amount = floatval($amount);
        if ($amount <= 0) return;

        // subtract from excess up to amount
        $excess = floatval($customer->excess_amount ?? 0);
        if ($excess > 0) {
            $appliedExcess = min($excess, $amount);
            $customer->excess_amount = max(0, $excess - $appliedExcess);
        }

        // subtract from opening up to amount
        $opening = floatval($customer->opening_balance ?? 0);
        if ($opening > 0) {
            $appliedOpening = min($opening, $amount);
            $customer->opening_balance = max(0, $opening - $appliedOpening);
        }

        $customer->save();
    }

    /**
     * Revert a previously applied receipt amount (Option 2 simple revert):
     * - Add the full `amount` back to `opening_balance`.
     * - Does not attempt to restore `excess_amount` (no per-receipt split stored).
     */
    protected function revertReceiptOnCustomer(Customer $customer, float $amount)
    {
        $amount = floatval($amount);
        if ($amount <= 0) return;

        $customer->opening_balance = floatval($customer->opening_balance ?? 0) + $amount;
        $customer->save();
    }
}
