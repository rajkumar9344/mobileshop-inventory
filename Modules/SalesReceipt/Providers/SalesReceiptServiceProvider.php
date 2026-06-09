<?php

namespace Modules\SalesReceipt\Providers;

use Illuminate\Support\ServiceProvider;

class SalesReceiptServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register module RouteServiceProvider (keeps route loading consistent with other modules)
        $this->app->register(\Modules\SalesReceipt\Providers\RouteServiceProvider::class);
    }

    public function boot()
    {
        // load migrations, routes, views if module uses them
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'salesreceipt');
    }
}
