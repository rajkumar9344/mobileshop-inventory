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
        Schema::table('products', function (Blueprint $table) {
            // Add subcategory_id foreign key
            if (!Schema::hasColumn('products', 'subcategory_id')) {
                $table->unsignedBigInteger('subcategory_id')->nullable()->after('category_id');
                $table->foreign('subcategory_id')->references('id')->on('subcategories')->nullOnDelete();
            }
            // Remove old subcategory string field if exists
            if (Schema::hasColumn('products', 'subcategory')) {
                $table->dropColumn('subcategory');
            }
            $table->string('alternative_number')->nullable()->after('product_code');
            $table->string('hsn')->nullable()->after('bin_no');
            $table->decimal('mrp', 10, 2)->nullable()->after('hsn');
            $table->string('location')->nullable()->after('mrp');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('location');
            $table->integer('re_order')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['alternative_number', 'hsn', 'mrp', 'location', 'status', 're_order']);
        });
    }
};