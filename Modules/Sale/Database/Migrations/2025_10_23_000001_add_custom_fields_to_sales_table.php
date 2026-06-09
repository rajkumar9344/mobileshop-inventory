<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('area', 30)->nullable()->after('customer_id');
            $table->decimal('balance', 15, 2)->nullable(false)->after('area');
            $table->string('bill_type', 1)->nullable(false)->after('balance');
            $table->unsignedSmallInteger('days')->nullable(false)->after('bill_type');
    
            $table->date('due_date')->nullable()->after('days');
            $table->string('phone_no', 15)->nullable()->after('due_date');
            $table->string('discount_type', 1)->nullable()->after('phone_no');
            // Foreign key constraint for customer_id if needed
            // $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    public function down() {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'area',
                'balance',
                'bill_type',
                'days',
                'due_date',
                'phone_no',
                'discount_type',
            ]);
        });
    }
};
