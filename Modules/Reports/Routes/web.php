<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['middleware' => 'auth'], function () {
    //Reorder Report (formerly Stock Report)
    Route::get('/reorder-report', 'ReportsController@reorderReport')
        ->name('reorder-report.index');
    //Current Stock Report
    Route::get('/current-stock-report', 'ReportsController@currentStockReport')
        ->name('current-stock-report.index');
    //Customers Payment Report (Sales Receipt based)
    Route::get('/customers-payment-report', 'ReportsController@customersPaymentReport')
        ->name('customers-payment-report.index');
    // GSTR Report
    Route::get('/gstr-report', 'ReportsController@gstrReport')
        ->name('gstr-report.index');
    // Daily Operations Report
    Route::get('/daily-operations-report', 'ReportsController@dailyOperationsReport')
        ->name('daily-operations-report.index');
    // Profit / Loss Report
    Route::get('/profit-loss-report', 'ReportsController@profitLossReport')
        ->name('profit-loss-report.index');
});
