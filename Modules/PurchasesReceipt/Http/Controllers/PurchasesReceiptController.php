<?php

namespace Modules\PurchasesReceipt\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Modules\PurchasesReceipt\Http\Requests\StorePurchasesReceiptRequest;
use Modules\PurchasesReceipt\Http\Requests\UpdatePurchasesReceiptRequest;
use Modules\PurchasesReceipt\Entities\PurchasesReceipt;
use Modules\PurchasesReceipt\Entities\PurchasesReceiptLine;
use Modules\Purchase\Entities\Purchase;
use Modules\Purchase\Entities\PurchasePayment;
use Modules\People\Entities\Supplier;
use Modules\PurchasesReceipt\DataTables\PurchasesReceiptsDataTable;

class PurchasesReceiptController extends Controller
{
    /** Tolerance for comparing floats when checking settlement status */
    protected const SETTLEMENT_TOLERANCE = 0.01;

    /** Prefix for temporary payment references before receipt ID is finalized */
    protected const TEMP_PAYMENT_PREFIX = 'PU-RE/';

    /** Prefix for final receipt reference numbers */
    protected const REFERENCE_PREFIX = 'PU-RE';

    public function index(PurchasesReceiptsDataTable $dataTable) {
        return $dataTable->render('purchasesreceipt::index');
    }

    /**
     * Aggregated totals for listing (supports optional filters: start_date, end_date, supplier_id, payment_mode)
     */
    public function totals() {
        $start = request()->get('start_date');
        $end = request()->get('end_date');
        $supplier = request()->get('supplier_id');
        $paymentMode = request()->get('payment_mode');

        // parse dates with Carbon to ensure proper day boundaries
        $startDt = null;
        $endDt = null;
        try {
            if ($start) $startDt = Carbon::parse($start)->startOfDay();
        } catch (\Exception $e) { $startDt = null; }
        try {
            if ($end) $endDt = Carbon::parse($end)->endOfDay();
        } catch (\Exception $e) { $endDt = null; }

        // Build query with optional filters
        $base = PurchasesReceipt::query();

        if ($startDt && $endDt) {
            $base->whereBetween('date', [$startDt->toDateTimeString(), $endDt->toDateTimeString()]);
        } elseif ($startDt) {
            $base->where('date', '>=', $startDt->toDateTimeString());
        } elseif ($endDt) {
            $base->where('date', '<=', $endDt->toDateTimeString());
        }

        if ($supplier) {
            $base->where('supplier_id', $supplier);
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

        // Retrieve collection to use model accessors (ensure rupee units)
        $collection = $base->get();

        $overallCount = $collection->count();
        $overallTotalAmount = $collection->sum(function($r){ return floatval($r->total_amount ?? 0); });
        $overallDiscount = $collection->sum(function($r){ return floatval($r->total_discount ?? 0); });
        // Received amount: total minus discount
        $overallReceived = $overallTotalAmount - $overallDiscount;

        // breakdown by payment modes (normalize stored values)
        $overallCash = 0.0;
        $overallCheque = 0.0;
        $overallUpi = 0.0;
        $overallCard = 0.0;
        $overallBank = 0.0;
        $overallProductReturn = 0.0;

        foreach ($collection as $r) {
            $pm = strtolower(trim((string)($r->payment_mode ?? '')));
            $amt = floatval($r->total_amount ?? 0);
            if ($pm === '') {
                // unknown -> treat as bank by default
                $overallBank += $amt;
                continue;
            }
            if (strpos($pm, 'cash') !== false) {
                $overallCash += $amt;
                continue;
            }
            if (strpos($pm, 'cheque') !== false || strpos($pm, 'check') !== false) {
                $overallCheque += $amt;
                continue;
            }
            if (strpos($pm, 'upi') !== false) {
                $overallUpi += $amt;
                continue;
            }
            if (strpos($pm, 'card') !== false || strpos($pm, 'cards') !== false) {
                $overallCard += $amt;
                continue;
            }
            if (strpos($pm, 'product return') !== false) {
                $overallProductReturn += $amt;
                continue;
            }
            // treat bank / bank transfer as bank category
            if (strpos($pm, 'bank') !== false || strpos($pm, 'bank transfer') !== false) {
                $overallBank += $amt;
                continue;
            }
            // fallback
            $overallBank += $amt;
        }

        $totals = [
            // keys used by the reusable daterange component
            'overall_count' => $overallCount,
            'overall_total_amount' => round(floatval($overallTotalAmount), 2),
            'overall_balance' => round(0.0, 2),
            'overall_received_amount' => round(floatval($overallReceived), 2),
            'overall_cgst' => 0.0,
            'overall_sgst' => 0.0,
            'overall_tax_amount' => 0.0,
            // per-mode amounts
            'overall_cash_amount' => round(floatval($overallCash), 2),
            'overall_cheque_amount' => round(floatval($overallCheque), 2),
            'overall_upi_amount' => round(floatval($overallUpi), 2),
            'overall_card_amount' => round(floatval($overallCard), 2),
            'overall_bank_amount' => round(floatval($overallBank), 2),
            'overall_product_return_amount' => round(floatval($overallProductReturn), 2),
            // backward-compatible keys for older callers
            'count' => $overallCount,
            'total' => round(floatval($overallReceived), 2),
            'discount' => round(floatval($overallDiscount), 2),
        ];

        return response()->json($totals);
    }

    public function create() {
        abort_if(Gate::denies('create_purchases_receipts'), 403);

        return view('purchasesreceipt::create');
    }

    /**
     * Return suppliers for the filter Select2.
     * - If user typed q, search suppliers globally (name/code)
     * - Otherwise return only suppliers who have receipts matching the optional date/payment filters
     */
    public function suppliers(Request $request)
    {
        $start = $request->get('start_date');
        $end = $request->get('end_date');

        // Return only suppliers who have receipts matching the optional date filters.
        $supplierIds = \Modules\PurchasesReceipt\Entities\PurchasesReceipt::query()
            ->when($start && $end, function($qry) use ($start, $end) { $qry->whereBetween('date', [$start, $end]); })
            ->when($start && !$end, function($qry) use ($start) { $qry->whereDate('date', '>=', $start); })
            ->when($end && !$start, function($qry) use ($end) { $qry->whereDate('date', '<=', $end); })
            ->distinct()
            ->pluck('supplier_id')
            ->toArray();

        $suppliers = \Modules\People\Entities\Supplier::whereIn('id', $supplierIds)
            ->orderBy('supplier_name', 'asc')
            ->get(['id', 'supplier_name', 'supplier_code']);

        $results = $suppliers->map(function($s) {
            return [
                'id' => $s->id,
                'text' => trim($s->supplier_name . ($s->supplier_code ? ' (' . $s->supplier_code . ')' : '')),
            ];
        })->values();

        return response()->json(['results' => $results]);
    }

    /**
     * Return the references of any not-settled receipts for a supplier.
     * "Not settled" uses the same definition as the settledNo() scope so that BOTH
     * normal receipts (line payments < total) AND lineless receipts created from a
     * Purchase Return (applied_to_supplier < total) block a new receipt. The scope is
     * wrapped in a nested where() because it uses orWhere internally — without the
     * wrapper the supplier_id filter would not apply to the lineless branch.
     */
    protected function supplierUnsettledReceipts($supplierId, $excludeReceiptId = null): array
    {
        if (empty($supplierId)) return [];

        $query = PurchasesReceipt::where('supplier_id', $supplierId)
            ->where(function ($q) { $q->settledNo(); });

        if ($excludeReceiptId) {
            $query->where('id', '!=', $excludeReceiptId);
        }

        return $query->orderBy('id')->pluck('reference')->filter()->values()->all();
    }

    /**
     * AJAX: check whether the selected supplier has not-settled receipts.
     */
    public function unsettledCheck(Request $request)
    {
        abort_if(!auth()->check(), 401);

        $refs = $this->supplierUnsettledReceipts($request->get('supplier_id'));

        return response()->json([
            'has_unsettled' => count($refs) > 0,
            'references'    => $refs,
        ]);
    }

    public function store(StorePurchasesReceiptRequest $request) {
        abort_if(Gate::denies('create_purchases_receipts'), 403);
        $data = $request->validated();

        // Block: supplier must have no not-settled receipts before creating a new one.
        $unsettled = $this->supplierUnsettledReceipts($data['supplier_id'] ?? null);
        if (!empty($unsettled)) {
            $msg = 'This supplier has not-settled receipt(s): ' . implode(', ', $unsettled)
                 . '. Please settle them before creating a new receipt.';
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['errors' => ['supplier_id' => [$msg]]], 422);
            }
            return redirect()->back()->withInput()->withErrors(['supplier_id' => $msg]);
        }

