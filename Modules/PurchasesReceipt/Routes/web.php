<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web', 'auth']], function() {
    // Register specific routes before the resource so named segments like 'totals' or 'purchases/search'
    // are not captured by the resource's {purchases_receipt} parameter.
    Route::get('purchases-receipts/purchases/search', 'Modules\\PurchasesReceipt\\Http\\Controllers\\PurchasesReceiptController@searchPurchases')->name('purchasesreceipts.purchases.search');
    Route::get('purchases-receipts/suppliers', 'Modules\\PurchasesReceipt\\Http\\Controllers\\PurchasesReceiptController@suppliers')->name('purchasesreceipts.suppliers');
    Route::get('purchases-receipts/totals', 'Modules\\PurchasesReceipt\\Http\\Controllers\\PurchasesReceiptController@totals')->name('purchasesreceipts.totals');
    // Toggle settlement for a receipt line (cheque clearance)
    Route::post('purchases-receipts/{receipt}/lines/{line}/settle', 'Modules\PurchasesReceipt\Http\Controllers\PurchasesReceiptController@toggleSettle')->name('purchasesreceipts.lines.settle');
    // Readonly view (reuse edit template but readonly)
    Route::get('purchases-receipts/{receipt}/view', 'Modules\\PurchasesReceipt\\Http\\Controllers\\PurchasesReceiptController@view')->name('purchasesreceipts.view');
    Route::resource('purchases-receipts', 'Modules\\PurchasesReceipt\\Http\\Controllers\\PurchasesReceiptController');
});