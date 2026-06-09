<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBankFieldsToSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('company_address');
            $table->string('bank_account')->nullable()->after('bank_name');
            $table->string('bank_branch')->nullable()->after('bank_account');
            $table->string('bank_ifsc')->nullable()->after('bank_branch');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account', 'bank_branch', 'bank_ifsc']);
        });
    }
}