        // Additional per-line business validation: ensure payment + discount <= purchase due
        $errors = [];
        $submittedLines = is_array($data['lines'] ?? null) ? $data['lines'] : [];

        // Identify the opening-balance line (empty purchase_id). Bill lines and one
        // opening line may be submitted together so one receipt settles bills AND Open Balance.
        $openingLine = null;
        if (!empty($data['apply_to_opening'])) {
            foreach ($submittedLines as $l) {
                if (empty($l['purchase_id'])) { $openingLine = $l; break; }
            }
        }
        $isSyntheticOpening = $openingLine !== null;

        foreach ($submittedLines as $idx => $line) {
            $purchaseId = $line['purchase_id'] ?? null;
            if (empty($purchaseId)) {
                // Opening-balance line (no purchase) — validated separately.
                continue;
            }
            $purchase = Purchase::find($purchaseId);
            if (!$purchase) {
                $errors["lines.{$idx}.purchase_id"] = "Selected purchase not found.";
                continue;
            }
            $paymentAmount = floatval($line['payment_amount'] ?? 0);
            $discountAmount = floatval($line['discount_amount'] ?? 0);
            $due = $purchase->due_amount; // model accessor gives rupees

            if (($paymentAmount + $discountAmount) > ($due + 0.0001)) {
                $errors["lines.{$idx}.payment_amount"] = "Payment + discount (".($paymentAmount + $discountAmount).") exceeds outstanding due ({$due}) for purchase {$purchase->reference}.";
            }
        }

        if (!empty($errors)) {
            if ($request->wantsJson() || $request->expectsJson()) {
                $jsonErrors = array_map(function($v){ return is_array($v) ? $v : [$v]; }, $errors);
                return response()->json(['errors' => $jsonErrors], 422);
            }
            return redirect()->back()->withInput()->withErrors($errors);
        }

