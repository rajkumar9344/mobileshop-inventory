<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Inserts ~1000 dummy SalesReceipt + SalesReceiptLine records for testing
 * the Customers Payment Report with a large dataset.
 *
 * Run with: php artisan db:seed --class=DummyCustomersPaymentSeeder
 * Remove with: php artisan db:seed --class=DummyCustomersPaymentSeeder --rollback
 *   (or just delete records where reference LIKE 'DUMMY%')
 */
class DummyCustomersPaymentSeeder extends Seeder
{
    // Existing customer IDs
    private array $customerIds = [3, 1, 5, 13, 14, 15, 18, 4, 12, 9, 2, 10, 7, 16, 17, 11, 8, 6, 19];

    // Existing sale IDs
    private array $saleIds = [
        36, 37, 38, 39, 40, 41, 42, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57,
        58, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 73, 74, 75, 76, 77, 78, 80,
        81, 82, 83, 84, 86, 87, 88, 89, 90, 91, 92, 93, 94, 95, 96, 97, 98, 99, 100,
        102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 116, 117, 118, 119, 120, 121,
        122, 123, 124, 125, 126, 127, 128, 129, 130, 134, 135, 136, 137
    ];

    private array $paymentModes = ['Cash', 'UPI Payment', 'Bank Transfer', 'Cheque', 'Card'];

    private array $particulars = ['COUNTER SALES', 'CREDIT PAYMENT', 'ADVANCE PAYMENT', 'PARTIAL PAYMENT'];

    public function run(): void
    {
        $target    = 2000;
        $existing  = DB::table('sales_receipt_lines')->count();
        $toInsert  = max(0, $target - $existing);

        if ($toInsert === 0) {
            $this->command->info("Already have {$existing} receipt lines — nothing to insert.");
            return;
        }

        $this->command->info("Inserting {$toInsert} dummy receipts + lines...");

        // Find the next dummy reference number from existing DUMMY-RE##### entries
        $maxRef = DB::table('sales_receipts')
            ->where('reference', 'like', 'DUMMY-%')
            ->max('reference') ?? 'DUMMY-RE00000';
        preg_match('/(\d+)$/', $maxRef, $m);
        $nextRefNum = (int) ($m[1] ?? 0) + 1;

        $receiptRows = [];
        $lineRows    = [];
        $now         = Carbon::now()->toDateTimeString();

        for ($i = 0; $i < $toInsert; $i++) {
            $refNum      = $nextRefNum + $i;
            $reference   = 'DUMMY-RE' . str_pad($refNum, 5, '0', STR_PAD_LEFT);
            $customerId  = $this->customerIds[array_rand($this->customerIds)];
            $paymentMode = $this->paymentModes[array_rand($this->paymentModes)];
            $particular  = $this->particulars[array_rand($this->particulars)];

            // Random date within last 30 days (to match the default filter in the report)
            $daysAgo = rand(0, 29);
            $date    = Carbon::now()->subDays($daysAgo)->format('Y-m-d');

            // Bill amount in paise: between ₹100 and ₹50,000
            $billAmountPaise    = rand(10000, 5000000);
            $receivedBefore     = rand(0, (int)($billAmountPaise * 0.5));
            $balanceBefore      = $billAmountPaise - $receivedBefore;
            $paymentAmountPaise = rand((int)($balanceBefore * 0.1), $balanceBefore);
            $discountPaise      = rand(0, (int)($balanceBefore * 0.05));
            $finalBalance       = $balanceBefore - $paymentAmountPaise - $discountPaise;
            $isSettled          = $finalBalance <= 0 ? 1 : 0;

            // Pick a random sale
            $saleId   = $this->saleIds[array_rand($this->saleIds)];
            $billRef  = 'SSA/' . str_pad($saleId, 5, '0', STR_PAD_LEFT) . '/2025-2026';

            $receiptRows[] = [
                'reference'              => $reference,
                'date'                   => $date,
                'customer_id'            => $customerId,
                'particular'             => $particular,
                'payment_mode'           => $paymentMode,
                'total_amount'           => $paymentAmountPaise,
                'customer_balance_before'=> null,
                'applied_to_customer'    => null,
                'total_discount'         => $discountPaise,
                'created_by'             => null,
                'created_at'             => $date . ' 10:00:00',
                'updated_at'             => $date . ' 10:00:00',
            ];

            $lineRows[] = [
                'sales_receipt_id' => null, // filled after batch insert
                'sale_id'          => $saleId,
                'bill_ref'         => $billRef,
                'bill_date'        => $date,
                'bill_amount'      => $billAmountPaise,
                'received_before'  => $receivedBefore,
                'balance_before'   => $balanceBefore,
                'payment_amount'   => $paymentAmountPaise,
                'discount_amount'  => $discountPaise,
                'final_balance'    => max(0, $finalBalance),
                'is_settled'       => $isSettled,
                'settled_at'       => null,
                'settled_by'       => null,
                'created_at'       => $date . ' 10:00:00',
                'updated_at'       => $date . ' 10:00:00',
            ];
        }

        // Insert in chunks of 200 for performance
        $chunkSize = 200;
        $chunks    = array_chunk($receiptRows, $chunkSize);

        $lineIndex = 0;
        foreach ($chunks as $chunk) {
            // Insert receipts in this chunk
            DB::table('sales_receipts')->insert($chunk);

            // Get the IDs that were just inserted (last N rows by id)
            $insertedCount = count($chunk);
            $insertedIds   = DB::table('sales_receipts')
                ->orderByDesc('id')
                ->take($insertedCount)
                ->pluck('id')
                ->reverse()
                ->values()
                ->toArray();

            // Build corresponding line rows with correct receipt IDs
            $lineChunk = [];
            foreach ($insertedIds as $k => $receiptId) {
                $line                    = $lineRows[$lineIndex];
                $line['sales_receipt_id'] = $receiptId;
                $lineChunk[]              = $line;
                $lineIndex++;
            }

            DB::table('sales_receipt_lines')->insert($lineChunk);
        }

        $total = DB::table('sales_receipt_lines')->count();
        $this->command->info("Done. Total receipt lines now: {$total}");
    }
}
