<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
         \App\Console\Commands\CheckWkhtmltopdf::class,
         \App\Console\Commands\UpdateCustomerOutstandingStatus::class,
            \App\Console\Commands\RecomputePurchaseLines::class,
            \App\Console\Commands\BackfillAllProductCodes::class,
            \App\Console\Commands\BackfillPurchasePaymentsDiscounts::class,
             \App\Console\Commands\BackfillSalesPaymentsDiscounts::class,
             \App\Console\Commands\RenumberBillsByFinancialYear::class,
            // \App\Console\Commands\BackfillRateBeforeDiscount::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('update:customer-outstanding-status')->daily();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
    
}
