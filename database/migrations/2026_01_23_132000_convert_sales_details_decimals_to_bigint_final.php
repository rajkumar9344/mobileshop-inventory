<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Add temporary paise columns (if missing)
        Schema::table('sales_details', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_details', 'mrp_pa_tmp')) {
                $table->bigInteger('mrp_pa_tmp')->default(0)->after('hsn');
            }
            if (! Schema::hasColumn('sales_details', 'rate_pa_tmp')) {
                $table->bigInteger('rate_pa_tmp')->default(0)->after('mrp_pa_tmp');
            }
            if (! Schema::hasColumn('sales_details', 'tax_amount_pa_tmp')) {
                $table->bigInteger('tax_amount_pa_tmp')->default(0)->after('rate_pa_tmp');
            }
            if (! Schema::hasColumn('sales_details', 'cash_discount_amount_pa_tmp')) {
                $table->bigInteger('cash_discount_amount_pa_tmp')->default(0)->after('tax_amount_pa_tmp');
            }
            if (! Schema::hasColumn('sales_details', 'discount_amount_pa_tmp')) {
                $table->bigInteger('discount_amount_pa_tmp')->default(0)->after('cash_discount_amount_pa_tmp');
            }
            if (! Schema::hasColumn('sales_details', 'unit_price_pa_tmp')) {
                $table->bigInteger('unit_price_pa_tmp')->default(0)->after('discount_type');
            }
            if (! Schema::hasColumn('sales_details', 'sub_total_pa_tmp')) {
                $table->bigInteger('sub_total_pa_tmp')->default(0)->after('unit_price_pa_tmp');
            }
        });

        // 2) Copy values into temp cols: multiply decimals by 100, copy integers as-is
        $map = [
            'mrp' => 'mrp_pa_tmp',
            'rate' => 'rate_pa_tmp',
            'tax_amount' => 'tax_amount_pa_tmp',
            'cash_discount_amount' => 'cash_discount_amount_pa_tmp',
            'discount_amount' => 'discount_amount_pa_tmp',
            'unit_price' => 'unit_price_pa_tmp',
            'sub_total' => 'sub_total_pa_tmp',
        ];

        foreach ($map as $src => $dst) {
            if (! Schema::hasColumn('sales_details', $dst)) continue;
            if (! Schema::hasColumn('sales_details', $src)) continue;

            try {
                $type = Schema::getColumnType('sales_details', $src);
            } catch (\Throwable $e) {
                $type = 'decimal';
            }

            // Source values are already stored in paise; copy directly without scaling.
            DB::statement("UPDATE sales_details SET {$dst} = {$src}");
        }

        // 3) No automatic scaling: assume product-level bigint columns are already in paise.

        // 4) Drop original decimal columns (if present)
        Schema::table('sales_details', function (Blueprint $table) use ($map) {
            foreach (array_keys($map) as $col) {
                if (Schema::hasColumn('sales_details', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // 5) Create final bigint columns with original names (if missing)
        Schema::table('sales_details', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_details', 'mrp')) $table->bigInteger('mrp')->default(0)->after('hsn');
            if (! Schema::hasColumn('sales_details', 'rate')) $table->bigInteger('rate')->default(0)->after('mrp');
            if (! Schema::hasColumn('sales_details', 'tax_amount')) $table->bigInteger('tax_amount')->default(0)->after('rate');
            if (! Schema::hasColumn('sales_details', 'cash_discount_amount')) $table->bigInteger('cash_discount_amount')->default(0)->after('tax_amount');
            if (! Schema::hasColumn('sales_details', 'discount_amount')) $table->bigInteger('discount_amount')->default(0)->after('cash_discount_amount');
            if (! Schema::hasColumn('sales_details', 'unit_price')) $table->bigInteger('unit_price')->default(0)->after('discount_type');
            if (! Schema::hasColumn('sales_details', 'sub_total')) $table->bigInteger('sub_total')->default(0)->after('unit_price');
        });

        // 6) Copy temp values into final columns
        foreach ($map as $src => $dst_tmp) {
            if (Schema::hasColumn('sales_details', $dst_tmp) && Schema::hasColumn('sales_details', $src)) {
                DB::statement("UPDATE sales_details SET {$src} = {$dst_tmp}");
            }
        }

        // 7) Drop temp columns
        Schema::table('sales_details', function (Blueprint $table) {
            if (Schema::hasColumn('sales_details', 'mrp_pa_tmp')) $table->dropColumn('mrp_pa_tmp');
            if (Schema::hasColumn('sales_details', 'rate_pa_tmp')) $table->dropColumn('rate_pa_tmp');
            if (Schema::hasColumn('sales_details', 'tax_amount_pa_tmp')) $table->dropColumn('tax_amount_pa_tmp');
            if (Schema::hasColumn('sales_details', 'cash_discount_amount_pa_tmp')) $table->dropColumn('cash_discount_amount_pa_tmp');
            if (Schema::hasColumn('sales_details', 'discount_amount_pa_tmp')) $table->dropColumn('discount_amount_pa_tmp');
            if (Schema::hasColumn('sales_details', 'unit_price_pa_tmp')) $table->dropColumn('unit_price_pa_tmp');
            if (Schema::hasColumn('sales_details', 'sub_total_pa_tmp')) $table->dropColumn('sub_total_pa_tmp');
        });
    }

    public function down(): void
    {
        // Down: convert bigint paise back to decimals (rupees)
        Schema::table('sales_details', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_details', 'mrp_old')) $table->decimal('mrp_old', 15, 2)->default(0)->after('hsn');
            if (! Schema::hasColumn('sales_details', 'rate_old')) $table->decimal('rate_old', 15, 2)->default(0)->after('mrp_old');
            if (! Schema::hasColumn('sales_details', 'tax_amount_old')) $table->decimal('tax_amount_old', 15, 2)->default(0)->after('rate_old');
            if (! Schema::hasColumn('sales_details', 'cash_discount_amount_old')) $table->decimal('cash_discount_amount_old', 15, 2)->default(0)->after('tax_amount_old');
            if (! Schema::hasColumn('sales_details', 'discount_amount_old')) $table->decimal('discount_amount_old', 15, 2)->default(0)->after('cash_discount_amount_old');
            if (! Schema::hasColumn('sales_details', 'unit_price_old')) $table->decimal('unit_price_old', 15, 2)->default(0)->after('discount_type');
            if (! Schema::hasColumn('sales_details', 'sub_total_old')) $table->decimal('sub_total_old', 15, 2)->default(0)->after('unit_price_old');
        });

        DB::statement("UPDATE sales_details SET mrp_old = ROUND(mrp / 100, 2), rate_old = ROUND(rate / 100, 2), tax_amount_old = ROUND(tax_amount / 100, 2), cash_discount_amount_old = ROUND(cash_discount_amount / 100, 2), discount_amount_old = ROUND(discount_amount / 100, 2), unit_price_old = ROUND(unit_price / 100, 2), sub_total_old = ROUND(sub_total / 100, 2)");

        // Drop bigint columns
        Schema::table('sales_details', function (Blueprint $table) {
            if (Schema::hasColumn('sales_details', 'mrp')) $table->dropColumn('mrp');
            if (Schema::hasColumn('sales_details', 'rate')) $table->dropColumn('rate');
            if (Schema::hasColumn('sales_details', 'tax_amount')) $table->dropColumn('tax_amount');
            if (Schema::hasColumn('sales_details', 'cash_discount_amount')) $table->dropColumn('cash_discount_amount');
            if (Schema::hasColumn('sales_details', 'discount_amount')) $table->dropColumn('discount_amount');
            if (Schema::hasColumn('sales_details', 'unit_price')) $table->dropColumn('unit_price');
            if (Schema::hasColumn('sales_details', 'sub_total')) $table->dropColumn('sub_total');
        });

        // Rename old decimals back
        Schema::table('sales_details', function (Blueprint $table) {
            if (Schema::hasColumn('sales_details', 'mrp_old')) $table->renameColumn('mrp_old', 'mrp');
            if (Schema::hasColumn('sales_details', 'rate_old')) $table->renameColumn('rate_old', 'rate');
            if (Schema::hasColumn('sales_details', 'tax_amount_old')) $table->renameColumn('tax_amount_old', 'tax_amount');
            if (Schema::hasColumn('sales_details', 'cash_discount_amount_old')) $table->renameColumn('cash_discount_amount_old', 'cash_discount_amount');
            if (Schema::hasColumn('sales_details', 'discount_amount_old')) $table->renameColumn('discount_amount_old', 'discount_amount');
            if (Schema::hasColumn('sales_details', 'unit_price_old')) $table->renameColumn('unit_price_old', 'unit_price');
            if (Schema::hasColumn('sales_details', 'sub_total_old')) $table->renameColumn('sub_total_old', 'sub_total');
        });
    }
};
