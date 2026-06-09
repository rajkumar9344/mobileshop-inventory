<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCustomerTypeAndContactSnapshotToQuotationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('customer_type')->default('existing')->after('customer_name');
            $table->string('contact_phone')->nullable()->after('customer_type');
            $table->string('contact_email')->nullable()->after('contact_phone');
            $table->text('contact_address')->nullable()->after('contact_email');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['customer_type', 'contact_phone', 'contact_email', 'contact_address']);
        });
    }
}
