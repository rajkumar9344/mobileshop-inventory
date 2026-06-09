<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakePurchaseIdNullableInPurchasesReceiptLines extends Migration
{
    public function up()
    {
        // Drop existing foreign key, make column nullable, then re-create FK with ON DELETE SET NULL
        Schema::table('purchases_receipt_lines', function (Blueprint $table) {
            try {
                $table->dropForeign(['purchase_id']);
            } catch (\Exception $e) {
                // ignore if FK not present
            }

            $table->unsignedBigInteger('purchase_id')->nullable()->change();

            $table->foreign('purchase_id')->references('id')->on('purchases')->onDelete('set null');
        });
    }

    public function down()
    {
        // Revert change only if there are no NULL purchase_id rows
        $nullCount = DB::table('purchases_receipt_lines')->whereNull('purchase_id')->count();
        if ($nullCount > 0) {
            throw new \Exception("Cannot revert migration: found {$nullCount} rows with NULL purchase_id. Fix data before rolling back.");
        }

        Schema::table('purchases_receipt_lines', function (Blueprint $table) {
            try {
                $table->dropForeign(['purchase_id']);
            } catch (\Exception $e) {
                // ignore
            }

            $table->unsignedBigInteger('purchase_id')->nullable(false)->change();
            $table->foreign('purchase_id')->references('id')->on('purchases')->onDelete('cascade');
        });
    }
}
