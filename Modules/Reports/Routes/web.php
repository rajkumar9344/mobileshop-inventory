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
    //Stock Report
    Route::get('/stock-report', 'ReportsController@stockReport')
        ->name('stock-report.index');
    //Sales Outstanding Report
    Route::get('/sales-outstanding-report', 'ReportsController@salesOutstandingReport')
        ->name('sales-outstanding-report.index');
    //Purchase Outstanding Report
    Route::get('/purchase-outstanding-report', 'ReportsController@purchaseOutstandingReport')
        ->name('purchase-outstanding-report.index');
    //Customers Payment Report (Sales Receipt based)
    Route::get('/customers-payment-report', 'ReportsController@customersPaymentReport')
        ->name('customers-payment-report.index');
    // GSTR Report
    Route::get('/gstr-report', 'ReportsController@gstrReport')
        ->name('gstr-report.index');
    // Daily Operations Report
    Route::get('/daily-operations-report', 'ReportsController@dailyOperationsReport')
        ->name('daily-operations-report.index');
});
