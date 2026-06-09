<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakeSaleIdNullableInSalesReceiptLines extends Migration
{
    public function up()
    {
        // Drop existing foreign key, make column nullable, then re-create FK with ON DELETE SET NULL
        Schema::table('sales_receipt_lines', function (Blueprint $table) {
            // attempt to drop foreign key by column
            try {
                $table->dropForeign(['sale_id']);
            } catch (\Exception $e) {
                // ignore if not present
            }

            $table->unsignedBigInteger('sale_id')->nullable()->change();

            // re-add foreign key with set null on delete
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('set null');
        });
    }

    public function down()
    {
        // Revert change only if there are no NULL sale_id rows
        $nullCount = DB::table('sales_receipt_lines')->whereNull('sale_id')->count();
        if ($nullCount > 0) {
            throw new \Exception("Cannot revert migration: found {$nullCount} rows with NULL sale_id. Fix data before rolling back.");
        }

        Schema::table('sales_receipt_lines', function (Blueprint $table) {
            try {
                $table->dropForeign(['sale_id']);
            } catch (\Exception $e) {
                // ignore
            }

            $table->unsignedBigInteger('sale_id')->nullable(false)->change();
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
        });
    }
}
