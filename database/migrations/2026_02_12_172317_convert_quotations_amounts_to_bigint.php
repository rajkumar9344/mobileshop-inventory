<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add temporary BIGINT columns for monetary amounts in quotations
        Schema::table('quotations', function (Blueprint $table) {
            // Original table columns
            if (!Schema::hasColumn('quotations', 'tax_amount_temp')) {
                $table->bigInteger('tax_amount_temp')->nullable()->after('tax_percentage');
            }
            if (!Schema::hasColumn('quotations', 'discount_amount_temp')) {
                $table->bigInteger('discount_amount_temp')->nullable()->after('discount_percentage');
            }
            if (!Schema::hasColumn('quotations', 'shipping_amount_temp')) {
                $table->bigInteger('shipping_amount_temp')->nullable()->after('discount_amount_temp');
            }
            if (!Schema::hasColumn('quotations', 'total_amount_temp')) {
                $table->bigInteger('total_amount_temp')->nullable()->after('shipping_amount_temp');
            }

            // Overall calculation columns
            if (!Schema::hasColumn('quotations', 'overall_quantity_temp')) {
                $table->bigInteger('overall_quantity_temp')->nullable()->after('overall_nos');
            }
            if (!Schema::hasColumn('quotations', 'overall_gross_amount_temp')) {
                $table->bigInteger('overall_gross_amount_temp')->nullable()->after('overall_quantity_temp');
            }
            if (!Schema::hasColumn('quotations', 'overall_taxable_amount_temp')) {
                $table->bigInteger('overall_taxable_amount_temp')->nullable()->after('overall_gross_amount_temp');
            }
            if (!Schema::hasColumn('quotations', 'overall_cgst_temp')) {
                $table->bigInteger('overall_cgst_temp')->nullable()->after('overall_taxable_amount_temp');
            }
            if (!Schema::hasColumn('quotations', 'overall_sgst_temp')) {
                $table->bigInteger('overall_sgst_temp')->nullable()->after('overall_cgst_temp');
            }
            if (!Schema::hasColumn('quotations', 'overall_igst_temp')) {
                $table->bigInteger('overall_igst_temp')->nullable()->after('overall_sgst_temp');
            }
            if (!Schema::hasColumn('quotations', 'overall_tax_amount_temp')) {
                $table->bigInteger('overall_tax_amount_temp')->nullable()->after('overall_igst_temp');
            }
            if (!Schema::hasColumn('quotations', 'overall_amount_temp')) {
                $table->bigInteger('overall_amount_temp')->nullable()->after('overall_tcs_percent');
            }
            if (!Schema::hasColumn('quotations', 'overall_other_temp')) {
                $table->bigInteger('overall_other_temp')->nullable()->after('overall_amount_temp');
            }
            if (!Schema::hasColumn('quotations', 'overall_adj_temp')) {
                $table->bigInteger('overall_adj_temp')->nullable()->after('overall_other_temp');
            }
            if (!Schema::hasColumn('quotations', 'overall_net_rate_temp')) {
                $table->bigInteger('overall_net_rate_temp')->nullable()->after('overall_adj_temp');
            }
        });

        // Copy data from old INTEGER columns to new BIGINT temp columns
        DB::statement("UPDATE quotations SET tax_amount_temp = tax_amount WHERE tax_amount IS NOT NULL");
        DB::statement("UPDATE quotations SET discount_amount_temp = discount_amount WHERE discount_amount IS NOT NULL");
        DB::statement("UPDATE quotations SET shipping_amount_temp = shipping_amount WHERE shipping_amount IS NOT NULL");
        DB::statement("UPDATE quotations SET total_amount_temp = total_amount WHERE total_amount IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_quantity_temp = overall_quantity WHERE overall_quantity IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_gross_amount_temp = overall_gross_amount WHERE overall_gross_amount IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_taxable_amount_temp = overall_taxable_amount WHERE overall_taxable_amount IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_cgst_temp = overall_cgst WHERE overall_cgst IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_sgst_temp = overall_sgst WHERE overall_sgst IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_igst_temp = overall_igst WHERE overall_igst IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_tax_amount_temp = overall_tax_amount WHERE overall_tax_amount IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_amount_temp = overall_amount WHERE overall_amount IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_other_temp = overall_other WHERE overall_other IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_adj_temp = overall_adj WHERE overall_adj IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_net_rate_temp = overall_net_rate WHERE overall_net_rate IS NOT NULL");

        // Drop old INTEGER columns
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('quotations', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
            if (Schema::hasColumn('quotations', 'shipping_amount')) {
                $table->dropColumn('shipping_amount');
            }
            if (Schema::hasColumn('quotations', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_quantity')) {
                $table->dropColumn('overall_quantity');
            }
            if (Schema::hasColumn('quotations', 'overall_gross_amount')) {
                $table->dropColumn('overall_gross_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_taxable_amount')) {
                $table->dropColumn('overall_taxable_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_cgst')) {
                $table->dropColumn('overall_cgst');
            }
            if (Schema::hasColumn('quotations', 'overall_sgst')) {
                $table->dropColumn('overall_sgst');
            }
            if (Schema::hasColumn('quotations', 'overall_igst')) {
                $table->dropColumn('overall_igst');
            }
            if (Schema::hasColumn('quotations', 'overall_tax_amount')) {
                $table->dropColumn('overall_tax_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_amount')) {
                $table->dropColumn('overall_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_other')) {
                $table->dropColumn('overall_other');
            }
            if (Schema::hasColumn('quotations', 'overall_adj')) {
                $table->dropColumn('overall_adj');
            }
            if (Schema::hasColumn('quotations', 'overall_net_rate')) {
                $table->dropColumn('overall_net_rate');
            }
        });

        // Rename temp columns to final BIGINT columns
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'tax_amount_temp')) {
                $table->renameColumn('tax_amount_temp', 'tax_amount');
            }
            if (Schema::hasColumn('quotations', 'discount_amount_temp')) {
                $table->renameColumn('discount_amount_temp', 'discount_amount');
            }
            if (Schema::hasColumn('quotations', 'shipping_amount_temp')) {
                $table->renameColumn('shipping_amount_temp', 'shipping_amount');
            }
            if (Schema::hasColumn('quotations', 'total_amount_temp')) {
                $table->renameColumn('total_amount_temp', 'total_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_quantity_temp')) {
                $table->renameColumn('overall_quantity_temp', 'overall_quantity');
            }
            if (Schema::hasColumn('quotations', 'overall_gross_amount_temp')) {
                $table->renameColumn('overall_gross_amount_temp', 'overall_gross_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_taxable_amount_temp')) {
                $table->renameColumn('overall_taxable_amount_temp', 'overall_taxable_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_cgst_temp')) {
                $table->renameColumn('overall_cgst_temp', 'overall_cgst');
            }
            if (Schema::hasColumn('quotations', 'overall_sgst_temp')) {
                $table->renameColumn('overall_sgst_temp', 'overall_sgst');
            }
            if (Schema::hasColumn('quotations', 'overall_igst_temp')) {
                $table->renameColumn('overall_igst_temp', 'overall_igst');
            }
            if (Schema::hasColumn('quotations', 'overall_tax_amount_temp')) {
                $table->renameColumn('overall_tax_amount_temp', 'overall_tax_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_amount_temp')) {
                $table->renameColumn('overall_amount_temp', 'overall_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_other_temp')) {
                $table->renameColumn('overall_other_temp', 'overall_other');
            }
            if (Schema::hasColumn('quotations', 'overall_adj_temp')) {
                $table->renameColumn('overall_adj_temp', 'overall_adj');
            }
            if (Schema::hasColumn('quotations', 'overall_net_rate_temp')) {
                $table->renameColumn('overall_net_rate_temp', 'overall_net_rate');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add temporary INTEGER columns for rollback
        Schema::table('quotations', function (Blueprint $table) {
            // Original table columns
            if (!Schema::hasColumn('quotations', 'tax_amount_temp')) {
                $table->integer('tax_amount_temp')->nullable()->after('tax_percentage');
            }
            if (!Schema::hasColumn('quotations', 'discount_amount_temp')) {
                $table->integer('discount_amount_temp')->nullable()->after('discount_percentage');
            }
            if (!Schema::hasColumn('quotations', 'shipping_amount_temp')) {
                $table->integer('shipping_amount_temp')->nullable()->after('discount_amount_temp');
            }
            if (!Schema::hasColumn('quotations', 'total_amount_temp')) {
                $table->integer('total_amount_temp')->nullable()->after('shipping_amount_temp');
            }

            // Overall calculation columns
            if (!Schema::hasColumn('quotations', 'overall_quantity_temp')) {
                $table->integer('overall_quantity_temp')->nullable()->after('overall_nos');
            }
            if (!Schema::hasColumn('quotations', 'overall_gross_amount_temp')) {
                $table->integer('overall_gross_amount_temp')->nullable()->after('overall_quantity_temp');
            }
            if (!Schema::hasColumn('quotations', 'overall_taxable_amount_temp')) {
                $table->integer('overall_taxable_amount_temp')->nullable()->after('overall_gross_amount_temp');
            }
            if (!Schema::hasColumn('quotations', 'overall_cgst_temp')) {
                $table->integer('overall_cgst_temp')->nullable()->after('overall_taxable_amount_temp');
            }
            if (!Schema::hasColumn('quotations', 'overall_sgst_temp')) {
                $table->integer('overall_sgst_temp')->nullable()->after('overall_cgst_temp');
            }
            if (!Schema::hasColumn('quotations', 'overall_igst_temp')) {
                $table->integer('overall_igst_temp')->nullable()->after('overall_sgst_temp');
            }
            if (!Schema::hasColumn('quotations', 'overall_tax_amount_temp')) {
                $table->integer('overall_tax_amount_temp')->nullable()->after('overall_igst_temp');
            }
            if (!Schema::hasColumn('quotations', 'overall_amount_temp')) {
                $table->integer('overall_amount_temp')->nullable()->after('overall_tcs_percent');
            }
            if (!Schema::hasColumn('quotations', 'overall_other_temp')) {
                $table->integer('overall_other_temp')->nullable()->after('overall_amount_temp');
            }
            if (!Schema::hasColumn('quotations', 'overall_adj_temp')) {
                $table->integer('overall_adj_temp')->nullable()->after('overall_other_temp');
            }
            if (!Schema::hasColumn('quotations', 'overall_net_rate_temp')) {
                $table->integer('overall_net_rate_temp')->nullable()->after('overall_adj_temp');
            }
        });

        // Copy data back from BIGINT columns to INTEGER temp columns (with clamping for safety)
        DB::statement("UPDATE quotations SET tax_amount_temp = LEAST(tax_amount, 2147483647) WHERE tax_amount IS NOT NULL");
        DB::statement("UPDATE quotations SET discount_amount_temp = LEAST(discount_amount, 2147483647) WHERE discount_amount IS NOT NULL");
        DB::statement("UPDATE quotations SET shipping_amount_temp = LEAST(shipping_amount, 2147483647) WHERE shipping_amount IS NOT NULL");
        DB::statement("UPDATE quotations SET total_amount_temp = LEAST(total_amount, 2147483647) WHERE total_amount IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_quantity_temp = LEAST(overall_quantity, 2147483647) WHERE overall_quantity IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_gross_amount_temp = LEAST(overall_gross_amount, 2147483647) WHERE overall_gross_amount IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_taxable_amount_temp = LEAST(overall_taxable_amount, 2147483647) WHERE overall_taxable_amount IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_cgst_temp = LEAST(overall_cgst, 2147483647) WHERE overall_cgst IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_sgst_temp = LEAST(overall_sgst, 2147483647) WHERE overall_sgst IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_igst_temp = LEAST(overall_igst, 2147483647) WHERE overall_igst IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_tax_amount_temp = LEAST(overall_tax_amount, 2147483647) WHERE overall_tax_amount IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_amount_temp = LEAST(overall_amount, 2147483647) WHERE overall_amount IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_other_temp = LEAST(overall_other, 2147483647) WHERE overall_other IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_adj_temp = LEAST(overall_adj, 2147483647) WHERE overall_adj IS NOT NULL");
        DB::statement("UPDATE quotations SET overall_net_rate_temp = LEAST(overall_net_rate, 2147483647) WHERE overall_net_rate IS NOT NULL");

        // Drop BIGINT columns
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('quotations', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
            if (Schema::hasColumn('quotations', 'shipping_amount')) {
                $table->dropColumn('shipping_amount');
            }
            if (Schema::hasColumn('quotations', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_quantity')) {
                $table->dropColumn('overall_quantity');
            }
            if (Schema::hasColumn('quotations', 'overall_gross_amount')) {
                $table->dropColumn('overall_gross_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_taxable_amount')) {
                $table->dropColumn('overall_taxable_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_cgst')) {
                $table->dropColumn('overall_cgst');
            }
            if (Schema::hasColumn('quotations', 'overall_sgst')) {
                $table->dropColumn('overall_sgst');
            }
            if (Schema::hasColumn('quotations', 'overall_igst')) {
                $table->dropColumn('overall_igst');
            }
            if (Schema::hasColumn('quotations', 'overall_tax_amount')) {
                $table->dropColumn('overall_tax_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_amount')) {
                $table->dropColumn('overall_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_other')) {
                $table->dropColumn('overall_other');
            }
            if (Schema::hasColumn('quotations', 'overall_adj')) {
                $table->dropColumn('overall_adj');
            }
            if (Schema::hasColumn('quotations', 'overall_net_rate')) {
                $table->dropColumn('overall_net_rate');
            }
        });

        // Rename temp columns back to original INTEGER columns
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'tax_amount_temp')) {
                $table->renameColumn('tax_amount_temp', 'tax_amount');
            }
            if (Schema::hasColumn('quotations', 'discount_amount_temp')) {
                $table->renameColumn('discount_amount_temp', 'discount_amount');
            }
            if (Schema::hasColumn('quotations', 'shipping_amount_temp')) {
                $table->renameColumn('shipping_amount_temp', 'shipping_amount');
            }
            if (Schema::hasColumn('quotations', 'total_amount_temp')) {
                $table->renameColumn('total_amount_temp', 'total_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_quantity_temp')) {
                $table->renameColumn('overall_quantity_temp', 'overall_quantity');
            }
            if (Schema::hasColumn('quotations', 'overall_gross_amount_temp')) {
                $table->renameColumn('overall_gross_amount_temp', 'overall_gross_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_taxable_amount_temp')) {
                $table->renameColumn('overall_taxable_amount_temp', 'overall_taxable_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_cgst_temp')) {
                $table->renameColumn('overall_cgst_temp', 'overall_cgst');
            }
            if (Schema::hasColumn('quotations', 'overall_sgst_temp')) {
                $table->renameColumn('overall_sgst_temp', 'overall_sgst');
            }
            if (Schema::hasColumn('quotations', 'overall_igst_temp')) {
                $table->renameColumn('overall_igst_temp', 'overall_igst');
            }
            if (Schema::hasColumn('quotations', 'overall_tax_amount_temp')) {
                $table->renameColumn('overall_tax_amount_temp', 'overall_tax_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_amount_temp')) {
                $table->renameColumn('overall_amount_temp', 'overall_amount');
            }
            if (Schema::hasColumn('quotations', 'overall_other_temp')) {
                $table->renameColumn('overall_other_temp', 'overall_other');
            }
            if (Schema::hasColumn('quotations', 'overall_adj_temp')) {
                $table->renameColumn('overall_adj_temp', 'overall_adj');
            }
            if (Schema::hasColumn('quotations', 'overall_net_rate_temp')) {
                $table->renameColumn('overall_net_rate_temp', 'overall_net_rate');
            }
        });
    }
};
