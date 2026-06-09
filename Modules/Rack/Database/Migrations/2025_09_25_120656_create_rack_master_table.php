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
        Schema::create('rack_master', function (Blueprint $table) {
            $table->id(); // Auto increment primary key
            $table->string('rack_id'); // Rack ID (e.g., R001) - not unique since multiple bins per rack
            $table->string('rack_name'); // Rack name (e.g., Rack A, Rack 5)
            $table->string('bin_id'); // Bin ID within the rack
            $table->string('bin_name'); // Bin name or identifier
            $table->integer('capacity'); // Capacity of the specific bin
            $table->enum('status', ['Active', 'Inactive'])->default('Active'); // Status
            $table->timestamps(); // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rack_master');
    }
};
