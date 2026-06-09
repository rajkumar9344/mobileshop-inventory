<?php
use Illuminate\Support\Facades\Route;
use Modules\Rack\Http\Controllers\RackController;
use Modules\Rack\Http\Controllers\BarcodeController;

Route::group(['middleware' => ['auth']], function () {
    // List all racks
    Route::get('rack', [RackController::class, 'index'])->name('rack.index');

    // Show create rack form
    Route::get('rack/create', [RackController::class, 'create'])->name('rack.create');

    // Store new rack (POST)
    Route::post('rack', [RackController::class, 'store'])->name('rack.store');

    // Show a single rack
    Route::get('rack/{rack}', [RackController::class, 'show'])->name('rack.show');

    // Show edit rack form
    Route::get('rack/{rack}/edit', [RackController::class, 'edit'])->name('rack.edit');

    // Update rack (PUT/PATCH)
    Route::put('rack/{rack}', [RackController::class, 'update'])->name('rack.update');
    Route::patch('rack/{rack}', [RackController::class, 'update']);

    // Delete rack
    Route::delete('rack/{rack}', [RackController::class, 'destroy'])->name('rack.destroy');

    // Print Barcode for Racks
    Route::get('/racks/print-barcode', [BarcodeController::class, 'printBarcode'])->name('rack.barcode.print');
});