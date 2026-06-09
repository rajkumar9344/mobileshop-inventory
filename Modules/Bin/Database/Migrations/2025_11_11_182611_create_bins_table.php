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
        Schema::create('bins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rack_id')->constrained('rack_master')->onDelete('cascade');
            $table->string('bin_id', 20); // Bin ID: alphanumeric, space, hyphen, underscore
            $table->string('bin_name', 100); // Bin Name
            $table->integer('capacity'); // Bin Capacity: numbers, max 4 digits
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('barcode')->nullable(); // Barcode for bin
            $table->timestamps();
            
            // Unique constraints: bin_id and bin_name unique per rack
            $table->unique(['rack_id', 'bin_id']);
            $table->unique(['rack_id', 'bin_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bins');
    }
};
