<?php

namespace Modules\SalesReceipt\Listeners;

use Modules\Sale\Events\SaleFullyPaid;
use Modules\Sale\Entities\Sale;
use Modules\SalesReceipt\Entities\SalesReceipt;
use Modules\SalesReceipt\Entities\SalesReceiptLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Sale\Entities\SalePayment;
use Carbon\Carbon;

class CreateSalesReceiptForPaidSale
{
    /**
     * Handle the event.
     * Create a single sales receipt (with one line) for the fully-paid sale.
     * This is idempotent: if a receipt line for the sale already exists we skip.
     */
    public function handle(SaleFullyPaid $event)
    {
        Log::info('CreateSalesReceiptForPaidSale listener invoked', ['saleId' => $event->saleId]);

        $sale = Sale::find($event->saleId);
        if (! $sale) {
            Log::warning('CreateSalesReceiptForPaidSale: sale not found', ['saleId' => $event->saleId]);
            return;
        }

        // Idempotency: if we already created a receipt line for this sale, skip
        if (SalesReceiptLine::where('sale_id', $sale->id)->exists()) {
            return;
        }

        DB::transaction(function () use ($sale) {
            // Normalize sale date to Y-m-d using raw DB value to avoid accessor formatting
            $rawSaleDate = $sale->getOriginal('date') ?? null;
            try {
                $receiptDate = $rawSaleDate ? Carbon::parse($rawSaleDate)->format('Y-m-d') : now()->format('Y-m-d');
            } catch (\Exception $e) {
                $receiptDate = now()->format('Y-m-d');
            }

            $receipt = SalesReceipt::create([
                'date' => $receiptDate,
                // create first, then set standardized reference (RExxxxx) using id
                'customer_id' => $sale->customer_id,
                // 'particular' is the small description field in sales_receipts table
                'particular' => $sale->customer_name ?? ('Sale '.$sale->reference),
                'payment_mode' => $sale->payment_method ?? null,
                'total_amount' => $sale->paid_amount ?? 0,
                // Persist the sale-level discount on the receipt for clarity
                'total_discount' => $sale->discount_amount ?? 0,
            ]);

            // Generate standardized receipt reference (same format used in SalesReceiptController)
            $receipt->reference = 'RE' . str_pad($receipt->id, 5, '0', STR_PAD_LEFT);
            $receipt->save();

            // Use overall_net_rate (customer-payable) as the bill amount when available,
            // otherwise fall back to stored total_amount. Persist sale discount on the line,
            // but compute balances using post-discount base: (bill - sale_discount).
            $billAmt = $sale->overall_net_rate ?? ($sale->total_amount ?? 0);
            $saleLevelDiscount = floatval($sale->discount_amount ?? 0);
            $receivedBefore = 0; // treat all paid as part of this generated receipt
            $paymentAmt = floatval($sale->paid_amount ?? 0);
            // $balanceBefore = ($billAmt - $saleLevelDiscount) - $receivedBefore;
            $balanceBefore = $billAmt - $receivedBefore;

            $finalBal = ($billAmt - $saleLevelDiscount) - ($receivedBefore + $paymentAmt);

            SalesReceiptLine::create([
                'sales_receipt_id' => $receipt->id,
                'sale_id' => $sale->id,
                'bill_ref' => $sale->reference,
                'bill_date' => $sale->date ?? now()->format('Y-m-d'),
                'bill_amount' => $billAmt,
                // received_before and balance_before reflect post-discount balances
                'received_before' => $receivedBefore,
                'balance_before' => $balanceBefore,
                'payment_amount' => $paymentAmt,
                'discount_amount' => $sale->discount_amount ?? 0,
                'final_balance' => $finalBal,
                'is_settled' => true,
            ]);
            // mark sale as locked because there's now a receipt for it
            try {
                $sale->locked = true;
                $sale->save();
            } catch (\Exception $e) {
                // ignore
            }
            // Link existing sale payments (if any) to the created receipt for traceability
            SalePayment::where('sale_id', $sale->id)
                ->whereNull('sales_receipt_id')
                ->update(['sales_receipt_id' => $receipt->id]);
            Log::info('CreateSalesReceiptForPaidSale: created receipt for sale', ['sale_id' => $sale->id, 'receipt_id' => $receipt->id]);
        });
    }

    /**
     * Create a receipt and settled line for a single applied SalePayment.
     * Idempotent: skips if the payment already linked to a receipt.
     */
    public function createReceiptForPayment(SalePayment $salePayment)
    {
        if (! $salePayment) {
            return;
        }

        if (! empty($salePayment->sales_receipt_id)) {
            return;
        }

        $sale = $salePayment->sale;
        if (! $sale) {
            Log::warning('createReceiptForPayment: sale not found', ['sale_payment_id' => $salePayment->id]);
            return;
        }

        DB::transaction(function () use ($sale, $salePayment) {
            // Use raw date values (getOriginal) to avoid accessor formatting like '19 Nov, 2025'
            $rawPaymentDate = $salePayment->getOriginal('date') ?? null;
            $rawSaleDate = $sale->getOriginal('date') ?? null;
            try {
                if ($rawPaymentDate) {
                    $receiptDate = Carbon::parse($rawPaymentDate)->format('Y-m-d');
                } elseif ($rawSaleDate) {
                    $receiptDate = Carbon::parse($rawSaleDate)->format('Y-m-d');
                } else {
                    $receiptDate = now()->format('Y-m-d');
                }
            } catch (\Exception $e) {
                $receiptDate = now()->format('Y-m-d');
            }

            $receipt = SalesReceipt::create([
                'date' => $receiptDate,
                'customer_id' => $sale->customer_id,
                'particular' => $sale->customer_name ?? ('Sale '.$sale->reference),
                'payment_mode' => $salePayment->payment_method ?? $sale->payment_method ?? null,
                'total_amount' => $salePayment->amount ?? 0,
                'total_discount' => $sale->discount_amount ?? 0,
            ]);

            $receipt->reference = 'RE' . str_pad($receipt->id, 5, '0', STR_PAD_LEFT);
            $receipt->save();

            $paidBefore = max(0, ($sale->paid_amount ?? 0) - ($salePayment->amount ?? 0));
            $billAmt = $sale->overall_net_rate ?? ($sale->total_amount ?? 0);
            $saleLevelDiscount = floatval($sale->discount_amount ?? 0);
            // $balanceBefore = ($billAmt - $saleLevelDiscount) - $paidBefore;
            $balanceBefore = $billAmt - $paidBefore;

            $finalBal = ($billAmt - $saleLevelDiscount) - ($paidBefore + ($salePayment->amount ?? 0));

            SalesReceiptLine::create([
                'sales_receipt_id' => $receipt->id,
                'sale_id' => $sale->id,
                'bill_ref' => $sale->reference,
                'bill_date' => $sale->date ?? now()->format('Y-m-d'),
                'bill_amount' => $billAmt,
                'received_before' => $paidBefore,
                'balance_before' => $balanceBefore,
                'payment_amount' => $salePayment->amount ?? 0,
                'discount_amount' => $sale->discount_amount ?? 0,
                'final_balance' => $finalBal,
                'is_settled' => true,
                'settled_at' => now(),
                'settled_by' => auth()->id() ?? null,
            ]);

            $salePayment->sales_receipt_id = $receipt->id;
            $salePayment->save();

            try {
                $sale->locked = true;
                $sale->save();
            } catch (\Exception $e) {
                // ignore
            }
        });
    }
}
