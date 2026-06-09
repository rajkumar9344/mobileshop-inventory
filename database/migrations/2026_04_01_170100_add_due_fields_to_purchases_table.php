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
            if (!Schema::hasColumn('purchases', 'days')) {
                $table->unsignedSmallInteger('days')->nullable()->after('invoice_date');
            }

            if (!Schema::hasColumn('purchases', 'due_date')) {
                $table->date('due_date')->nullable()->after('days');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('purchases', 'due_date')) {
                $drop[] = 'due_date';
            }
            if (Schema::hasColumn('purchases', 'days')) {
                $drop[] = 'days';
            }
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
