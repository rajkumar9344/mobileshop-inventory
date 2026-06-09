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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('supplier_code', 10)->nullable()->after('supplier_name');
            $table->string('area', 30)->nullable()->after('supplier_phone');
            $table->string('state', 30)->nullable()->after('area');
            $table->decimal('open_balance', 15, 2)->nullable()->default(0)->after('address');
            $table->decimal('credit_limit', 15, 2)->nullable()->default(0)->after('open_balance');
            $table->decimal('tax_percent', 5, 2)->nullable()->default(0)->after('credit_limit');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('tax_percent');
            $table->text('remarks')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'supplier_code',
                'area',
                'state',
                'open_balance',
                'credit_limit',
                'tax_percent',
                'status',
                'remarks'
            ]);
        });
    }
};
