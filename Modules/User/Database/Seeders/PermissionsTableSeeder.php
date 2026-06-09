<?php

namespace Modules\User\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            //User Mangement
            'edit_own_profile',
            'access_user_management',
            //Dashboard
            'show_total_stats',
            'show_month_overview',
            'show_weekly_sales_purchases',
            'show_monthly_cashflow',
            'show_notifications',
            //Products
            'access_products',
            'create_products',
            'show_products',
            'edit_products',
            'delete_products',
            //Product Categories
            'access_product_categories',
            'create_product_categories',
            'show_product_categories',
            'edit_product_categories',
            'delete_product_categories',
            //Product Subcategories
            'access_product_subcategories',
            'create_product_subcategories',
            'show_product_subcategories',
            'edit_product_subcategories',
            'delete_product_subcategories',
            //Barcode Printing (resource-specific)
            'print_product_barcodes',
            'print_rack_barcodes',
            'print_bin_barcodes',
            //Adjustments
            'access_adjustments',
            'create_adjustments',
            'show_adjustments',
            'edit_adjustments',
            'delete_adjustments',
            //Quotaions
            'access_quotations',
            'create_quotations',
            'show_quotations',
            'edit_quotations',
            'delete_quotations',
            //Create Sale From Quotation
            'create_quotation_sales',
            //Send Quotation On Email
            'send_quotation_mails',
            //Expenses
            'access_expenses',
            'create_expenses',
            'edit_expenses',
            'delete_expenses',
            //Expense Categories
            'access_expense_categories',
            //Customers
            'access_customers',
            'create_customers',
            'show_customers',
            'edit_customers',
            'update_customers',
            'delete_customers',
            //Suppliers
            'access_suppliers',
            'create_suppliers',
            'show_suppliers',
            'edit_suppliers',
            'delete_suppliers',
            //Sales
            'access_sales',
            'create_sales',
            'show_sales',
            'view_sales',
            'edit_sales',
            'delete_sales',
            //Send Sale On Email
            'send_sale_mails',
            // Sales Receipts
            'access_sales_receipts',
            'create_sales_receipts',
            'show_sales_receipts',
            'view_sales_receipts',
            'edit_sales_receipts',
            'delete_sales_receipts',
            //POS Sale
            'create_pos_sales',
            //Sale Payments
            'access_sale_payments',
            //Sale Returns
            'access_sale_returns',
            'create_sale_returns',
            'show_sale_returns',
            'edit_sale_returns',
            'delete_sale_returns',
            //Sale Return Payments
            'access_sale_return_payments',
            //Purchases
            'access_purchases',
            'create_purchases',
            'show_purchases',
            'view_purchases',
            'edit_purchases',
            'delete_purchases',
            //Purchase Payments
            'access_purchase_payments',
            //Purchases Receipts
            'access_purchases_receipts',
            'create_purchases_receipts',
            'show_purchases_receipts',
            'view_purchases_receipts',
            'edit_purchases_receipts',
            'delete_purchases_receipts',
            //Purchase Returns
            'access_purchase_returns',
            'create_purchase_returns',
            'show_purchase_returns',
            'edit_purchase_returns',
            'delete_purchase_returns',
            //Purchase Return Payments
            'access_purchase_return_payments',
            //Reports
            'access_reports',
            //Currencies
            'access_currencies',
            'create_currencies',
            'edit_currencies',
            'delete_currencies',
            //Settings
            'access_settings',
            //Units
            'access_units',
            'create_units',
            'edit_units',
            'delete_units',
            //Racks
            'access_racks',
            'create_racks',
            'show_racks',
            'edit_racks',
            'delete_racks',
            //Bins
            'access_bins',
            'create_bins',
            'show_bins',
            'edit_bins',
            'delete_bins'
        ];

        foreach ($permissions as $permission) {
            // Ensure permission exists for the web guard explicitly to avoid guard mismatch errors
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web'
            ]);
        }

        $role = Role::firstOrCreate([
            'name' => 'Admin'
        ]);

        $role->givePermissionTo($permissions);
        $role->revokePermissionTo('access_user_management');

        $superAdminRole = Role::firstOrCreate([
            'name' => 'Super Admin'
        ]);

        $superAdminRole->givePermissionTo($permissions);
    }
}
