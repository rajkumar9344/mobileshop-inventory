<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePurchasesReceiptsTable extends Migration
{
    public function up()
    {
        Schema::create('purchases_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique()->nullable();
            $table->date('date');
            $table->unsignedBigInteger('supplier_id');
            $table->string('particular', 100)->nullable();
            $table->string('payment_mode', 10)->nullable();
            $table->bigInteger('total_amount')->default(0); // stored in minor units
            $table->bigInteger('total_discount')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchases_receipts');
    }
}