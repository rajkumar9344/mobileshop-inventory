<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/people', function (Request $request) {
    return $request->user();
});

// API: return customer details as JSON for sale form population
Route::get('/customers/{customer}', [\Modules\People\Http\Controllers\CustomersController::class, 'apiShow'])->middleware('auth:api');