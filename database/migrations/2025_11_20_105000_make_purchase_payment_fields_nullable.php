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
            // Make paid and due amounts nullable so purchases can be created without immediate payment
            // and make payment_method nullable because payments are handled in receipts module.
            $table->integer('paid_amount')->nullable()->change();
            if (Schema::hasColumn('purchases', 'due_amount')) {
                $table->integer('due_amount')->nullable()->change();
            }
            $table->string('payment_method')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // Revert to non-nullable. Default to 0 for numeric columns to avoid DB errors.
            $table->integer('paid_amount')->default(0)->change();
            if (Schema::hasColumn('purchases', 'due_amount')) {
                $table->integer('due_amount')->default(0)->change();
            }
            $table->string('payment_method')->nullable(false)->change();
        });
    }
};
