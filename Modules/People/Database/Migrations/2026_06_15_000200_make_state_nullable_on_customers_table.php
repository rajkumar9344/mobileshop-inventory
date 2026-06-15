<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix schema drift: `customers.state` was left NOT NULL on the live DB while
     * the customer form (StoreCustomerRequest) treats State as optional and its
     * sibling columns (city/address/pincode) are already nullable. Make it NULL
     * so saving a customer without a State no longer fails.
     */
    public function up(): void
    {
        if (Schema::hasColumn('customers', 'state')) {
            DB::statement("ALTER TABLE `customers` MODIFY `state` VARCHAR(30) NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'state')) {
            DB::statement("ALTER TABLE `customers` MODIFY `state` VARCHAR(30) NOT NULL");
        }
    }
};
