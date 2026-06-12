<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Modules\Product\Http\Controllers\BarcodeController;
use App\Http\Controllers\HomeController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('auth.login');
})->middleware('guest');

Auth::routes(['register' => false]);

Route::group(['middleware' => 'auth'], function () {
    Route::get('/home', [HomeController::class, 'index'])
        ->name('home');

    Route::get('/download-barcode-pdf', [BarcodeController::class, 'downloadBarcodePdf'])->name('barcode.download.pdf');

    // Reports exports
    Route::get('reports/reorder-pdf', [\App\Http\Controllers\ReportsController::class, 'reorderPdf'])
        ->name('reports.reorder-pdf');
    Route::get('reports/reorder-excel', [\App\Http\Controllers\ReportsController::class, 'reorderExcel'])
        ->name('reports.reorder-excel');

    // Profit / Loss Report exports
    Route::get('reports/profit-loss-pdf', [\App\Http\Controllers\ReportsController::class, 'profitLossPdf'])
        ->name('reports.profit-loss-pdf');
    Route::get('reports/profit-loss-excel', [\App\Http\Controllers\ReportsController::class, 'profitLossExcel'])
        ->name('reports.profit-loss-excel');

    // Customers Payment Report exports
    Route::get('reports/customers-payment-pdf', [\App\Http\Controllers\ReportsController::class, 'customersPaymentPdf'])
        ->name('reports.customers-payment-pdf');
    Route::get('reports/customers-payment-excel', [\App\Http\Controllers\ReportsController::class, 'customersPaymentExcel'])
        ->name('reports.customers-payment-excel');
    Route::get('reports/customers-payment-print', [\App\Http\Controllers\ReportsController::class, 'customersPaymentPrint'])
        ->name('reports.customers-payment-print');

    // GSTR print
    Route::get('reports/gstr-print', [\App\Http\Controllers\ReportsController::class, 'gstrPrint'])
        ->name('reports.gstr-print');

    // Reorder (Purchase Order) print
    Route::get('reports/reorder-print', [\App\Http\Controllers\ReportsController::class, 'reorderPrint'])
        ->name('reports.reorder-print');

    // Ledger Report view and exports
    Route::view('reports/ledger', 'reports.ledger')->name('reports.ledger');
    Route::get('reports/ledger-pdf', [\App\Http\Controllers\ReportsController::class, 'ledgerPdf'])->name('reports.ledger-pdf');
    Route::post('reports/ledger-send-email', [\App\Http\Controllers\ReportsController::class, 'ledgerSendEmail'])->name('reports.ledger-send-email');
    Route::get('reports/ledger-excel', [\App\Http\Controllers\ReportsController::class, 'ledgerExcel'])->name('reports.ledger-excel');

    // Supplier Ledger Report view and exports
    Route::view('reports/supplier-ledger', 'reports.supplier-ledger')->name('reports.supplier-ledger');
    Route::get('reports/supplier-ledger-pdf', [\App\Http\Controllers\ReportsController::class, 'supplierLedgerPdf'])->name('reports.supplier-ledger-pdf');
    Route::post('reports/supplier-ledger-send-email', [\App\Http\Controllers\ReportsController::class, 'supplierLedgerSendEmail'])->name('reports.supplier-ledger-send-email');
    Route::get('reports/supplier-ledger-excel', [\App\Http\Controllers\ReportsController::class, 'supplierLedgerExcel'])->name('reports.supplier-ledger-excel');
    Route::get('reports/ledger-print', [\App\Http\Controllers\ReportsController::class, 'ledgerPrint'])
        ->name('reports.ledger-print');
    Route::get('reports/supplier-ledger-print', [\App\Http\Controllers\ReportsController::class, 'supplierLedgerPrint'])
        ->name('reports.supplier-ledger-print');

    // GSTR Report exports
    Route::get('reports/gstr-pdf', [\App\Http\Controllers\ReportsController::class, 'gstrPdf'])
        ->name('reports.gstr-pdf');
    Route::get('reports/gstr-excel', [\App\Http\Controllers\ReportsController::class, 'gstrExcel'])
        ->name('reports.gstr-excel');

    // Daily Operations Report exports
    Route::get('reports/daily-operations-pdf', [\App\Http\Controllers\ReportsController::class, 'dailyOperationsPdf'])
        ->name('reports.daily-operations-pdf');
    Route::get('reports/daily-operations-excel', [\App\Http\Controllers\ReportsController::class, 'dailyOperationsExcel'])
        ->name('reports.daily-operations-excel');
    Route::get('reports/daily-operations-print', [\App\Http\Controllers\ReportsController::class, 'dailyOperationsPrint'])
        ->name('reports.daily-operations-print');

    // Daily Operations Monthwise Summary exports
    Route::get('reports/daily-operations-monthwise-pdf', [\App\Http\Controllers\ReportsController::class, 'dailyOperationsMonthwisePdf'])
        ->name('reports.daily-operations-monthwise-pdf');
    Route::get('reports/daily-operations-monthwise-excel', [\App\Http\Controllers\ReportsController::class, 'dailyOperationsMonthwiseExcel'])
        ->name('reports.daily-operations-monthwise-excel');

});

