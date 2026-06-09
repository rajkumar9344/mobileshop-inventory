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
    Route::get('/quotations/pdf/{id}', function ($id) {
        $quotation = \Modules\Quotation\Entities\Quotation::findOrFail($id);

        // when customer_type is 'new' the record won't have a customer_id,
        // so we skip the lookup and allow the view to render using the
        // contact_ fields defined on the quotation itself.
        if ($quotation->customer_id) {
            $customer = \Modules\People\Entities\Customer::find($quotation->customer_id);
        } else {
            $customer = null;
        }

        $pdf = app(\App\Services\PdfGenerator::class)->make('quotation::print', [
            'quotation' => $quotation,
            'customer' => $customer,
        ], ['paper' => 'a4']);

        return $pdf->stream('quotation-'. $quotation->reference .'.pdf');
    })->name('quotations.pdf');

    //Send Quotation Mail (POST - queue/send)
    Route::post('/quotations/send-email/{quotation}', 'SendQuotationEmailController')->name('quotations.send-email');

    //Sales Form Quotation
    Route::get('/quotation-sales/{quotation}', 'QuotationSalesController')->name('quotation-sales.create');

    // Auto-save draft
    Route::post('quotations/auto-save-draft', 'QuotationController@autoSaveDraft')->name('quotations.auto-save-draft');

    //quotations
    Route::resource('quotations', 'QuotationController');
});
