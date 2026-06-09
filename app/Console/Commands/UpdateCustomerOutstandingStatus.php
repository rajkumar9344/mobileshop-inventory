<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\People\Entities\Customer;
use Modules\Sale\Entities\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateCustomerOutstandingStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:customer-outstanding-status {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update customer outstanding status based on overdue days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $today = Carbon::today();

        $this->info($dryRun ? 'Running in dry-run mode. No changes will be made.' : 'Updating customer outstanding status...');

        // Get all active customers
        $customers = Customer::where('is_active', true)->get();
        $updatedCount = 0;

        foreach ($customers as $customer) {
            // Check if customer has overdue outstanding sales
            $hasOverdue = Sale::where('customer_id', $customer->id)
                ->whereIn('payment_status', ['Unpaid', 'Pending', 'Partial', 'Partially Paid'])
                ->where('status', '!=', 'Draft')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)
                ->exists();

            $newOutstanding = $hasOverdue ? 'Yes' : 'No';

            if ($customer->outstanding !== $newOutstanding) {
                if (!$dryRun) {
                    $customer->update(['outstanding' => $newOutstanding]);
                }
                $updatedCount++;
                $this->line("Customer {$customer->customer_name} (ID: {$customer->id}): outstanding set to {$newOutstanding}");
            }
        }

        $this->info("Process complete. {$updatedCount} customers updated." . ($dryRun ? ' (Dry run)' : ''));
    }
}
