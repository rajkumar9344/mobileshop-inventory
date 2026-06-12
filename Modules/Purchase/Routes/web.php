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

    //Generate PDF
    Route::get('/purchases/pdf/{id}', function ($id) {
        abort_if(\Illuminate\Support\Facades\Gate::denies('show_purchases'), 403);

        $purchase = \Modules\Purchase\Entities\Purchase::findOrFail($id);
        // Use find() for supplier so draft purchases without a supplier don't cause a 404
        $supplier = \Modules\People\Entities\Supplier::find($purchase->supplier_id);

        $pdf = app(\App\Services\PdfGenerator::class)->make('purchase::print', [
            'purchase' => $purchase,
            'supplier' => $supplier,
        ], ['paper' => 'a4']);

        return $pdf->stream('purchase-'. $purchase->reference .'.pdf');
    })->name('purchases.pdf');

    // Generate reference number
    Route::get('/purchases/generate-reference', function () {
        $lastPurchase = \Modules\Purchase\Entities\Purchase::latest('id')->first();
        $nextNumber = $lastPurchase ? $lastPurchase->id + 1 : 1;
        $reference = 'PU' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

        return response()->json(['reference' => $reference]);
    })->name('purchases.generate-reference');

    //Sales
    // View (readonly) - show the edit UI in readonly mode
    Route::get('purchases/{purchase}/view', 'PurchaseController@view')->name('purchases.view');
    // Reorder: create a new draft purchase pre-filled from an existing one
    Route::get('purchases/{purchase}/reorder', 'PurchaseController@reorder')->name('purchases.reorder');
    Route::resource('purchases', 'PurchaseController');
    Route::get('/purchases-totals', 'PurchaseController@totals')->name('purchases.totals');

    // Auto-save draft
    Route::post('purchases/auto-save-draft', 'PurchaseController@autoSaveDraft')->name('purchases.auto-save-draft');

    //Payments
    Route::get('/purchase-payments/{purchase_id}', 'PurchasePaymentsController@index')->name('purchase-payments.index');
    Route::get('/purchase-payments/{purchase_id}/create', 'PurchasePaymentsController@create')->name('purchase-payments.create');
    Route::post('/purchase-payments/store', 'PurchasePaymentsController@store')->name('purchase-payments.store');
    Route::get('/purchase-payments/{purchase_id}/edit/{purchasePayment}', 'PurchasePaymentsController@edit')->name('purchase-payments.edit');
    Route::patch('/purchase-payments/update/{purchasePayment}', 'PurchasePaymentsController@update')->name('purchase-payments.update');
    Route::delete('/purchase-payments/destroy/{purchasePayment}', 'PurchasePaymentsController@destroy')->name('purchase-payments.destroy');

    // Send Email for Purchase Bill
    Route::post('/purchases/send-email/{purchase}', 'SendPurchaseEmailController')->name('purchases.send-email');

});
