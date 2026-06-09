<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds indexes to improve query performance on frequently accessed columns.
     */
    public function up(): void
    {
        // Add indexes to sales table
        Schema::table('sales', function (Blueprint $table) {
            // Index on date for date range queries
            if (!$this->hasIndex('sales', 'sales_date_index')) {
                $table->index('date', 'sales_date_index');
            }
            // Index on customer_id for customer filtering
            if (!$this->hasIndex('sales', 'sales_customer_id_index')) {
                $table->index('customer_id', 'sales_customer_id_index');
            }
            // Index on status for filtering drafts
            if (!$this->hasIndex('sales', 'sales_status_index')) {
                $table->index('status', 'sales_status_index');
            }
            // Composite index for common listing queries
            if (!$this->hasIndex('sales', 'sales_date_status_index')) {
                $table->index(['date', 'status'], 'sales_date_status_index');
            }
        });

        // Add indexes to sales_details table
        Schema::table('sales_details', function (Blueprint $table) {
            // Index on sale_id for joins
            if (!$this->hasIndex('sales_details', 'sales_details_sale_id_index')) {
                $table->index('sale_id', 'sales_details_sale_id_index');
            }
            // Index on product_id for product queries
            if (!$this->hasIndex('sales_details', 'sales_details_product_id_index')) {
                $table->index('product_id', 'sales_details_product_id_index');
            }
        });

        // Add indexes to purchases table
        Schema::table('purchases', function (Blueprint $table) {
            // Index on date for date range queries
            if (!$this->hasIndex('purchases', 'purchases_date_index')) {
                $table->index('date', 'purchases_date_index');
            }
            // Index on supplier_id for supplier filtering
            if (!$this->hasIndex('purchases', 'purchases_supplier_id_index')) {
                $table->index('supplier_id', 'purchases_supplier_id_index');
            }
            // Index on status for filtering drafts
            if (!$this->hasIndex('purchases', 'purchases_status_index')) {
                $table->index('status', 'purchases_status_index');
            }
        });

        // Add indexes to purchase_details table
        Schema::table('purchase_details', function (Blueprint $table) {
            // Index on purchase_id for joins
            if (!$this->hasIndex('purchase_details', 'purchase_details_purchase_id_index')) {
                $table->index('purchase_id', 'purchase_details_purchase_id_index');
            }
            // Index on product_id for product queries
            if (!$this->hasIndex('purchase_details', 'purchase_details_product_id_index')) {
                $table->index('product_id', 'purchase_details_product_id_index');
            }
        });

        // Add indexes to products table
        Schema::table('products', function (Blueprint $table) {
            // Index on category_id for category filtering
            if (!$this->hasIndex('products', 'products_category_id_index')) {
                $table->index('category_id', 'products_category_id_index');
            }
            // Index on product_code for lookups
            if (!$this->hasIndex('products', 'products_product_code_index')) {
                $table->index('product_code', 'products_product_code_index');
            }
        });

        // Add indexes to customers table
        Schema::table('customers', function (Blueprint $table) {
            // Index on customer_name for search
            if (!$this->hasIndex('customers', 'customers_customer_name_index')) {
                $table->index('customer_name', 'customers_customer_name_index');
            }
        });

        // Add indexes to suppliers table  
        Schema::table('suppliers', function (Blueprint $table) {
            // Index on supplier_name for search
            if (!$this->hasIndex('suppliers', 'suppliers_supplier_name_index')) {
                $table->index('supplier_name', 'suppliers_supplier_name_index');
            }
        });
    }

    /**
     * Helper to check if an index exists.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $doctrine = $connection->getDoctrineSchemaManager();
        
        try {
            $indexes = $doctrine->listTableIndexes($table);
            return isset($indexes[$indexName]) || isset($indexes[strtolower($indexName)]);
        } catch (\Exception $e) {
            // If doctrine fails, try raw SQL for MySQL
            try {
                $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
                return count($result) > 0;
            } catch (\Exception $e2) {
                return false;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_date_index');
            $table->dropIndex('sales_customer_id_index');
            $table->dropIndex('sales_status_index');
            $table->dropIndex('sales_date_status_index');
        });

        Schema::table('sales_details', function (Blueprint $table) {
            $table->dropIndex('sales_details_sale_id_index');
            $table->dropIndex('sales_details_product_id_index');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex('purchases_date_index');
            $table->dropIndex('purchases_supplier_id_index');
            $table->dropIndex('purchases_status_index');
        });

        Schema::table('purchase_details', function (Blueprint $table) {
            $table->dropIndex('purchase_details_purchase_id_index');
            $table->dropIndex('purchase_details_product_id_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_category_id_index');
            $table->dropIndex('products_product_code_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_customer_name_index');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex('suppliers_supplier_name_index');
        });
    }
};
