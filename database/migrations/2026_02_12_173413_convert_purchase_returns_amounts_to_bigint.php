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
        // Add temporary BIGINT columns for monetary amounts in purchase_returns
        Schema::table('purchase_returns', function (Blueprint $table) {
            // Original table columns
            if (!Schema::hasColumn('purchase_returns', 'tax_amount_temp')) {
                $table->bigInteger('tax_amount_temp')->nullable()->after('tax_percentage');
            }
            if (!Schema::hasColumn('purchase_returns', 'discount_amount_temp')) {
                $table->bigInteger('discount_amount_temp')->nullable()->after('discount_percentage');
            }
            if (!Schema::hasColumn('purchase_returns', 'shipping_amount_temp')) {
                $table->bigInteger('shipping_amount_temp')->nullable()->after('discount_amount_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'total_amount_temp')) {
                $table->bigInteger('total_amount_temp')->nullable()->after('shipping_amount_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'paid_amount_temp')) {
                $table->bigInteger('paid_amount_temp')->nullable()->after('total_amount_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'due_amount_temp')) {
                $table->bigInteger('due_amount_temp')->nullable()->after('paid_amount_temp');
            }

            // Overall calculation columns
            if (!Schema::hasColumn('purchase_returns', 'overall_quantity_temp')) {
                $table->bigInteger('overall_quantity_temp')->nullable()->after('overall_nos');
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_gross_amount_temp')) {
                $table->bigInteger('overall_gross_amount_temp')->nullable()->after('overall_quantity_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_taxable_amount_temp')) {
                $table->bigInteger('overall_taxable_amount_temp')->nullable()->after('overall_gross_amount_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_cgst_temp') && Schema::hasColumn('purchase_returns', 'overall_cgst')) {
                $table->bigInteger('overall_cgst_temp')->nullable()->after('overall_taxable_amount_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_sgst_temp') && Schema::hasColumn('purchase_returns', 'overall_sgst')) {
                $table->bigInteger('overall_sgst_temp')->nullable();
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_igst_temp') && Schema::hasColumn('purchase_returns', 'overall_igst')) {
                $table->bigInteger('overall_igst_temp')->nullable();
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_tax_amount_temp')) {
                $table->bigInteger('overall_tax_amount_temp')->nullable()->after('overall_taxable_amount_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_amount_temp')) {
                $table->bigInteger('overall_amount_temp')->nullable()->after('overall_tax_amount_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_other_temp') && Schema::hasColumn('purchase_returns', 'overall_other')) {
                $table->bigInteger('overall_other_temp')->nullable()->after('overall_amount_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_adj_temp') && Schema::hasColumn('purchase_returns', 'overall_adj')) {
                $table->bigInteger('overall_adj_temp')->nullable();
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_net_rate_temp') && Schema::hasColumn('purchase_returns', 'overall_net_rate')) {
                $table->bigInteger('overall_net_rate_temp')->nullable();
            }
        });

        // Copy data from old INTEGER columns to new BIGINT temp columns
        DB::statement("UPDATE purchase_returns SET tax_amount_temp = tax_amount WHERE tax_amount IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET discount_amount_temp = discount_amount WHERE discount_amount IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET shipping_amount_temp = shipping_amount WHERE shipping_amount IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET total_amount_temp = total_amount WHERE total_amount IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET paid_amount_temp = paid_amount WHERE paid_amount IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET due_amount_temp = due_amount WHERE due_amount IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET overall_quantity_temp = overall_quantity WHERE overall_quantity IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET overall_gross_amount_temp = overall_gross_amount WHERE overall_gross_amount IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET overall_taxable_amount_temp = overall_taxable_amount WHERE overall_taxable_amount IS NOT NULL");
        if (Schema::hasColumn('purchase_returns', 'overall_cgst')) DB::statement("UPDATE purchase_returns SET overall_cgst_temp = overall_cgst WHERE overall_cgst IS NOT NULL");
        if (Schema::hasColumn('purchase_returns', 'overall_sgst')) DB::statement("UPDATE purchase_returns SET overall_sgst_temp = overall_sgst WHERE overall_sgst IS NOT NULL");
        if (Schema::hasColumn('purchase_returns', 'overall_igst')) DB::statement("UPDATE purchase_returns SET overall_igst_temp = overall_igst WHERE overall_igst IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET overall_tax_amount_temp = overall_tax_amount WHERE overall_tax_amount IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET overall_amount_temp = overall_amount WHERE overall_amount IS NOT NULL");
        if (Schema::hasColumn('purchase_returns', 'overall_other')) DB::statement("UPDATE purchase_returns SET overall_other_temp = overall_other WHERE overall_other IS NOT NULL");
        if (Schema::hasColumn('purchase_returns', 'overall_adj')) DB::statement("UPDATE purchase_returns SET overall_adj_temp = overall_adj WHERE overall_adj IS NOT NULL");
        if (Schema::hasColumn('purchase_returns', 'overall_net_rate')) DB::statement("UPDATE purchase_returns SET overall_net_rate_temp = overall_net_rate WHERE overall_net_rate IS NOT NULL");

        // Drop old INTEGER columns
        Schema::table('purchase_returns', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_returns', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'shipping_amount')) {
                $table->dropColumn('shipping_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'due_amount')) {
                $table->dropColumn('due_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_quantity')) {
                $table->dropColumn('overall_quantity');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_gross_amount')) {
                $table->dropColumn('overall_gross_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_taxable_amount')) {
                $table->dropColumn('overall_taxable_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_cgst')) {
                $table->dropColumn('overall_cgst');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_sgst')) {
                $table->dropColumn('overall_sgst');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_igst')) {
                $table->dropColumn('overall_igst');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_tax_amount')) {
                $table->dropColumn('overall_tax_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_amount')) {
                $table->dropColumn('overall_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_other')) {
                $table->dropColumn('overall_other');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_adj')) {
                $table->dropColumn('overall_adj');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_net_rate')) {
                $table->dropColumn('overall_net_rate');
            }
        });

        // Rename temp columns to final BIGINT columns
        Schema::table('purchase_returns', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_returns', 'tax_amount_temp')) {
                $table->renameColumn('tax_amount_temp', 'tax_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'discount_amount_temp')) {
                $table->renameColumn('discount_amount_temp', 'discount_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'shipping_amount_temp')) {
                $table->renameColumn('shipping_amount_temp', 'shipping_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'total_amount_temp')) {
                $table->renameColumn('total_amount_temp', 'total_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'paid_amount_temp')) {
                $table->renameColumn('paid_amount_temp', 'paid_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'due_amount_temp')) {
                $table->renameColumn('due_amount_temp', 'due_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_quantity_temp')) {
                $table->renameColumn('overall_quantity_temp', 'overall_quantity');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_gross_amount_temp')) {
                $table->renameColumn('overall_gross_amount_temp', 'overall_gross_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_taxable_amount_temp')) {
                $table->renameColumn('overall_taxable_amount_temp', 'overall_taxable_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_cgst_temp')) {
                $table->renameColumn('overall_cgst_temp', 'overall_cgst');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_sgst_temp')) {
                $table->renameColumn('overall_sgst_temp', 'overall_sgst');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_igst_temp')) {
                $table->renameColumn('overall_igst_temp', 'overall_igst');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_tax_amount_temp')) {
                $table->renameColumn('overall_tax_amount_temp', 'overall_tax_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_amount_temp')) {
                $table->renameColumn('overall_amount_temp', 'overall_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_other_temp')) {
                $table->renameColumn('overall_other_temp', 'overall_other');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_adj_temp')) {
                $table->renameColumn('overall_adj_temp', 'overall_adj');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_net_rate_temp')) {
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
        Schema::table('purchase_returns', function (Blueprint $table) {
            // Original table columns
            if (!Schema::hasColumn('purchase_returns', 'tax_amount_temp')) {
                $table->integer('tax_amount_temp')->nullable()->after('tax_percentage');
            }
            if (!Schema::hasColumn('purchase_returns', 'discount_amount_temp')) {
                $table->integer('discount_amount_temp')->nullable()->after('discount_percentage');
            }
            if (!Schema::hasColumn('purchase_returns', 'shipping_amount_temp')) {
                $table->integer('shipping_amount_temp')->nullable()->after('discount_amount_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'total_amount_temp')) {
                $table->integer('total_amount_temp')->nullable()->after('shipping_amount_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'paid_amount_temp')) {
                $table->integer('paid_amount_temp')->nullable()->after('total_amount_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'due_amount_temp')) {
                $table->integer('due_amount_temp')->nullable()->after('paid_amount_temp');
            }

            // Overall calculation columns
            if (!Schema::hasColumn('purchase_returns', 'overall_quantity_temp')) {
                $table->integer('overall_quantity_temp')->nullable()->after('overall_nos');
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_gross_amount_temp')) {
                $table->integer('overall_gross_amount_temp')->nullable()->after('overall_quantity_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_taxable_amount_temp')) {
                $table->integer('overall_taxable_amount_temp')->nullable()->after('overall_gross_amount_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_cgst_temp') && Schema::hasColumn('purchase_returns', 'overall_cgst')) {
                $table->integer('overall_cgst_temp')->nullable()->after('overall_taxable_amount_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_sgst_temp') && Schema::hasColumn('purchase_returns', 'overall_sgst')) {
                $table->integer('overall_sgst_temp')->nullable();
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_igst_temp') && Schema::hasColumn('purchase_returns', 'overall_igst')) {
                $table->integer('overall_igst_temp')->nullable();
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_tax_amount_temp')) {
                $table->integer('overall_tax_amount_temp')->nullable()->after('overall_taxable_amount_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_amount_temp')) {
                $table->integer('overall_amount_temp')->nullable()->after('overall_tax_amount_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_other_temp') && Schema::hasColumn('purchase_returns', 'overall_other')) {
                $table->integer('overall_other_temp')->nullable()->after('overall_amount_temp');
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_adj_temp') && Schema::hasColumn('purchase_returns', 'overall_adj')) {
                $table->integer('overall_adj_temp')->nullable();
            }
            if (!Schema::hasColumn('purchase_returns', 'overall_net_rate_temp') && Schema::hasColumn('purchase_returns', 'overall_net_rate')) {
                $table->integer('overall_net_rate_temp')->nullable();
            }
        });

        // Copy data back from BIGINT columns to INTEGER temp columns (with clamping for safety)
        DB::statement("UPDATE purchase_returns SET tax_amount_temp = LEAST(tax_amount, 2147483647) WHERE tax_amount IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET discount_amount_temp = LEAST(discount_amount, 2147483647) WHERE discount_amount IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET shipping_amount_temp = LEAST(shipping_amount, 2147483647) WHERE shipping_amount IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET total_amount_temp = LEAST(total_amount, 2147483647) WHERE total_amount IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET paid_amount_temp = LEAST(paid_amount, 2147483647) WHERE paid_amount IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET due_amount_temp = LEAST(due_amount, 2147483647) WHERE due_amount IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET overall_quantity_temp = LEAST(overall_quantity, 2147483647) WHERE overall_quantity IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET overall_gross_amount_temp = LEAST(overall_gross_amount, 2147483647) WHERE overall_gross_amount IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET overall_taxable_amount_temp = LEAST(overall_taxable_amount, 2147483647) WHERE overall_taxable_amount IS NOT NULL");
        if (Schema::hasColumn('purchase_returns', 'overall_cgst')) DB::statement("UPDATE purchase_returns SET overall_cgst_temp = LEAST(overall_cgst, 2147483647) WHERE overall_cgst IS NOT NULL");
        if (Schema::hasColumn('purchase_returns', 'overall_sgst')) DB::statement("UPDATE purchase_returns SET overall_sgst_temp = LEAST(overall_sgst, 2147483647) WHERE overall_sgst IS NOT NULL");
        if (Schema::hasColumn('purchase_returns', 'overall_igst')) DB::statement("UPDATE purchase_returns SET overall_igst_temp = LEAST(overall_igst, 2147483647) WHERE overall_igst IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET overall_tax_amount_temp = LEAST(overall_tax_amount, 2147483647) WHERE overall_tax_amount IS NOT NULL");
        DB::statement("UPDATE purchase_returns SET overall_amount_temp = LEAST(overall_amount, 2147483647) WHERE overall_amount IS NOT NULL");
        if (Schema::hasColumn('purchase_returns', 'overall_other')) DB::statement("UPDATE purchase_returns SET overall_other_temp = LEAST(overall_other, 2147483647) WHERE overall_other IS NOT NULL");
        if (Schema::hasColumn('purchase_returns', 'overall_adj')) DB::statement("UPDATE purchase_returns SET overall_adj_temp = LEAST(overall_adj, 2147483647) WHERE overall_adj IS NOT NULL");
        if (Schema::hasColumn('purchase_returns', 'overall_net_rate')) DB::statement("UPDATE purchase_returns SET overall_net_rate_temp = LEAST(overall_net_rate, 2147483647) WHERE overall_net_rate IS NOT NULL");

        // Drop BIGINT columns
        Schema::table('purchase_returns', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_returns', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'shipping_amount')) {
                $table->dropColumn('shipping_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'due_amount')) {
                $table->dropColumn('due_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_quantity')) {
                $table->dropColumn('overall_quantity');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_gross_amount')) {
                $table->dropColumn('overall_gross_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_taxable_amount')) {
                $table->dropColumn('overall_taxable_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_cgst')) {
                $table->dropColumn('overall_cgst');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_sgst')) {
                $table->dropColumn('overall_sgst');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_igst')) {
                $table->dropColumn('overall_igst');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_tax_amount')) {
                $table->dropColumn('overall_tax_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_amount')) {
                $table->dropColumn('overall_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_other')) {
                $table->dropColumn('overall_other');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_adj')) {
                $table->dropColumn('overall_adj');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_net_rate')) {
                $table->dropColumn('overall_net_rate');
            }
        });

        // Rename temp columns back to original INTEGER columns
        Schema::table('purchase_returns', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_returns', 'tax_amount_temp')) {
                $table->renameColumn('tax_amount_temp', 'tax_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'discount_amount_temp')) {
                $table->renameColumn('discount_amount_temp', 'discount_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'shipping_amount_temp')) {
                $table->renameColumn('shipping_amount_temp', 'shipping_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'total_amount_temp')) {
                $table->renameColumn('total_amount_temp', 'total_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'paid_amount_temp')) {
                $table->renameColumn('paid_amount_temp', 'paid_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'due_amount_temp')) {
                $table->renameColumn('due_amount_temp', 'due_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_quantity_temp')) {
                $table->renameColumn('overall_quantity_temp', 'overall_quantity');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_gross_amount_temp')) {
                $table->renameColumn('overall_gross_amount_temp', 'overall_gross_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_taxable_amount_temp')) {
                $table->renameColumn('overall_taxable_amount_temp', 'overall_taxable_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_cgst_temp')) {
                $table->renameColumn('overall_cgst_temp', 'overall_cgst');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_sgst_temp')) {
                $table->renameColumn('overall_sgst_temp', 'overall_sgst');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_igst_temp')) {
                $table->renameColumn('overall_igst_temp', 'overall_igst');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_tax_amount_temp')) {
                $table->renameColumn('overall_tax_amount_temp', 'overall_tax_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_amount_temp')) {
                $table->renameColumn('overall_amount_temp', 'overall_amount');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_other_temp')) {
                $table->renameColumn('overall_other_temp', 'overall_other');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_adj_temp')) {
                $table->renameColumn('overall_adj_temp', 'overall_adj');
            }
            if (Schema::hasColumn('purchase_returns', 'overall_net_rate_temp')) {
                $table->renameColumn('overall_net_rate_temp', 'overall_net_rate');
            }
        });
    }
};