        DB::transaction(function() use ($request, $data, $openingLine, $submittedLines, $isSyntheticOpening) {
            // lock supplier row to avoid concurrent balance updates
            $supplier = Supplier::lockForUpdate()->findOrFail($data['supplier_id']);

            // create receipt (without reference yet) including opening balance snapshot
            $receipt = PurchasesReceipt::create([
                'date' => $data['date'],
                'supplier_id' => $data['supplier_id'],
                'particular' => $data['particular'],
                'payment_mode' => $data['payment_mode'] ?? null,
                'total_amount' => 0,
                'total_discount' => 0,
                'supplier_balance_before' => (int) round(floatval($data['opening_balance'] ?? $supplier->open_balance ?? 0) * 100),
                // Freeze the supplier's Bill Balance (unpaid dues) right now, before this
                // receipt's payments reduce any due. Paise, to match supplier_balance_before.
                'bill_balance_before' => (int) round(floatval($supplier->bill_balance ?? 0) * 100),
                'created_by' => auth()->id()
            ]);

            $total = 0; // sum of all line payments (regardless of applied)
            $totalDiscount = 0;
            $appliedTotal = 0; // sum of payments actually applied to purchase bills
            $appliedDiscount = 0;

            // Bill lines (with purchase_id) reduce each purchase's own due — they do NOT
            // touch Open Balance. The opening line (no purchase_id) reduces Open Balance.
            $receiptAmount = floatval($data['amount'] ?? 0);
            $linesToProcess = array_values(array_filter(is_array($submittedLines) ? $submittedLines : [], function($l){ return !empty($l['purchase_id']); }));
            $billAllocated = 0.0;
            foreach ($linesToProcess as $line) {
                $billAllocated += floatval($line['payment_amount'] ?? 0) + floatval($line['discount_amount'] ?? 0);
            }
            $openingPayment  = $openingLine ? floatval($openingLine['payment_amount'] ?? 0) : 0.0;
            $openingDiscount = $openingLine ? floatval($openingLine['discount_amount'] ?? 0) : 0.0;
            $totalAllocated = $billAllocated + $openingPayment + $openingDiscount;

            // Determine if receipt is settled: receipt amount equals total allocated amount
            $isReceiptSettled = abs($receiptAmount - $totalAllocated) < self::SETTLEMENT_TOLERANCE;

            foreach ($linesToProcess as $line) {
                $purchase = Purchase::lockForUpdate()->findOrFail($line['purchase_id']);

                $billAmount = $purchase->total_amount; // model returns rupees
                $paidBefore = $purchase->paid_amount;
                $balanceBefore = $purchase->due_amount;

                $paymentAmount = floatval($line['payment_amount']);
                $discountAmount = floatval($line['discount_amount'] ?? 0);

                // Always apply payments to purchase table regardless of settlement status
                // Settlement flag is only for tracking/remembering proper amount distribution
                $apply = true; // Always apply to purchase table

                // compute the final balance to display/store on the receipt line based on user-entered amounts
                $displayFinal = $billAmount - ($paidBefore + $paymentAmount + $discountAmount);

                // Always update purchase paid, discount and due amounts when there are payment/discount amounts
                if ($paymentAmount > 0 || $discountAmount > 0) {
                    // paid_amount contains only actual payments
                    $newPaid = $paidBefore + $paymentAmount;
                    // discount stored separately on purchase
                    $existingDiscount = floatval($purchase->discount_amount ?? 0);
                    $newDiscount = $existingDiscount + $discountAmount;
                    // due = total - (paid + discount)
                    $newDue = $billAmount - ($newPaid + $newDiscount);
                } else {
                    // when no payment/discount, keep existing amounts
                    $newPaid = $paidBefore;
                    $newDiscount = floatval($purchase->discount_amount ?? 0);
                    $newDue = $billAmount - ($newPaid + $newDiscount);
                }

                // create line - settlement flag reflects whether receipt amount allocation is proper
                $rline = PurchasesReceiptLine::create([
                    'purchases_receipt_id' => $receipt->id,
                    'purchase_id' => $purchase->id,
                    'bill_ref' => $purchase->reference,
                    'bill_date' => $purchase->date,
                    'bill_amount' => $billAmount,
                    'paid_before' => $paidBefore,
                    'balance_before' => $balanceBefore,
                    'payment_amount' => $paymentAmount,
                    'discount_amount' => $discountAmount,
                    // store the displayed final balance (reflects the entered payment/discount)
                    'final_balance' => $displayFinal,
                    'is_settled' => $isReceiptSettled, // settlement flag for tracking proper allocation
                    'settled_at' => $isReceiptSettled ? now() : null,
                    'settled_by' => $isReceiptSettled ? auth()->id() : null,
                ]);

                // Always create purchase payment and update purchase when there are amounts
                if ($paymentAmount > 0) {
                    PurchasePayment::create([
                        'date' => $data['date'],
                        'reference' => self::TEMP_PAYMENT_PREFIX.$receipt->id,
                        'amount' => $paymentAmount,
                        'purchase_id' => $purchase->id,
                        'payment_method' => $data['payment_mode'] ?? 'Cash'
                    ]);
                }

                // Always update purchase paid, discount and due amounts (regardless of settlement status)
                if ($paymentAmount > 0 || $discountAmount > 0) {
                    $purchase->paid_amount = $newPaid;
                    $purchase->discount_amount = $newDiscount;
                    $purchase->due_amount = $newDue;
                    // update payment_status on purchase
                    if ($purchase->due_amount == ($purchase->total_amount ?? 0)) {
                        $purchase->payment_status = 'Unpaid';
                    } elseif ($purchase->due_amount > 0) {
                        $purchase->payment_status = 'Partial';
                    } else {
                        $purchase->payment_status = 'Paid';
                    }
                    $purchase->save();

                    $appliedTotal += $paymentAmount;
                    $appliedDiscount += $discountAmount;
                }

                // mark the purchase as locked because a receipt referencing this bill exists now
                try {
                    $purchase->locked = true;
                    $purchase->save();
                } catch (\Exception $e) {
                    // non-fatal: locking a purchase shouldn't break receipt creation
                }

                // total (receipt-level) still includes all payments regardless of applied state
                $total += $paymentAmount;
                $totalDiscount += $discountAmount;
            }

            // update totals and optionally balance_before in single query
            $attrs = [
                'total_amount' => $receiptAmount,
                'total_discount' => $totalDiscount,
            ];
            if (is_null($receipt->supplier_balance_before)) {
                $attrs['supplier_balance_before'] = (int) round(floatval($supplier->open_balance ?? 0) * 100);
            }
            $receipt->update($attrs);

            if (is_null($receipt->supplier_balance_before)) {
                $receipt->supplier_balance_before = (int) round(floatval($supplier->open_balance ?? 0) * 100);
                $receipt->save();
            }

            // update receipt reference using id (use PU-RE prefix)
            $receipt->reference = self::REFERENCE_PREFIX . str_pad($receipt->id, 5, '0', STR_PAD_LEFT);
            $receipt->save();

            // update purchase_payments created with temporary PU-RE/{id} reference to final receipt reference
            \Modules\Purchase\Entities\PurchasePayment::where('reference', self::TEMP_PAYMENT_PREFIX.$receipt->id)
                ->update(['reference' => $receipt->reference]);

            // Open Balance application — only the opening line reduces Open Balance.
            if ($openingLine) {
                $openingApply = $openingPayment + $openingDiscount;
                $balanceBefore = !is_null($receipt->supplier_balance_before)
                    ? ($receipt->supplier_balance_before / 100)
                    : floatval($supplier->open_balance ?? 0);

                // applied_to_supplier tracks money paid against Open Balance (payments only)
                $receipt->applied_to_supplier = (int) round($openingPayment * 100);
                $receipt->save();

                try {
                    PurchasesReceiptLine::create([
                        'purchases_receipt_id' => $receipt->id,
                        'purchase_id' => null,
                        'bill_ref' => 'Opening Balance',
                        'bill_date' => null,
                        'bill_amount' => 0,
                        'paid_before' => 0,
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

                // reduce the supplier's Open Balance by the applied amount (payment + discount)
                if ($openingApply > 0) {
                    $supplier->open_balance = max(0, floatval($supplier->open_balance ?? 0) - $openingApply);
                    $supplier->save();
                }
            }

            // Excess: any receipt amount not allocated to bills or opening becomes supplier credit
            $excessAmount = $receiptAmount - $totalAllocated;
            if ($excessAmount > 0) {
                $supplier->excess_amount = floatval($supplier->excess_amount ?? 0) + $excessAmount;
                $supplier->save();
            }
        });

        toast('Purchases Receipt created', 'success');

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['success' => true, 'redirect' => route('purchases-receipts.index')]);
        }

        return redirect()->route('purchases-receipts.index');
    }

