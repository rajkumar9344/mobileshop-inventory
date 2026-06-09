<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            // Basic new fields
            $table->string('customer_code', 10)->unique()->nullable(false)->after('id');

            // Address related
            $table->string('area', 30)->nullable();
            $table->string('state', 30)->nullable();
            $table->string('pincode', 10)->nullable();

            // Financials
            $table->decimal('opening_balance', 15, 2)->default(0)->nullable();
            $table->decimal('credit_limit', 15, 2)->default(0)->nullable();

            // Other fields
            $table->string('lock', 5)->nullable();
            $table->string('outstanding', 50)->nullable();

            $table->boolean('is_active')->default(1);
            $table->string('account_id', 10)->nullable();
            $table->string('remarks', 200)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'customer_code','area','state','pincode',
                'opening_balance','credit_limit','lock','outstanding','is_active','account_id','remarks'
            ]);
        });
    }
};
