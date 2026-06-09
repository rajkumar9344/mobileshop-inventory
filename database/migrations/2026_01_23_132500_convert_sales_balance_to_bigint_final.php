<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'balance_pa_tmp')) {
                $table->bigInteger('balance_pa_tmp')->default(0)->after('area');
            }
        });

        // Populate balance_pa_tmp from existing balance column (copy as-is -- values already in paise)
        if (Schema::hasColumn('sales', 'balance')) {
            DB::statement("UPDATE sales SET balance_pa_tmp = balance");
        }

        // Drop old balance column
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'balance')) $table->dropColumn('balance');
        });

        // Create final bigint `balance` column if missing
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'balance')) {
                $table->bigInteger('balance')->default(0)->after('area');
            }
        });

        // Copy tmp into final and drop tmp
        if (Schema::hasColumn('sales', 'balance_pa_tmp') && Schema::hasColumn('sales', 'balance')) {
            DB::statement("UPDATE sales SET balance = balance_pa_tmp");
        }

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'balance_pa_tmp')) $table->dropColumn('balance_pa_tmp');
        });
    }

    public function down(): void
    {
        // create old decimal column
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'balance_old')) {
                $table->decimal('balance_old', 15, 2)->default(0)->after('area');
            }
        });

        // Populate old decimal from bigint paise (divide by 100)
        if (Schema::hasColumn('sales', 'balance')) {
            DB::statement("UPDATE sales SET balance_old = ROUND(balance / 100, 2)");
        }

        // Drop bigint balance and rename
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'balance')) $table->dropColumn('balance');
        });

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'balance_old')) $table->renameColumn('balance_old', 'balance');
        });
    }
};
