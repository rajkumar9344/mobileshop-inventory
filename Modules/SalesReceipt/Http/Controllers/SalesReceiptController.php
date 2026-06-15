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

    /**
     * Return the references of any not-settled receipts for a customer.
     * A receipt is "not settled" when it has at least one line with is_settled = false.
     */
    protected function customerUnsettledReceipts($customerId, $excludeReceiptId = null): array
    {
        if (empty($customerId)) return [];

        $query = SalesReceipt::where('customer_id', $customerId)
            ->whereHas('lines', function ($l) {
                $l->where('is_settled', false);
            });

        if ($excludeReceiptId) {
            $query->where('id', '!=', $excludeReceiptId);
        }

        return $query->orderBy('id')->pluck('reference')->filter()->values()->all();
    }

    /**
     * AJAX: check whether the selected customer has not-settled receipts.
     */
    public function unsettledCheck(Request $request)
    {
        abort_if(!auth()->check(), 401);

        $refs = $this->customerUnsettledReceipts($request->get('customer_id'));

        return response()->json([
            'has_unsettled' => count($refs) > 0,
            'references'    => $refs,
        ]);
    }

    public function store(StoreSalesReceiptRequest $request) {
        abort_if(Gate::denies('create_sales_receipts'), 403);
        $data = $request->validated();

        // Block: customer must have no not-settled receipts before creating a new one.
        $unsettled = $this->customerUnsettledReceipts($data['customer_id'] ?? null);
        if (!empty($unsettled)) {
            $msg = 'This customer has not-settled receipt(s): ' . implode(', ', $unsettled)
                 . '. Please settle them before creating a new receipt.';
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['errors' => ['customer_id' => [$msg]]], 422);
            }
            return redirect()->back()->withInput()->withErrors(['customer_id' => $msg]);
        }

        // Additional per-line business validation: ensure payment + discount <= sale due
        $errors = [];
        $submittedLines = is_array($data['lines'] ?? null) ? $data['lines'] : [];

        // Detect synthetic opening-row: single submitted line with empty sale_id while apply_to_opening checked.
        // Capture the payment+discount the user entered (so we can apply partial amounts to opening),
        // but skip sale-level validation for this synthetic row.
        // Identify the opening-balance line (empty sale_id) when apply_to_opening is checked.
        // Bill lines (with sale_id) and a single opening line may be submitted together,
        // so one receipt can settle bills AND reduce Open Balance at the same time.
        $openingLine = null;
        if (!empty($data['apply_to_opening'])) {
            foreach ($submittedLines as $l) {
                if (empty($l['sale_id'])) { $openingLine = $l; break; }
            }
        }
        $isSyntheticOpening = $openingLine !== null;

        foreach ($submittedLines as $idx => $line) {
            $saleId = $line['sale_id'] ?? null;
            if (empty($saleId)) {
                // Opening-balance line (no sale) — validated separately, not against a sale.
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

    DB::transaction(function() use ($request, $data, $openingLine, $submittedLines, $isSyntheticOpening) {
            // lock customer row to avoid concurrent balance updates
            $customer = Customer::lockForUpdate()->findOrFail($data['customer_id']);

            // create receipt including a snapshot of the customer's Open Balance
            $receipt = SalesReceipt::create([
                'date' => $data['date'],
                'customer_id' => $data['customer_id'],
                'particular' => $data['particular'],
                'payment_mode' => $data['payment_mode'] ?? null,
                'total_amount' => 0,
                'total_discount' => 0,
                'customer_balance_before' => (int) round(floatval($data['opening_balance'] ?? $customer->opening_balance ?? 0) * 100),
                'created_by' => auth()->id()
            ]);

            $receiptAmount = floatval($data['amount'] ?? 0);

            // Bill lines (with sale_id) reduce each sale's own due — they do NOT touch
            // Open Balance. The opening line (no sale_id) reduces Open Balance.
            $billLines = array_values(array_filter(is_array($submittedLines) ? $submittedLines : [], function($l){ return !empty($l['sale_id']); }));
            $billPayments = 0.0;
            foreach ($billLines as $l) { $billPayments += floatval($l['payment_amount'] ?? 0); }
            $openingPayment  = $openingLine ? floatval($openingLine['payment_amount'] ?? 0) : 0.0;
            $openingDiscount = $openingLine ? floatval($openingLine['discount_amount'] ?? 0) : 0.0;

            // Settlement: receipt amount equals total payments allocated (bills + opening)
            $totalAllocated = $billPayments + $openingPayment;
            $isReceiptSettled = abs($receiptAmount - $totalAllocated) < self::SETTLEMENT_TOLERANCE;

            $totalDiscount = 0.0;
            foreach ($billLines as $line) {
                $result = $this->processSaleLine($line, $receipt, $isReceiptSettled, $data);
                $totalDiscount += $result['totalDiscount'];
            }

            // update receipt totals + reference
            $receipt->update([
                'total_amount' => $receiptAmount,
                'total_discount' => $totalDiscount,
                'reference' => self::REFERENCE_PREFIX . str_pad($receipt->id, 5, '0', STR_PAD_LEFT),
            ]);

            // point temp-referenced sale payments at the final receipt reference
            \Modules\Sale\Entities\SalePayment::where('reference', self::TEMP_PAYMENT_PREFIX.$receipt->id)
                ->update(['reference' => $receipt->reference]);

            // Open Balance application — only the opening line reduces Open Balance.
            if ($openingLine) {
                $openingApply = $openingPayment + $openingDiscount;
                $balanceBefore = !is_null($receipt->customer_balance_before)
                    ? ($receipt->customer_balance_before / 100)
                    : floatval($customer->opening_balance ?? 0);

                // applied_to_customer tracks money received against Open Balance (payments only)
                $receipt->update(['applied_to_customer' => (int) round($openingPayment * 100)]);

                try {
                    SalesReceiptLine::create([
                        'sales_receipt_id' => $receipt->id,
                        'sale_id' => null,
                        'bill_ref' => 'Opening Balance',
                        'bill_date' => null,
                        'bill_amount' => 0,
                        'received_before' => 0,
                        'balance_before' => $balanceBefore,
                        'payment_amount' => $openingPayment,
                        'discount_amount' => $openingDiscount,
                        'final_balance' => max(0, $balanceBefore - $openingApply),
                        'is_settled' => $isReceiptSettled,
                        'settled_at' => $isReceiptSettled ? now() : null,
                        'settled_by' => $isReceiptSettled ? auth()->id() : null,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to persist opening line', ['receipt_id' => $receipt->id, 'error' => $e->getMessage()]);
                }

                // reduce the customer's Open Balance by the applied amount (payment + discount)
                if ($openingApply > 0) {
                    $customer->opening_balance = max(0, floatval($customer->opening_balance ?? 0) - $openingApply);
                    $customer->save();
                }
            }

            // Excess: any receipt amount not allocated to bills or opening becomes customer credit
            $excessAmount = $receiptAmount - ($billPayments + $openingPayment);
            if ($excessAmount > 0) {
                $customer->excess_amount = floatval($customer->excess_amount ?? 0) + $excessAmount;
                $customer->save();
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

        // Identify the opening-balance line (empty sale_id). Bill lines and one opening
        // line may be submitted together so one receipt settles bills AND Open Balance.
        $submittedLines = is_array($data['lines'] ?? null) ? $data['lines'] : [];
        $openingLine = null;
        if (!empty($data['apply_to_opening'])) {
            foreach ($submittedLines as $l) {
                if (empty($l['sale_id'])) { $openingLine = $l; break; }
            }
        }
        $isSyntheticOpening = $openingLine !== null;

        DB::transaction(function() use ($data, $id, $submittedLines, $isSyntheticOpening, $openingLine) {
            $receipt = SalesReceipt::with('lines')->findOrFail($id);
            // lock the customer early and keep for reuse
            $customer = Customer::lockForUpdate()->find($receipt->customer_id);

            // If this existing receipt previously applied directly to opening (lineless), revert that first
            // If this receipt previously applied directly to opening (lineless), revert that first.
            // Prefer the persisted lineless line's payment+discount (if present) otherwise fall back to
            // the stored `applied_to_customer` (payments-only) for backwards compatibility.
            $oldLinelessAmount = 0.0;
            $oldOpeningPayment = 0.0;
            $linelessLine = $receipt->lines->first(function($l){ return empty($l->sale_id); });
            if ($linelessLine) {
                $oldOpeningPayment = floatval($linelessLine->payment_amount ?? 0);
                $oldLinelessAmount = floatval(($linelessLine->payment_amount ?? 0) + ($linelessLine->discount_amount ?? 0));
            } else {
                $oldLinelessPaise = intval($receipt->applied_to_customer ?? 0);
                $oldLinelessAmount = floatval($oldLinelessPaise) / 100;
                $oldOpeningPayment = $oldLinelessAmount;
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

            // Bill lines reduce each sale's own due; the opening line reduces Open Balance.
            $receiptAmount = floatval($data['amount'] ?? 0);
            $billLines = array_values(array_filter(is_array($submittedLines) ? $submittedLines : [], function($l){ return !empty($l['sale_id']); }));
            $billPayments = 0.0;
            foreach ($billLines as $l) { $billPayments += floatval($l['payment_amount'] ?? 0); }
            $openingPayment  = $openingLine ? floatval($openingLine['payment_amount'] ?? 0) : 0.0;
            $openingDiscount = $openingLine ? floatval($openingLine['discount_amount'] ?? 0) : 0.0;

            $totalAllocated = $billPayments + $openingPayment;
            $isReceiptSettled = abs($receiptAmount - $totalAllocated) < self::SETTLEMENT_TOLERANCE;

            $totalDiscount = 0.0;
            foreach ($billLines as $line) {
                $result = $this->processSaleLine($line, $receipt, $isReceiptSettled, $data);
                $totalDiscount += $result['totalDiscount'];
            }

            // Lock new referenced sales; unlock previously referenced sales no longer used.
            $newSaleIds = array_values(array_filter(array_unique(array_map(function($l){ return intval($l['sale_id'] ?? 0); }, $billLines)), function($v){ return $v > 0; }));
            foreach ($newSaleIds as $sid) {
                try { $s = Sale::find($sid); if ($s) { $s->locked = true; $s->save(); } }
                catch (\Exception $e) { Log::warning('Failed to lock sale during receipt update', ['sale_id' => $sid, 'error' => $e->getMessage()]); }
            }
            foreach ($oldSaleIds as $oldId) {
                if (in_array(intval($oldId), $newSaleIds)) continue;
                if (! SalesReceiptLine::where('sale_id', $oldId)->exists()) {
                    try { $s = Sale::find($oldId); if ($s) { $s->locked = false; $s->save(); } }
                    catch (\Exception $e) { Log::warning('Failed to unlock sale during receipt update', ['sale_id' => $oldId, 'error' => $e->getMessage()]); }
                }
            }

            // update receipt header
            $receipt->update([
                'date' => $data['date'],
                'particular' => $data['particular'],
                'payment_mode' => $data['payment_mode'] ?? $receipt->payment_mode,
                'total_amount' => $receiptAmount,
                'total_discount' => $totalDiscount,
                'customer_balance_before' => (int) round(floatval($data['opening_balance'] ?? ($customer ? $customer->opening_balance : 0) ?? 0) * 100),
                'applied_to_customer' => $openingLine ? (int) round($openingPayment * 100) : null,
            ]);

            if ($customer) {
                // Open Balance application — only the opening line reduces Open Balance.
                if ($openingLine) {
                    $openingApply = $openingPayment + $openingDiscount;
                    $balanceBefore = !is_null($receipt->customer_balance_before) ? ($receipt->customer_balance_before / 100) : floatval($customer->opening_balance ?? 0);
                    try {
                        SalesReceiptLine::create([
                            'sales_receipt_id' => $receipt->id,
                            'sale_id' => null,
                            'bill_ref' => 'Opening Balance',
                            'bill_date' => null,
                            'bill_amount' => 0,
                            'received_before' => 0,
                            'balance_before' => $balanceBefore,
                            'payment_amount' => $openingPayment,
                            'discount_amount' => $openingDiscount,
                            'final_balance' => max(0, $balanceBefore - $openingApply),
                            'is_settled' => $isReceiptSettled,
                            'settled_at' => $isReceiptSettled ? now() : null,
                            'settled_by' => $isReceiptSettled ? auth()->id() : null,
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to persist opening line (update)', ['receipt_id' => $receipt->id, 'error' => $e->getMessage()]);
                    }
                    if ($openingApply > 0) {
                        $customer->opening_balance = max(0, floatval($customer->opening_balance ?? 0) - $openingApply);
                        $customer->save();
                    }
                }

                // Excess reconciliation: remove the old receipt's excess, then add the new one.
                $oldExcess = max(0, floatval($oldTotal) - ($oldAppliedTotal + $oldOpeningPayment));
                if ($oldExcess > 0) {
                    $customer->excess_amount = max(0, floatval($customer->excess_amount ?? 0) - $oldExcess);
                    $customer->save();
                }
                $newExcess = $receiptAmount - ($billPayments + $openingPayment);
                if ($newExcess > 0) {
                    $customer->excess_amount = floatval($customer->excess_amount ?? 0) + $newExcess;
                    $customer->save();
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

            // Reverse effects. Bill lines are reverted by un-paying their sale (which
            // restores Bill Balance) and do NOT touch Open Balance. The opening line's
            // amount is added back to Open Balance.
            $appliedTotal = 0;        // bill payments reverted
            $appliedDiscount = 0;     // bill discounts reverted
            $openingApplied = 0.0;    // opening payment + discount (restores Open Balance)
            $openingPaymentOnly = 0.0; // opening payment (counts toward receipt allocation)
            foreach ($receipt->lines as $line) {
                $oldPayment = floatval($line->payment_amount ?? 0);
                $oldDiscount = floatval($line->discount_amount ?? 0);
                if (empty($line->sale_id)) {
                    $openingApplied += ($oldPayment + $oldDiscount);
                    $openingPaymentOnly += $oldPayment;
                    continue;
                }

                $result = $this->revertReceiptLineEffects($line, $receipt->id, $receipt->reference);
                $appliedTotal += $result['payment'];
                $appliedDiscount += $result['discount'];
            }

            $customer = Customer::lockForUpdate()->find($receipt->customer_id);
            if ($customer) {
                // Fall back to the legacy applied_to_customer if no opening line was persisted.
                if ($openingApplied <= 0) {
                    $linelessPaise = intval($receipt->applied_to_customer ?? 0);
                    $openingApplied = floatval($linelessPaise) / 100;
                    $openingPaymentOnly = $openingApplied;
                }

                // Restore ONLY the Open Balance portion (bills already reverted above).
                $this->revertReceiptOnCustomer($customer, $openingApplied);

                // Remove the excess that this receipt had added: receipt - (bill payments + opening payment).
                $receiptAmount = floatval($receipt->total_amount ?? 0);
                $excessAmount = $receiptAmount - ($appliedTotal + $openingPaymentOnly);
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
        abort_if(Gate::denies('edit_sales_receipts'), 403);

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

                // Settling a bill payment reduces only the bill's due (done above) — it
                // does not touch Open Balance in the three-bucket model.
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

                // Un-settling restores only the bill's due (done above) — Open Balance untouched.
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
            // Persist paid amount and recompute due/status.
            // NOTE: sales.discount_amount was removed — do NOT write it (the save would
            // fail with "Unknown column" and the sale would never update, leaving the
            // bill unpaid and reappearing in later receipts).
            $sale->paid_amount = $paidAfter;
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
