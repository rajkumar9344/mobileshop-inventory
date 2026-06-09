<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('petty_cash_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('petty_cash_entries');
    }
};
