<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\BarcodeController;
use Modules\Product\Http\Controllers\ProductController;
use Modules\Product\Http\Controllers\CategoriesController;
use Modules\Product\Http\Controllers\SubcategoriesController;
use Modules\Product\Entities\Category;

Route::group(['middleware' => 'auth'], function () {
    //Print Barcode
    Route::get('/products/print-barcode', [BarcodeController::class, 'printBarcode'])->name('barcode.print');
    // Check product code uniqueness (AJAX) - placed before resource routes to avoid model binding conflicts
    Route::get('/products/check-code', [ProductController::class, 'checkCode'])->name('products.checkCode');
    //Product
    Route::resource('products', ProductController::class);
    //Product Category
    Route::resource('product-categories', CategoriesController::class)->except('create', 'show');
    Route::get('product-categories/{category}/show', [CategoriesController::class, 'show'])->name('product-categories.show');
    // Product Subcategories
    Route::resource('product-subcategories', SubcategoriesController::class)->except('create', 'show');
    Route::get('product-subcategories/{subcategory}/show', [SubcategoriesController::class, 'show'])->name('product-subcategories.show');
    Route::delete('product-categories/delete-category/{category}', [CategoriesController::class, 'deleteCategory'])->name('product-categories.delete-category');
    Route::delete('product-subcategories/delete-subcategory/{subcategory}', [SubcategoriesController::class, 'deleteSubcategory'])->name('product-subcategories.delete-subcategory');

    //Subcategories API
    Route::get('/get-subcategories', function (\Illuminate\Http\Request $request) {
        $category_id = $request->query('category_id');
        if ($category_id) {
            $subcategories = \Modules\Product\Entities\Subcategory::where('category_id', $category_id)
                ->where('status', true)
                ->pluck('subcategory_name', 'id')->toArray();
            return response()->json($subcategories);
        }
        return response()->json([]);
    })->name('get-subcategories');

    //Bins by Rack API
    Route::get('/get-bins', function (\Illuminate\Http\Request $request) {
        $rack_identifier = $request->query('rack_id');
        if ($rack_identifier) {
            // The product form sends the human-readable rack identifier (e.g. 'R006').
            // Find the rack record by its `rack_id` column, then use the numeric PK to fetch bins.
            $rack = \Modules\Rack\Entities\Rack::where('rack_id', $rack_identifier)->first();
            if ($rack) {
                $bins = \Modules\Bin\Entities\Bin::where('rack_id', $rack->id)
                    ->where('status', 'active')
                    ->pluck('bin_id', 'id')->toArray();
                return response()->json($bins);
            }
        }
        return response()->json([]);
    })->name('get-bins');
});

