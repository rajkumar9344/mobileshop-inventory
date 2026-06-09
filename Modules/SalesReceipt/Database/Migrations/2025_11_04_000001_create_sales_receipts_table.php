<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSalesReceiptsTable extends Migration
{
    public function up()
    {
        Schema::create('sales_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique()->nullable();
            $table->date('date');
            $table->unsignedBigInteger('customer_id');
            $table->string('particular', 100);
            $table->string('payment_mode', 10)->nullable();
            $table->bigInteger('total_amount')->default(0); // stored in minor units
            $table->bigInteger('total_discount')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales_receipts');
    }
}
