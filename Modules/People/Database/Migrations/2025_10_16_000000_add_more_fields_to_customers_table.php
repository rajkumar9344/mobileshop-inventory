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
            $table->string('gst_no', 15)->nullable();
            $table->string('pan_no', 10)->nullable();
            $table->string('aadhar_no', 12)->nullable();

            // Address related
            $table->string('area', 30)->nullable();
            $table->string('state', 30)->nullable();
            $table->string('pincode', 10)->nullable();
            $table->text('lr_through')->nullable();

            // Financials
            $table->decimal('opening_balance', 15, 2)->default(0)->nullable();
            $table->decimal('credit_limit', 15, 2)->default(0)->nullable();
            $table->decimal('cash_discount', 5, 2)->nullable();
            $table->decimal('less_discount', 5, 2)->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();

            // Other fields
            $table->unsignedSmallInteger('terms_days')->nullable();
            $table->string('lock', 5)->nullable();
            $table->string('outstanding', 50)->nullable();

            // Flags and references kept as text for now per instructions
            $table->boolean('is_active')->default(1);
            $table->string('salesman', 10)->nullable();
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
                'customer_code','gst_no','pan_no','aadhar_no','area','state','pincode','lr_through',
                'opening_balance','credit_limit','cash_discount','less_discount','discount_percent',
                'terms_days','lock','outstanding','is_active','salesman','account_id','remarks'
            ]);
        });
    }
};
