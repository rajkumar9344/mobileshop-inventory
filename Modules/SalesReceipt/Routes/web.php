<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web', 'auth']], function() {
    // Register specific routes before the resource so named segments like 'totals' or 'sales/search'
    // are not captured by the resource's {sales_receipt} parameter.
    Route::get('sales-receipts/sales/search', 'Modules\\SalesReceipt\\Http\\Controllers\\SalesReceiptController@searchSales')->name('salesreceipts.sales.search');
    Route::get('sales-receipts/customers', 'Modules\\SalesReceipt\\Http\\Controllers\\SalesReceiptController@customers')->name('salesreceipts.customers');
    // Check whether a customer has any not-settled receipts (blocks creating new ones)
    Route::get('sales-receipts/unsettled-check', 'Modules\\SalesReceipt\\Http\\Controllers\\SalesReceiptController@unsettledCheck')->name('salesreceipts.unsettled-check');
    Route::get('sales-receipts/totals', 'Modules\\SalesReceipt\\Http\\Controllers\\SalesReceiptController@totals')->name('salesreceipts.totals');
    // Toggle settlement for a receipt line (cheque clearance)
    Route::post('sales-receipts/{receipt}/lines/{line}/settle', 'Modules\SalesReceipt\Http\Controllers\SalesReceiptController@toggleSettle')->name('salesreceipts.lines.settle');
    // Read-only view route for receipts (shows the edit form in readonly mode)
    Route::get('sales-receipts/{receipt}/view', 'Modules\\SalesReceipt\\Http\\Controllers\\SalesReceiptController@view')->name('salesreceipts.view');
    Route::resource('sales-receipts', 'Modules\\SalesReceipt\\Http\\Controllers\\SalesReceiptController');
});