    public function show($id) {
        abort_if(Gate::denies('show_purchases_receipts'), 403);

        // Eager-load related purchases, their details and suppliers to avoid N+1 queries.
        $receipt = PurchasesReceipt::with([
            'lines.purchase.purchaseDetails',
            'lines.purchase.supplier',
            'supplier'
        ])->findOrFail($id);

        // Build keyed collections for easy lookup in the view (id => Purchase / id => Supplier)
        $purchases = $receipt->lines->pluck('purchase')->filter()->keyBy('id');
        $suppliers = $purchases->pluck('supplier')->filter()->keyBy('id');

        return view('purchasesreceipt::show', compact('receipt', 'purchases', 'suppliers'));
    }

    public function edit($id) {
        abort_if(Gate::denies('edit_purchases_receipts'), 403);

        $receipt = PurchasesReceipt::with('lines')->findOrFail($id);
        return view('purchasesreceipt::edit', compact('receipt'));
    }

    /**
     * Readonly view: reuse the edit UI but in readonly mode.
     */
    public function view($id)
    {
        abort_if(Gate::denies('view_purchases_receipts'), 403);

        $receipt = PurchasesReceipt::with(['lines.purchase', 'supplier'])->findOrFail($id);

        // If this receipt was applied to opening balance (lineless), synthesize a display line for the view
        if ($receipt->applied_to_supplier > 0) {
            $applied = $receipt->applied_to_supplier / 100;
            $syntheticLine = new \stdClass();
            $syntheticLine->bill_ref = 'Opening Balance';
            $syntheticLine->bill_date = '-';
            // Lineless display rows do not represent a purchase bill; show bill_amount as zero
            $syntheticLine->bill_amount = 0;
            $syntheticLine->paid_before = 0;
            if (!is_null($receipt->supplier_balance_before)) {
                $syntheticLine->balance_before = $receipt->supplier_balance_before / 100;
            } else {
                $syntheticLine->balance_before = $receipt->supplier->opening_balance ?? 0;
            }
            $syntheticLine->payment_amount = $applied;
            $syntheticLine->discount_amount = 0;
            // final balance for opening-row should be balance_before minus applied amounts
            $syntheticLine->final_balance = $syntheticLine->balance_before - $syntheticLine->payment_amount - $syntheticLine->discount_amount;
            $syntheticLine->purchase_id = null;
            $syntheticLine->is_settled = true;
            $receipt->lines->push($syntheticLine);
        }

        // Build keyed purchases collection for the view (if needed by JS)
        $purchases = $receipt->lines->pluck('purchase')->filter()->keyBy('id');

        return view('purchasesreceipt::edit', compact('receipt', 'purchases'))->with('readonly', true);
    }

