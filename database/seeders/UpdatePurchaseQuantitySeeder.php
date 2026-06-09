<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UpdatePurchaseQuantitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \Modules\Product\Entities\Product::all()->each(function ($product) {
            $purchase_qty = max(0, $product->product_quantity - ($product->open_quantity ?? 0));
            $product->update(['purchase_quantity' => $purchase_qty]);
            $product->recalculateProductQuantity(); // This should keep product_quantity the same
        });
    }
}
