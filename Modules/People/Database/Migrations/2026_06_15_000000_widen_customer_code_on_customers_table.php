<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen customer_code so it can hold a UAE phone number (auto-filled from
     * the phone field, e.g. +97150123456). Was varchar(10) from the original
     * India design. customer_phone is already varchar(255).
     */
    public function up(): void
    {
        if (Schema::hasColumn('customers', 'customer_code')) {
            DB::statement("ALTER TABLE customers MODIFY customer_code VARCHAR(15) NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'customer_code')) {
            DB::statement("ALTER TABLE customers MODIFY customer_code VARCHAR(10) NOT NULL");
        }
    }
};
