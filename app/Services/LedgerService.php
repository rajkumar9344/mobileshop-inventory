<?php

namespace App\Services;

use Modules\People\Entities\Customer;
use Modules\People\Entities\Supplier;
use Modules\Sale\Entities\Sale;
use Modules\SalesReceipt\Entities\SalesReceipt;
use Modules\Purchase\Entities\Purchase;
use Modules\PurchasesReceipt\Entities\PurchasesReceipt;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /**
     * Get financial year start and end dates.
     *
     * @param string|null $financialYear Format: "2025-2026"
     * @return array{start_date: string, end_date: string, financial_year: string}
     */
    public static function getFinancialYearDates(?string $financialYear = null): array
    {
        $year = (int) date('Y');
        $month = (int) date('m');

        if ($financialYear) {
            $parts = explode('-', $financialYear);
            $fromYear = (int) trim($parts[0]);
            return [
                'start_date' => "{$fromYear}-04-01",
                'end_date' => ($fromYear + 1) . '-03-31',
                'financial_year' => $financialYear,
            ];
        }

        // Current FY based on today's date
        $fyStartYear = ($month >= 4) ? $year : ($year - 1);
        return [
            'start_date' => "{$fyStartYear}-04-01",
            'end_date' => ($fyStartYear + 1) . '-03-31',
            'financial_year' => "{$fyStartYear}-" . ($fyStartYear + 1),
        ];
    }

    /**
     * Build ledger data for customers.
     *
     * @param array $filters ['customer_id' => int|null, 'start_date' => string, 'end_date' => string]
     * @return array
     */
    public static function buildLedgerData(array $filters): array
    {
        $start = $filters['start_date'];
        $end = $filters['end_date'];
        $customerId = $filters['customer_id'] ?? null;

        $customersQuery = Customer::query()->orderBy('customer_name');
        if ($customerId) {
            $customersQuery->where('id', $customerId);
        }
        $customers = $customersQuery->get();

        $result = [];
        foreach ($customers as $customer) {
            $result[] = self::buildCustomerLedger($customer, $start, $end);
        }

        return $result;
    }

    /**
     * Build ledger for a single customer.
     */
    protected static function buildCustomerLedger(Customer $customer, string $start, string $end): array
    {
        // Opening balance derived from transactions before the report period.
        // NOTE: We do NOT use customer.opening_balance because the system updates
        // that field dynamically with each sale/receipt (it's a "current balance").
        // Using it would double-count sales that already appear in the ledger.
        // sum() bypasses model accessors — DB stores values as paise (×100), so divide by 100.
        $salesBefore = Sale::where('customer_id', $customer->id)
            ->where('status', '!=', 'Draft')
            ->whereDate('date', '<', $start)
            ->sum('overall_net_rate') / 100;

        $receiptsBefore = SalesReceipt::where('customer_id', $customer->id)
            ->whereDate('date', '<', $start)
            ->get()
            ->map(function($r) {
                // Keep opening consistent with in-period receipt amount logic.
                $lineSumPaise = (int) $r->lines()->sum(DB::raw('COALESCE(payment_amount,0) + COALESCE(discount_amount,0)'));
                $lineSum = $lineSumPaise / 100;

                if ($lineSum > 0) {
                    return $lineSum;
                }

                if (isset($r->applied_to_customer) && $r->applied_to_customer !== null) {
                    return $r->applied_to_customer / 100; // stored in paise
                }

                return $r->total_amount ?? 0; // accessor returns rupees
            })
            ->filter(function($amount) {
                return (float) $amount > 0;
            })
            ->sum();

        // Keep opening continuity with ledger closing logic:
        // receipts applied to opening balance (sale_id = null) are mirrored as debit
        // rows in the report, so include the same amount in pre-period opening.
        $openingAppliedFromLinesBeforePaise = (int) DB::table('sales_receipt_lines')
            ->join('sales_receipts', 'sales_receipt_lines.sales_receipt_id', '=', 'sales_receipts.id')
            ->where('sales_receipts.customer_id', $customer->id)
            ->whereDate('sales_receipts.date', '<', $start)
            ->whereNull('sales_receipt_lines.sale_id')
            ->sum(DB::raw('COALESCE(sales_receipt_lines.payment_amount,0) + COALESCE(sales_receipt_lines.discount_amount,0)'));

        // Backward compatibility for older lineless opening receipts.
        $openingAppliedLegacyBeforePaise = (int) SalesReceipt::where('customer_id', $customer->id)
            ->whereDate('date', '<', $start)
            ->whereNotNull('applied_to_customer')
            ->whereDoesntHave('lines')
            ->sum('applied_to_customer');

        $openingAppliedBefore = ($openingAppliedFromLinesBeforePaise + $openingAppliedLegacyBeforePaise) / 100;

        $opening = $salesBefore + $openingAppliedBefore - $receiptsBefore;

        // Fetch transactions in period
        $sales = Sale::where('customer_id', $customer->id)
            ->where('status', '!=', 'Draft')
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->get()
            ->map(fn($s) => [
                'type' => 'sale',
                'date' => $s->date,
                'reference' => $s->reference,
                'payment_mode' => $s->payment_method ?? '',
                'amount' => $s->overall_net_rate ?? $s->overall_amount ?? $s->total_amount ?? 0,
            ]);

        $receipts = SalesReceipt::where('customer_id', $customer->id)
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->get()
            ->map(function($r) {
                // Sum payment+discount from lines via a DB query to avoid
                // lazy-loading relations (application disables lazy loading).
                $lineSumPaise = (int) $r->lines()->sum(DB::raw('COALESCE(payment_amount,0) + COALESCE(discount_amount,0)'));
                $lineSum = $lineSumPaise / 100;

                // Portion of this receipt that was applied directly to opening balance
                // (synthetic line with sale_id = null).
                $openingLinePaise = (int) $r->lines()
                    ->whereNull('sale_id')
                    ->sum(DB::raw('COALESCE(payment_amount,0) + COALESCE(discount_amount,0)'));
                $openingApplied = $openingLinePaise / 100;

                // Backward compatibility: older lineless opening receipts may not have
                // persisted lines, but do store applied_to_customer.
                if ($openingApplied <= 0 && $lineSumPaise <= 0 && isset($r->applied_to_customer) && $r->applied_to_customer !== null) {
                    $openingApplied = $r->applied_to_customer / 100;
                }

                if ($lineSum > 0) {
                    $amount = $lineSum;
                } else {
                    if (isset($r->applied_to_customer) && $r->applied_to_customer !== null) {
                        $amount = $r->applied_to_customer / 100; // stored in paise
                    } else {
                        $amount = $r->total_amount ?? 0; // accessor returns rupees
                    }
                }

                return [
                    'type' => 'receipt',
                    'date' => $r->date,
                    'reference' => $r->reference ?? $r->id,
                    'payment_mode' => $r->payment_mode ?? '',
                    'amount' => $amount,
                    'opening_applied' => max(0, $openingApplied),
                ];
            });

        // Exclude receipts with zero amount (no settlement/payment)
        $receipts = $receipts->filter(function($t){
            return ($t['amount'] ?? 0) > 0;
        })->values();

        // Merge and sort by date
        $transactions = $sales->concat($receipts)->sortBy('date')->values();

        // Build transaction rows with debit/credit
        $txnDebit = 0;
        $txnCredit = 0;
        $txRows = [];

        foreach ($transactions as $t) {
            if ($t['type'] === 'sale') {
                $debit = $t['amount'];
                $credit = 0;
                $txnDebit += $debit;
            } else {
                $debit = 0;
                $credit = $t['amount'];
                $txnCredit += $credit;
            }

            $txRows[] = [
                'type' => $t['type'],
                'date' => $t['date'],
                'reference' => $t['reference'],
                'payment_mode' => $t['payment_mode'],
                'debit' => $debit,
                'credit' => $credit,
            ];

            // If a receipt is applied to opening balance (no sale bill), add a
            // mirrored debit entry so ledger totals remain balanced.
            if ($t['type'] === 'receipt' && !empty($t['opening_applied']) && $t['opening_applied'] > 0) {
                $openingDebit = (float) $t['opening_applied'];
                $txnDebit += $openingDebit;

                $txRows[] = [
                    'type' => 'opening balance adjustment',
                    'date' => $t['date'],
                    'reference' => $t['reference'],
                    'payment_mode' => '',
                    'debit' => $openingDebit,
                    'credit' => 0,
                ];
            }
        }

        // Pre-calculate totals for views.
        // Positive opening means customer owes us (debit side),
        // negative opening means customer has advance (credit side).
        $openingDebit = $opening > 0 ? $opening : 0;
        $openingCredit = $opening < 0 ? abs($opening) : 0;

        $totalDebit = $openingDebit + $txnDebit;
        $totalCredit = $openingCredit + $txnCredit;
        $closingBalance = abs($totalDebit - $totalCredit);
        $balancedTotal = max($totalDebit, $totalCredit);
        $closingInCredit = $totalDebit > $totalCredit;

        return [
            'customer' => $customer,
            'opening' => $opening,
            'transactions' => $txRows,
            'closing' => $totalDebit - $totalCredit, // Positive = customer owes, Negative = overpaid
            // Pre-computed summary for views
            'summary' => [
                'opening_debit' => $openingDebit,
                'opening_credit' => $openingCredit,
                'txn_debit' => $txnDebit,
                'txn_credit' => $txnCredit,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'closing_balance' => $closingBalance,
                'balanced_total' => $balancedTotal,
                'closing_in_credit' => $closingInCredit,
            ],
        ];
    }

    // ==================== SUPPLIER LEDGER ====================

    /**
     * Build ledger data for suppliers.
     *
     * @param array $filters ['supplier_id' => int|null, 'start_date' => string, 'end_date' => string]
     * @return array
     */
    public static function buildSupplierLedgerData(array $filters): array
    {
        $start = $filters['start_date'];
        $end = $filters['end_date'];
        $supplierId = $filters['supplier_id'] ?? null;

        $suppliersQuery = Supplier::query()->orderBy('supplier_name');
        if ($supplierId) {
            $suppliersQuery->where('id', $supplierId);
        }
        $suppliers = $suppliersQuery->get();

        $result = [];
        foreach ($suppliers as $supplier) {
            $result[] = self::buildSupplierLedger($supplier, $start, $end);
        }

        return $result;
    }

    /**
     * Build ledger for a single supplier.
     * 
     * Supplier ledger logic (opposite of customer):
     * - Opening Balance: derived from purchases before - payments before
     * - Purchases = Credit (we owe them more)
     * - Payments = Debit (we paid them, reducing what we owe)
     */
    protected static function buildSupplierLedger(Supplier $supplier, string $start, string $end): array
    {
        // Opening balance derived from transactions before the report period.
        // NOTE: We do NOT use supplier.open_balance because the system updates
        // that field dynamically with each purchase/payment (it's a "current balance").
        // Using it would double-count purchases that already appear in the ledger.
        // sum() bypasses model accessors — DB stores values as paise (×100), so divide by 100.
        $purchasesBefore = Purchase::where('supplier_id', $supplier->id)
            ->where('status', '!=', 'Draft')
            ->whereDate('date', '<', $start)
            ->sum('total_amount') / 100;

        $paymentsBefore = PurchasesReceipt::where('supplier_id', $supplier->id)
            ->whereDate('date', '<', $start)
            ->get()
            ->map(function($r) {
                // Keep opening consistent with in-period payment amount logic.
                $lineSumPaise = (int) $r->lines()->sum(DB::raw('COALESCE(payment_amount,0) + COALESCE(discount_amount,0)'));
                $lineSum = $lineSumPaise / 100;

                if ($lineSum > 0) {
                    return $lineSum;
                }

                if (isset($r->applied_to_supplier) && $r->applied_to_supplier !== null) {
                    return $r->applied_to_supplier / 100; // stored in paise
                }

                return $r->total_amount ?? 0; // accessor returns rupees
            })
            ->filter(function($amount) {
                return (float) $amount > 0;
            })
            ->sum();

        $opening = $purchasesBefore - $paymentsBefore;

        // Fetch transactions in period
        $purchases = Purchase::where('supplier_id', $supplier->id)
            ->where('status', '!=', 'Draft')
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->get()
            ->map(fn($p) => [
                'type' => 'purchase',
                'date' => $p->date,
                'reference' => $p->reference,
                'payment_mode' => $p->payment_method ?? '',
                'amount' => $p->total_amount ?? 0,
            ]);

        $payments = PurchasesReceipt::where('supplier_id', $supplier->id)
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->get()
            ->map(function($r) {
                // Sum line payments+discounts via DB to avoid lazy-loading.
                $lineSumPaise = (int) $r->lines()->sum(DB::raw('COALESCE(payment_amount,0) + COALESCE(discount_amount,0)'));
                $lineSum = $lineSumPaise / 100;

                if ($lineSum > 0) {
                    $amount = $lineSum;
                } else {
                    if (isset($r->applied_to_supplier) && $r->applied_to_supplier !== null) {
                        $amount = $r->applied_to_supplier / 100;
                    } else {
                        $amount = $r->total_amount ?? 0;
                    }
                }

                return [
                    'type' => 'payment',
                    'date' => $r->date,
                    'reference' => $r->reference ?? $r->id,
                    'payment_mode' => $r->payment_mode ?? '',
                    'amount' => $amount,
                ];
            });

        // Exclude payments with zero amount (empty receipts)
        $payments = $payments->filter(function($t){
            return ($t['amount'] ?? 0) > 0;
        })->values();

        // Merge and sort by date
        $transactions = $purchases->concat($payments)->sortBy('date')->values();

        // Build transaction rows with debit/credit
        // For supplier: Payment = Debit, Purchase = Credit
        $txnDebit = 0;
        $txnCredit = 0;
        $txRows = [];

        foreach ($transactions as $t) {
            if ($t['type'] === 'purchase') {
                // Purchase increases what we owe (Credit)
                $debit = 0;
                $credit = $t['amount'];
                $txnCredit += $credit;
            } else {
                // Payment decreases what we owe (Debit)
                $debit = $t['amount'];
                $credit = 0;
                $txnDebit += $debit;
            }

            $txRows[] = [
                'type' => $t['type'],
                'date' => $t['date'],
                'reference' => $t['reference'],
                'payment_mode' => $t['payment_mode'],
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        // Pre-calculate totals for views
        // Opening goes to Credit (we owe them from before)
        $totalDebit = $txnDebit;
        $totalCredit = $opening + $txnCredit;
        $closingBalance = abs($totalCredit - $totalDebit);
        $balancedTotal = max($totalDebit, $totalCredit);
        $closingInDebit = $totalCredit > $totalDebit; // If we owe more, closing goes to Debit

        return [
            'supplier' => $supplier,
            'opening' => $opening,
            'transactions' => $txRows,
            'closing' => $totalCredit - $totalDebit, // Positive = we owe, Negative = overpaid
            // Pre-computed summary for views
            'summary' => [
                'txn_debit' => $txnDebit,
                'txn_credit' => $txnCredit,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'closing_balance' => $closingBalance,
                'balanced_total' => $balancedTotal,
                'closing_in_debit' => $closingInDebit,
            ],
        ];
    }
}
