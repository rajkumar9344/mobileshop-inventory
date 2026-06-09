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

    //POS
    Route::get('/app/pos', 'PosController@index')->name('app.pos.index');
    Route::post('/app/pos', 'PosController@store')->name('app.pos.store');
    Route::get('/app/pos/check-credit-limit', 'PosController@checkCreditLimit')->name('app.pos.check-credit-limit');

    //Generate PDF
    Route::get('/sales/pdf/{id}', function ($id) {
        $sale = \Modules\Sale\Entities\Sale::findOrFail($id);
        // Use find() so missing customer (drafts) do not cause a 404
        $customer = \Modules\People\Entities\Customer::find($sale->customer_id);

        $pdf = app(\App\Services\PdfGenerator::class)->make('sale::print', [
            'sale' => $sale,
            'customer' => $customer,
        ], ['paper' => 'a4']);

        // sanitize filename: Symfony HttpFoundation disallows '/' and '\\' in disposition filename
        $safeRef = preg_replace('/[\/\\\\]+/', '-', $sale->reference);
        return $pdf->stream('sale-'. $safeRef .'.pdf');
    })->name('sales.pdf');

    Route::get('/sales/pos/pdf/{id}', function ($id) {
        $sale = \Modules\Sale\Entities\Sale::findOrFail($id);

        $pdf = app(\App\Services\PdfGenerator::class)->make('sale::print-pos', [
            'sale' => $sale,
        ], [
            'paper' => 'a7',
            'options' => [
                'margin-top' => 8,
                'margin-bottom' => 8,
                'margin-left' => 5,
                'margin-right' => 5,
            ],
        ]);

        // sanitize filename for POS print as well
        $safeRef = preg_replace('/[\/\\\\]+/', '-', $sale->reference);
        return $pdf->stream('sale-'. $safeRef .'.pdf');
    })->name('sales.pos.pdf');

    //Sales
    Route::get('sales/next-reference', 'SaleController@getNextReference')->name('sales.next-reference');
    Route::get('sales/totals', 'SaleController@totals')->name('sales.totals');
    Route::post('sales/auto-save-draft', 'SaleController@autoSaveDraft')->name('sales.auto-save-draft');
    Route::get('sales/check-credit-limit', 'SaleController@checkCreditLimit')->name('sales.check-credit-limit');
    Route::resource('sales', 'SaleController');
    // Read-only view page for sales (separate from printable invoice)
    Route::get('sales/{sale}/view', 'SaleController@view')->name('sales.view');

    //Payments
    Route::get('/sale-payments/{sale_id}', 'SalePaymentsController@index')->name('sale-payments.index');
    Route::get('/sale-payments/{sale_id}/create', 'SalePaymentsController@create')->name('sale-payments.create');
    Route::post('/sale-payments/store', 'SalePaymentsController@store')->name('sale-payments.store');
    Route::get('/sale-payments/{sale_id}/edit/{salePayment}', 'SalePaymentsController@edit')->name('sale-payments.edit');
    Route::patch('/sale-payments/update/{salePayment}', 'SalePaymentsController@update')->name('sale-payments.update');
    Route::delete('/sale-payments/destroy/{salePayment}', 'SalePaymentsController@destroy')->name('sale-payments.destroy');

    // Send Email
    Route::post('/sales/send-email/{sale}', 'SendSaleEmailController')->name('sales.send-email');
});
