<?php

use Illuminate\Support\Facades\Route;
use Modules\Bin\Http\Controllers\BinController;

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

Route::group(['middleware' => ['auth']], function () {
    Route::resource('bin', BinController::class)->names('bin');

    // Print Barcode for Bins
    Route::get('/bins/print-barcode', [\Modules\Bin\Http\Controllers\BarcodeController::class, 'printBarcode'])->name('bin.barcode.print');
    
    // Get bins for a specific rack (AJAX)
    Route::get('/get-bins', [BinController::class, 'getBins'])->name('get-bins');
});
