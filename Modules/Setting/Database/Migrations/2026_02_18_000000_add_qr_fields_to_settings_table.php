<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddQrFieldsToSettingsTable extends Migration
{
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->text('gpay_qr')->nullable()->after('bank_ifsc');
            $table->text('phonepe_qr')->nullable()->after('gpay_qr');
        });
    }

    public function down()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['gpay_qr', 'phonepe_qr']);
        });
    }
}
