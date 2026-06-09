<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSalesReceiptLinesTable extends Migration
{
    public function up()
    {
        Schema::create('sales_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_receipt_id');
            $table->unsignedBigInteger('sale_id');
            $table->string('bill_ref');
            $table->date('bill_date')->nullable();
            $table->bigInteger('bill_amount')->default(0);
            $table->bigInteger('received_before')->default(0);
            $table->bigInteger('balance_before')->default(0);
            $table->bigInteger('payment_amount')->default(0);
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('final_balance')->default(0);
            $table->timestamps();

            $table->foreign('sales_receipt_id')->references('id')->on('sales_receipts')->cascadeOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales_receipt_lines');
    }
}
