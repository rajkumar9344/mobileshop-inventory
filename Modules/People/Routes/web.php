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

    //Customers
    // Register specific routes before the resource so they are not captured by the
    // resource's "customers/{customer}" route (which would treat 'totals' as an id).
    Route::get('customers/totals', [\Modules\People\Http\Controllers\CustomersController::class, 'totals'])->name('customers.totals');
    // API endpoint used by forms (session-authenticated) - canonical single route for Select2
    Route::get('api/customers/search', [\Modules\People\Http\Controllers\CustomersController::class, 'apiSearch'])->name('api.customers.search');
    // Return customer details for a given id (used by Select2 on select)
    // Constrain {customer} to numeric IDs to avoid accidental literal matches (eg. 'search')
    Route::get('api/customers/{customer}', [\Modules\People\Http\Controllers\CustomersController::class, 'apiShow'])
        ->where('customer', '[0-9]+')
        ->name('api.customers.show');
    // Lookup customer by phone number (used by sale form phone field)
    Route::get('api/customers/by-phone/{phone}', [\Modules\People\Http\Controllers\CustomersController::class, 'apiShowByPhone'])->name('api.customers.by-phone');
     // legacy/customer json route (kept for backward compatibility)
    Route::get('customers/{customer}/json', [\Modules\People\Http\Controllers\CustomersController::class, 'apiShow'])->name('customers.json');
    // legacy/customer json route removed (consolidated to api.customers.show). If needed, add back or alias.
    Route::resource('customers', 'CustomersController');
    //Suppliers
    // Register specific routes before the resource so they are not captured by the
    // resource's "suppliers/{supplier}" route (which would treat 'totals' as an id).
    Route::get('suppliers/totals', [\Modules\People\Http\Controllers\SuppliersController::class, 'totals'])->name('suppliers.totals');
    // API endpoint used by forms (session-authenticated) - canonical single route for Select2
    Route::get('api/suppliers/search', [\Modules\People\Http\Controllers\SuppliersController::class, 'apiSearch'])->name('api.suppliers.search');
    // Return supplier details for a given id (used by Select2 on select)
    // Constrain {supplier} to numeric IDs to avoid accidental literal matches (eg. 'search')
    Route::get('api/suppliers/{supplier}', [\Modules\People\Http\Controllers\SuppliersController::class, 'apiShow'])
        ->where('supplier', '[0-9]+')
        ->name('api.suppliers.show');
    Route::resource('suppliers', 'SuppliersController');

});
