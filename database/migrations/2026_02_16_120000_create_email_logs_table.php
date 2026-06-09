<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('emailable_type')->nullable()->index();
            $table->unsignedBigInteger('emailable_id')->nullable()->index();
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            $table->string('message_id')->nullable();
            $table->text('headers')->nullable();
            $table->text('error')->nullable();
            $table->enum('status', ['queued', 'sent', 'failed'])->default('queued')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['emailable_type', 'emailable_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_logs');
    }
};