    public function update(UpdatePurchasesReceiptRequest $request, $id) {
        abort_if(Gate::denies('edit_purchases_receipts'), 403);

        $data = $request->validated();

        // Identify the opening-balance line (empty purchase_id). Bill lines and one
        // opening line may be submitted together so one receipt settles bills AND Open Balance.
        $submittedLines = is_array($data['lines'] ?? null) ? $data['lines'] : [];
        $openingLine = null;
        if (!empty($data['apply_to_opening'])) {
            foreach ($submittedLines as $l) {
                if (empty($l['purchase_id'])) { $openingLine = $l; break; }
            }
        }
        $isSyntheticOpening = $openingLine !== null;

        DB::transaction(function() use ($data, $id, $submittedLines, $isSyntheticOpening, $openingLine) {
            $receipt = PurchasesReceipt::with('lines')->findOrFail($id);
            // lock supplier early and reuse variable
            $supplier = Supplier::lockForUpdate()->find($receipt->supplier_id);

            // If this existing receipt previously applied directly to opening (lineless), revert that first
            // If this receipt previously applied directly to opening (lineless), revert that first.
            // Prefer the persisted lineless line's payment+discount (if present) otherwise fall back to
            // the stored `applied_to_supplier` (payments-only) for backwards compatibility.
            $oldLinelessAmount = 0.0;
            $oldOpeningPayment = 0.0;
            $linelessLine = $receipt->lines->first(function($l){ return empty($l->purchase_id); });
            if ($linelessLine) {
                $oldOpeningPayment = floatval($linelessLine->payment_amount ?? 0);
                $oldLinelessAmount = floatval(($linelessLine->payment_amount ?? 0) + ($linelessLine->discount_amount ?? 0));
            } else {
                $oldLinelessPaise = intval($receipt->applied_to_supplier ?? 0);
                $oldLinelessAmount = floatval($oldLinelessPaise) / 100;
                $oldOpeningPayment = $oldLinelessAmount;
            }
            if ($oldLinelessAmount > 0 && $supplier) {
                $this->revertReceiptOnSupplier($supplier, $oldLinelessAmount);
                // clear stored applied fields so later logic isn't confused
                $receipt->supplier_balance_before = null;
                $receipt->applied_to_supplier = null;
                $receipt->save();
            }
            // capture purchase ids referenced by the existing receipt so we can unlock those
            // that are removed during the update (if no other receipt references them)
            $oldPurchaseIds = $receipt->lines->pluck('purchase_id')->toArray();

            // capture old totals for supplier adjustment (only amounts that were applied)
            $oldTotal = $receipt->total_amount ?? 0;
            $oldDiscount = $receipt->total_discount ?? 0;

            $oldAppliedTotal = 0;
            $oldAppliedDiscount = 0;

            // Reverse previous effects: for each existing line, remove payments and adjust purchase paid/due
            // Note: store() always created PurchasePayment and updated purchase amounts when payment/discount > 0,
            // so on update we must reverse those changes regardless of the per-line is_settled flag.
            foreach ($receipt->lines as $oldLine) {
                $purchase = Purchase::lockForUpdate()->find($oldLine->purchase_id);

                $oldPayment = floatval($oldLine->payment_amount ?? 0);
                $oldDiscountLine = floatval($oldLine->discount_amount ?? 0);

                $hadAmounts = ($oldPayment > 0) || ($oldDiscountLine > 0);

                if ($purchase && $hadAmounts) {
                    // subtract old payment from paid_amount and old discount from discount_amount
                    $purchase->paid_amount = max(0, $purchase->paid_amount - $oldPayment);
                    $purchase->discount_amount = max(0, floatval($purchase->discount_amount ?? 0) - $oldDiscountLine);
                    // recompute due as total - (paid + discount)
                    $purchase->due_amount = $purchase->total_amount - ($purchase->paid_amount + $purchase->discount_amount);
                    // update payment_status
                    if ($purchase->due_amount == ($purchase->total_amount ?? 0)) {
                        $purchase->payment_status = 'Unpaid';
                    } elseif ($purchase->due_amount > 0) {
                        $purchase->payment_status = 'Partial';
                    } else {
                        $purchase->payment_status = 'Paid';
                    }
                    $purchase->save();

                    $oldAppliedTotal += $oldPayment;
                    $oldAppliedDiscount += $oldDiscountLine;
                }

                // delete purchase payments created with this receipt reference (match either placeholder or final reference)
                if ($hadAmounts) {
                    PurchasePayment::whereIn('reference', [self::TEMP_PAYMENT_PREFIX.$receipt->id, $receipt->reference])->where('purchase_id', $oldLine->purchase_id)->delete();
                }
            }

            // delete old lines
            PurchasesReceiptLine::where('purchases_receipt_id', $receipt->id)->delete();

            $total = 0;
            $totalDiscount = 0;
            $appliedTotal = 0;
            $appliedDiscount = 0;

            // Bill lines reduce each purchase's own due; the opening line reduces Open Balance.
            $receiptAmount = floatval($data['amount'] ?? 0);
            $linesToProcess = array_values(array_filter(is_array($submittedLines) ? $submittedLines : [], function($l){ return !empty($l['purchase_id']); }));
            $billAllocated = 0.0;
            foreach ($linesToProcess as $line) {
                $billAllocated += floatval($line['payment_amount'] ?? 0) + floatval($line['discount_amount'] ?? 0);
            }
            $openingPayment  = $openingLine ? floatval($openingLine['payment_amount'] ?? 0) : 0.0;
            $openingDiscount = $openingLine ? floatval($openingLine['discount_amount'] ?? 0) : 0.0;
            $totalAllocated = $billAllocated + $openingPayment + $openingDiscount;

            // Determine if receipt is settled: receipt amount equals total allocated amount
            $isReceiptSettled = abs($receiptAmount - $totalAllocated) < self::SETTLEMENT_TOLERANCE;

            // apply new lines (only process real purchase lines; synthetic opening rows are skipped)
            foreach ($linesToProcess as $line) {
                $purchase = Purchase::lockForUpdate()->findOrFail($line['purchase_id']);

                $billAmount = $purchase->total_amount;
                $paidBefore = $purchase->paid_amount;
                $balanceBefore = $purchase->due_amount;

                $paymentAmount = floatval($line['payment_amount']);
                $discountAmount = floatval($line['discount_amount'] ?? 0);

                // Always apply payments to purchase table regardless of settlement status
                $apply = true;

                // compute displayed final balance based on user-entered values
                $displayFinal = $billAmount - ($paidBefore + $paymentAmount + $discountAmount);

                // Always update purchase paid, discount and due amounts when there are payment/discount amounts
                if ($paymentAmount > 0 || $discountAmount > 0) {
                    $newPaid = $paidBefore + $paymentAmount;
                    $existingDiscount = floatval($purchase->discount_amount ?? 0);
                    $newDiscount = $existingDiscount + $discountAmount;
                    $newDue = $billAmount - ($newPaid + $newDiscount);
                } else {
                    $newPaid = $paidBefore;
                    $newDiscount = floatval($purchase->discount_amount ?? 0);
                    $newDue = $billAmount - ($newPaid + $newDiscount);
                }

                PurchasesReceiptLine::create([
                    'purchases_receipt_id' => $receipt->id,
                    'purchase_id' => $purchase->id,
                    'bill_ref' => $purchase->reference,
                    'bill_date' => $purchase->date,
                    'bill_amount' => $billAmount,
                    'paid_before' => $paidBefore,
                    'balance_before' => $balanceBefore,
                    'payment_amount' => $paymentAmount,
                    'discount_amount' => $discountAmount,
                    'final_balance' => $displayFinal,
                    'is_settled' => $isReceiptSettled, // settlement flag for tracking
                    'settled_at' => $isReceiptSettled ? now() : null,
                    'settled_by' => $isReceiptSettled ? auth()->id() : null,
                ]);

                // Always create purchase payment and update purchase when there are amounts
                if ($paymentAmount > 0) {
                    // use final receipt reference when updating existing receipt
                    $payRef = $receipt->reference ?? (self::TEMP_PAYMENT_PREFIX.$receipt->id);
                    PurchasePayment::create([
                        'date' => $data['date'],
                        'reference' => $payRef,
                        'amount' => $paymentAmount,
                        'purchase_id' => $purchase->id,
                        'payment_method' => $data['payment_mode'] ?? 'Cash'
                    ]);
                }

                // Always update purchase amounts (regardless of settlement status)
                if ($paymentAmount > 0 || $discountAmount > 0) {
                    $purchase->paid_amount = $newPaid;
                    $purchase->discount_amount = $newDiscount;
                    $purchase->due_amount = $newDue;
                    if ($purchase->due_amount == ($purchase->total_amount ?? 0)) {
                        $purchase->payment_status = 'Unpaid';
                    } elseif ($purchase->due_amount > 0) {
                        $purchase->payment_status = 'Partial';
                    } else {
                        $purchase->payment_status = 'Paid';
                    }
                    $purchase->save();

                    $appliedTotal += $paymentAmount;
                    $appliedDiscount += $discountAmount;
                }

                $total += $paymentAmount;
                $totalDiscount += $discountAmount;
            }

            // Ensure new referenced purchases are locked and unlock previously referenced
            // purchases which are no longer referenced by any receipt
            $newPurchaseIds = array_unique(array_map(function($l) { return intval($l['purchase_id'] ?? 0); }, $linesToProcess));
            // remove any zero/invalid ids
            $newPurchaseIds = array_values(array_filter($newPurchaseIds, function($v){ return $v > 0; }));

            foreach ($newPurchaseIds as $sid) {
                try {
                    $s = Purchase::find($sid);
                    if ($s) {
                        $s->locked = true;
                        $s->save();
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to lock purchase', ['purchase_id' => $sid, 'error' => $e->getMessage()]);
                }
            }

            foreach ($oldPurchaseIds as $oldId) {
                if (in_array(intval($oldId), $newPurchaseIds)) continue;
                // if there are other receipt lines referring to this purchase, keep it locked
                $exists = PurchasesReceiptLine::where('purchase_id', $oldId)->exists();
                if (! $exists) {
                    try {
                        $s = Purchase::find($oldId);
                        if ($s) {
                            $s->locked = false;
                            $s->save();
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to unlock purchase', ['purchase_id' => $oldId, 'error' => $e->getMessage()]);
                    }
                }
            }

            // batch update receipt header and ensure balance before stored
            $receipt->update([
                'date' => $data['date'],
                'particular' => $data['particular'],
                'payment_mode' => $data['payment_mode'] ?? $receipt->payment_mode,
                'total_amount' => $receiptAmount,
                'total_discount' => $totalDiscount,
                // prefer opening_balance from form, fall back to current supplier balance
                'supplier_balance_before' => (int) round(floatval($data['opening_balance'] ?? $supplier->open_balance ?? 0) * 100),
            ]);

            if ($supplier) {
                // Open Balance application — only the opening line reduces Open Balance.
                if ($openingLine) {
                    $openingApply = $openingPayment + $openingDiscount;
                    $balanceBefore = !is_null($receipt->supplier_balance_before) ? ($receipt->supplier_balance_before / 100) : floatval($supplier->open_balance ?? 0);
                    $receipt->update(['applied_to_supplier' => (int) round($openingPayment * 100)]);
                    try {
                        PurchasesReceiptLine::create([
                            'purchases_receipt_id' => $receipt->id,
                            'purchase_id' => null,
                            'bill_ref' => 'Opening Balance',
                            'bill_date' => null,
                            'bill_amount' => 0,
                            'paid_before' => 0,
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
                        $supplier->open_balance = max(0, floatval($supplier->open_balance ?? 0) - $openingApply);
                        $supplier->save();
                    }
                }

                // Excess reconciliation: remove the old receipt's excess, then add the new one.
                $oldExcess = max(0, floatval($oldTotal) - ($oldAppliedTotal + $oldOpeningPayment));
                if ($oldExcess > 0) {
                    $supplier->excess_amount = max(0, floatval($supplier->excess_amount ?? 0) - $oldExcess);
                    $supplier->save();
                }
                $newExcess = $receiptAmount - $totalAllocated;
                if ($newExcess > 0) {
                    $supplier->excess_amount = floatval($supplier->excess_amount ?? 0) + $newExcess;
                    $supplier->save();
                }
            }
        });

        toast('Purchases Receipt updated', 'success');

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['success' => true, 'redirect' => route('purchases-receipts.show', $id)]);
        }

        return redirect()->route('purchases-receipts.show', $id);
    }

    public function destroy($id) {
        abort_if(Gate::denies('delete_purchases_receipts'), 403);

        DB::transaction(function() use ($id) {
            $receipt = PurchasesReceipt::with('lines')->findOrFail($id);

            // reverse effects
            $appliedTotal = 0;
            $appliedDiscount = 0;
            $linelessAmount = 0.0;
            foreach ($receipt->lines as $line) {
                $oldPayment = floatval($line->payment_amount ?? 0);
                $oldDiscount = floatval($line->discount_amount ?? 0);
                $hadAmounts = ($oldPayment > 0) || ($oldDiscount > 0);
                if (!$hadAmounts) continue;

                // If this is a persisted lineless opening line, accumulate separately and skip purchase-level revert
                if (empty($line->purchase_id)) {
                    $linelessAmount += ($oldPayment + $oldDiscount);
                    continue;
                }

                $purchase = Purchase::lockForUpdate()->find($line->purchase_id);
                if ($purchase) {
                    // subtract only payment from paid_amount and only discount from discount_amount
                    $purchase->paid_amount = max(0, $purchase->paid_amount - $oldPayment);
                    $purchase->discount_amount = max(0, floatval($purchase->discount_amount ?? 0) - $oldDiscount);
                    $purchase->due_amount = $purchase->total_amount - ($purchase->paid_amount + $purchase->discount_amount);
                    if ($purchase->due_amount == ($purchase->total_amount ?? 0)) {
                        $purchase->payment_status = 'Unpaid';
                    } elseif ($purchase->due_amount > 0) {
                        $purchase->payment_status = 'Partial';
                    } else {
                        $purchase->payment_status = 'Paid';
                    }
                    $purchase->save();
                    // if no other receipts reference this purchase, unlock it
                    $other = PurchasesReceiptLine::where('purchase_id', $line->purchase_id)
                        ->where('purchases_receipt_id', '!=', $receipt->id)
                        ->exists();
                    if (! $other) {
                        try {
                            $purchase->locked = false;
                            $purchase->save();
                        } catch (\Exception $e) {
                            Log::warning('Failed to unlock purchase', ['purchase_id' => $line->purchase_id, 'error' => $e->getMessage()]);
                        }
                    }
                }

                PurchasePayment::whereIn('reference', [self::TEMP_PAYMENT_PREFIX.$receipt->id, $receipt->reference])->where('purchase_id', $line->purchase_id)->delete();

                $appliedTotal += $oldPayment;
                $appliedDiscount += $oldDiscount;
            }

            // update supplier only for applied amounts (revert previously applied amounts), include lineless applied if any
            $supplier = Supplier::lockForUpdate()->find($receipt->supplier_id);
            if ($supplier) {
                // If no persisted lineless line was present, fall back to legacy applied_to_supplier
                if ($linelessAmount <= 0) {
                    $linelessPaise = intval($receipt->applied_to_supplier ?? 0);
                    $linelessAmount = floatval($linelessPaise) / 100;
                }
                // Restore ONLY the Open Balance portion (bills are reverted by un-paying
                // their purchase above, which restores Bill Balance).
                $this->revertReceiptOnSupplier($supplier, $linelessAmount);

                // Subtract excess receipt amount that was previously added to excess_amount
                $receiptAmount = floatval($receipt->total_amount ?? 0);
                $excessAmount = $receiptAmount - (($appliedTotal + $appliedDiscount) + $linelessAmount);
                if ($excessAmount > 0) {
                    $supplier->excess_amount = max(0, floatval($supplier->excess_amount ?? 0) - $excessAmount);
                    $supplier->save();
                }
            }

            // delete lines and receipt
            PurchasesReceiptLine::where('purchases_receipt_id', $receipt->id)->delete();
            $receipt->delete();
        });

        toast('Purchases Receipt deleted', 'warning');
        return redirect()->route('purchases-receipts.index');
    }

    // purchases search API
    public function searchPurchases(Request $request) {
        $q = $request->get('q');
        $supplierId = $request->get('supplier_id');
        // support optional include_ids[] so the UI can ask the server to include
        // specific purchase ids even if they would normally be filtered out (e.g. due_amount == 0)
        $includeIds = $request->get('include_ids', []);
        $receiptId = $request->get('receipt_id');
        $receiptLinesMap = [];
        if ($receiptId) {
            // load lines for this receipt so we can return purchase amounts "before" this receipt's payments
            // Prefer stored per-line snapshot values (`paid_before` / `balance_before`) when available so
            // the Available Bills list reflects the state at the time this receipt was created.
            $lines = \Modules\PurchasesReceipt\Entities\PurchasesReceiptLine::where('purchases_receipt_id', $receiptId)->get();
            foreach ($lines as $l) {
                $sid = $l->purchase_id;
                if (!isset($receiptLinesMap[$sid])) $receiptLinesMap[$sid] = ['payment' => 0, 'discount' => 0, 'paid_before' => null, 'balance_before' => null];
                $receiptLinesMap[$sid]['payment'] += floatval($l->payment_amount ?? 0);
                $receiptLinesMap[$sid]['discount'] += floatval($l->discount_amount ?? 0);
                if (isset($l->paid_before)) $receiptLinesMap[$sid]['paid_before'] = floatval($l->paid_before);
                if (isset($l->balance_before)) $receiptLinesMap[$sid]['balance_before'] = floatval($l->balance_before);
            }
        }
        if (!is_array($includeIds)) {
            // support comma separated or single value
            $includeIds = $includeIds ? explode(',', $includeIds) : [];
        }

        $query = Purchase::query();
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($q) {
            $query->where(function($sub) use ($q) {
                $sub->where('reference', 'like', "%{$q}%")
                    ->orWhere('total_amount', 'like', "%{$q}%");
            });
        }

        // only bills with outstanding or include explicitly requested ids
        // Exclude Draft purchases from the available bills list (they should not be selectable)
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

        $results = $query->limit(20)->get()->map(function($p) use ($receiptLinesMap) {
            // compute amounts adjusted for an editing receipt (if present)
            $total = floatval($p->total_amount ?? 0);
            $paid = floatval($p->paid_amount ?? 0);
            $due = floatval($p->due_amount ?? ($total - $paid));

                if (!empty($receiptLinesMap) && isset($receiptLinesMap[$p->id])) {
                    $adj = $receiptLinesMap[$p->id];
                    // If the original line stored a snapshot of paid/balance, use it. Otherwise fall back to
                    // subtracting this receipt's payments from current purchase paid to approximate the
                    // "before" state. Note: paid excludes discounts, so subtract only payment here.
                    if (isset($adj['paid_before']) && $adj['paid_before'] !== null) {
                        $paidBefore = floatval($adj['paid_before']);
                        $dueBefore = floatval($adj['balance_before'] ?? ($total - $paidBefore));
                        $discountBefore = floatval($adj['discount'] ?? 0);
                    } else {
                        $paidBefore = max(0, $paid - floatval($adj['payment'] ?? 0));
                        $discountBefore = floatval($adj['discount'] ?? 0);
                        $dueBefore = $total - ($paidBefore + $discountBefore);
                    }
                } else {
                    $paidBefore = $paid;
                    $discountBefore = 0;
                    $dueBefore = $due;
                }

            // format as rupees strings with 2 decimals to keep client display consistent
            $fmt = function($v) { return number_format(floatval($v), 2, '.', ''); };

            return [
                'id' => $p->id,
                'text' => $p->reference . ' (' . $p->supplier_name . ')',
                'reference' => $p->reference,
                'date' => $p->date,
                // return numeric floats (rupees) so clients can format as needed
                'total_amount' => round($total, 2),
                'paid_amount' => round($paidBefore, 2),
                'discount_amount' => round(floatval($discountBefore ?? 0), 2),
                'due_amount' => round($dueBefore, 2)
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Toggle settlement for a given receipt line. When settling, create PurchasePayment and
     * update purchase paid/due and supplier balance. When unsetting, reverse the payment.
     */
    public function toggleSettle(Request $request, $receiptId, $lineId)
    {
        $receipt = PurchasesReceipt::with('lines')->findOrFail($receiptId);
        $line = PurchasesReceiptLine::where('purchases_receipt_id', $receiptId)->where('id', $lineId)->firstOrFail();

        // only applicable for cheque-mode receipts
        if (($receipt->payment_mode ?? '') !== 'Cheque') {
            return response()->json(['error' => 'Settlement is only applicable for cheque payment mode.'], 422);
        }

        DB::transaction(function() use ($receipt, $line) {
            if (empty($line->purchase_id)) {
                throw new \Exception('Cannot settle an opening balance line');
            }
            $purchase = Purchase::lockForUpdate()->findOrFail($line->purchase_id);
            $supplier = Supplier::lockForUpdate()->findOrFail($receipt->supplier_id);

            // if currently not settled, apply payment
                if (!($line->is_settled ?? false)) {
                    $amount = floatval($line->payment_amount ?? 0);
                    $discount = floatval($line->discount_amount ?? 0);
                    if ($amount > 0) {
                        PurchasePayment::create([
                            'date' => $receipt->date,
                            'reference' => $receipt->reference ?? (self::TEMP_PAYMENT_PREFIX.$receipt->id),
                            'amount' => $amount,
                            'purchase_id' => $purchase->id,
                            'payment_method' => $receipt->payment_mode ?? 'Cheque'
                        ]);
                    }

                    // update purchase: paid only includes actual payments; discount stored separately
                    $purchase->paid_amount = $purchase->paid_amount + $amount;
                    $purchase->discount_amount = floatval($purchase->discount_amount ?? 0) + $discount;
                    $purchase->due_amount = $purchase->total_amount - ($purchase->paid_amount + $purchase->discount_amount);
                    $purchase->save();

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

                    // remove associated purchase payments for this receipt & purchase
                    PurchasePayment::whereIn('reference', [self::TEMP_PAYMENT_PREFIX.$receipt->id, $receipt->reference])->where('purchase_id', $purchase->id)->delete();

                    // update purchase by subtracting payment and discount separately
                    $purchase->paid_amount = max(0, $purchase->paid_amount - $amount);
                    $purchase->discount_amount = max(0, floatval($purchase->discount_amount ?? 0) - $discount);
                    $purchase->due_amount = $purchase->total_amount - ($purchase->paid_amount + $purchase->discount_amount);
                    $purchase->save();

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
     * Apply a receipt amount to the supplier according to Option 2:
     * - Subtract up to `amount` from `excess_amount` (not below 0)
     * - Also subtract up to `amount` from `open_balance` (not below 0)
     * Note: total subtracted may be up to 2 * amount per user request.
     */
    protected function applyReceiptToSupplier(Supplier $supplier, float $amount)
    {
        $amount = floatval($amount);
        if ($amount <= 0) return;

        // subtract from excess up to amount
        $excess = floatval($supplier->excess_amount ?? 0);
        if ($excess > 0) {
            $appliedExcess = min($excess, $amount);
            $supplier->excess_amount = max(0, $excess - $appliedExcess);
        }

        // subtract from opening (open_balance) up to amount
        $opening = floatval($supplier->open_balance ?? 0);
        if ($opening > 0) {
            $appliedOpening = min($opening, $amount);
            $supplier->open_balance = max(0, $opening - $appliedOpening);
        }

        $supplier->save();
    }

    /**
     * Revert a previously applied receipt amount (Option 2 simple revert):
     * - Add the full `amount` back to `open_balance`.
     * - Does not attempt to restore `excess_amount` (no per-receipt split stored).
     */
    protected function revertReceiptOnSupplier(Supplier $supplier, float $amount)
    {
        $amount = floatval($amount);
        if ($amount <= 0) return;

        $supplier->open_balance = floatval($supplier->open_balance ?? 0) + $amount;
        $supplier->save();
    }
}