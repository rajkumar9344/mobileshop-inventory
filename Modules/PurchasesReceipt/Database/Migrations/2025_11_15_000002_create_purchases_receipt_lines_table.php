<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePurchasesReceiptLinesTable extends Migration
{
    public function up()
    {
        Schema::create('purchases_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchases_receipt_id');
            $table->unsignedBigInteger('purchase_id');
            $table->string('bill_ref');
            $table->date('bill_date')->nullable();
            $table->bigInteger('bill_amount')->default(0);
            $table->bigInteger('paid_before')->default(0);
            $table->bigInteger('balance_before')->default(0);
            $table->bigInteger('payment_amount')->default(0);
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('final_balance')->default(0);
            $table->timestamps();

            $table->foreign('purchases_receipt_id')->references('id')->on('purchases_receipts')->cascadeOnDelete();
            $table->foreign('purchase_id')->references('id')->on('purchases')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchases_receipt_lines');
    }
}