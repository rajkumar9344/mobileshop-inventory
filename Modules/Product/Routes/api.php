<?php

use Illuminate\Http\Request;
use Modules\Product\Entities\Subcategory;

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

Route::middleware('auth:api')->get('/product', function (Request $request) {
    return $request->user();
});

Route::get('/subcategories', function (Request $request) {
    $category_id = $request->query('category_id');
    if ($category_id) {
        $subcategories = Subcategory::where('category_id', $category_id)->pluck('subcategory_name', 'id');
        return response()->json($subcategories);
    }
    return response()->json([]);
});