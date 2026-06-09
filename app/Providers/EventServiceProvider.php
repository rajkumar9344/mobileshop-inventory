<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Modules\Sale\Events\SaleFullyPaid;
use Modules\SalesReceipt\Listeners\CreateSalesReceiptForPaidSale;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Events\MailFailed;
use App\Listeners\EmailLogListener;
use Modules\Product\Entities\Product;
use Modules\Product\Observers\ProductObserver;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        SaleFullyPaid::class => [
            CreateSalesReceiptForPaidSale::class,
        ],
        MessageSent::class => [
            EmailLogListener::class,
        ],
        MailFailed::class => [
            EmailLogListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
