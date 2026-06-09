<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('code', 100);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique('code'); // each code must be globally unique
        });

        // Seed from existing products.product_code (mark as primary)
        DB::statement("
            INSERT INTO product_codes (product_id, code, is_primary, created_at, updated_at)
            SELECT id, product_code, 1, NOW(), NOW()
            FROM products
            WHERE product_code IS NOT NULL AND product_code != ''
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('product_codes');
    }
};
