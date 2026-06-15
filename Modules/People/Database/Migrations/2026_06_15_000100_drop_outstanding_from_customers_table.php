<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the obsolete customer Outstanding status flag. Outstanding bills are
     * already derived on demand from the Sales Outstanding Report; the stored
     * Yes/No flag and its auto-sync logic have been removed.
     */
    public function up(): void
    {
        if (Schema::hasColumn('customers', 'outstanding')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('outstanding');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('customers', 'outstanding')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('outstanding', 3)->default('No')->after('lock');
            });
        }
    }
};
