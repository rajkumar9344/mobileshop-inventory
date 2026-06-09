<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->unsignedInteger('overall_nos')->default(0)->after('note');
            $table->bigInteger('overall_quantity')->default(0)->after('overall_nos');
            $table->bigInteger('overall_gross_amount')->default(0)->after('overall_quantity');
            $table->bigInteger('overall_taxable_amount')->default(0)->after('overall_gross_amount');
            $table->bigInteger('overall_tax_amount')->default(0)->after('overall_taxable_amount');
            $table->bigInteger('overall_amount')->default(0)->after('overall_tax_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn([
                'overall_nos', 'overall_quantity', 'overall_gross_amount',
                'overall_taxable_amount', 'overall_tax_amount', 'overall_amount',
            ]);
        });
    }
};
