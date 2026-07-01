<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time conversion: make all stored purchase costs VAT-inclusive (×1.05).
 *
 * Before this migration product_cost (on products) and purchase_rate (on sale /
 * sale-return / quotation detail lines) were stored as the pre-VAT weighted
 * average purchase rate.  From this point forward PurchaseController stores them
 * as the incl-VAT average so that the Sale / Sale Return / Quotation "Purchase
 * Rate" column and the Profit/Loss report both operate on a consistent incl-VAT
 * basis.
 *
 * All four columns are stored as minor-units (paise ×100), so multiplying by
 * 1.05 and rounding to the nearest integer is safe.
 *
 * Assumption: all historical purchases used the standard UAE 5% VAT (the app
 * forces 5% at cart-add time), so a flat ×1.05 conversion is correct for every
 * existing row.
 */
return new class extends Migration
{
    public function up(): void
    {
        // products.product_cost — integer, minor units (×100)
        DB::statement('UPDATE products SET product_cost = ROUND(product_cost * 1.05) WHERE product_cost > 0');

        // sales_details.purchase_rate — bigInteger, minor units (×100)
        DB::statement('UPDATE sales_details SET purchase_rate = ROUND(purchase_rate * 1.05) WHERE purchase_rate > 0');

        // sale_return_details.purchase_rate — bigInteger, minor units (×100)
        DB::statement('UPDATE sale_return_details SET purchase_rate = ROUND(purchase_rate * 1.05) WHERE purchase_rate > 0');

        // quotation_details.purchase_rate — bigInteger, minor units (×100)
        DB::statement('UPDATE quotation_details SET purchase_rate = ROUND(purchase_rate * 1.05) WHERE purchase_rate > 0');
    }

    public function down(): void
    {
        // Reverse: divide by 1.05 to restore pre-VAT values
        DB::statement('UPDATE products SET product_cost = ROUND(product_cost / 1.05) WHERE product_cost > 0');
        DB::statement('UPDATE sales_details SET purchase_rate = ROUND(purchase_rate / 1.05) WHERE purchase_rate > 0');
        DB::statement('UPDATE sale_return_details SET purchase_rate = ROUND(purchase_rate / 1.05) WHERE purchase_rate > 0');
        DB::statement('UPDATE quotation_details SET purchase_rate = ROUND(purchase_rate / 1.05) WHERE purchase_rate > 0');
    }
};
