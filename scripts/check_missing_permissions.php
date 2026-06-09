<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;

$seederPermissions = [
    'edit_own_profile','access_user_management',
    'show_total_stats','show_month_overview','show_weekly_sales_purchases','show_monthly_cashflow','show_notifications',
    'access_products','create_products','show_products','edit_products','delete_products',
    'access_product_categories','create_product_categories','show_product_categories','edit_product_categories','delete_product_categories',
    'access_product_subcategories','create_product_subcategories','show_product_subcategories','edit_product_subcategories','delete_product_subcategories',
    'print_product_barcodes','print_rack_barcodes','print_bin_barcodes',
    'access_adjustments','create_adjustments','show_adjustments','edit_adjustments','delete_adjustments',
    'access_quotations','create_quotations','show_quotations','edit_quotations','delete_quotations',
    'create_quotation_sales','send_quotation_mails',
    'access_expenses','create_expenses','edit_expenses','delete_expenses',
    'access_expense_categories',
    'access_customers','create_customers','show_customers','edit_customers','update_customers','delete_customers',
    'access_suppliers','create_suppliers','show_suppliers','edit_suppliers','delete_suppliers',
    'access_sales','create_sales','show_sales','view_sales','edit_sales','delete_sales','send_sale_mails',
    'access_sales_receipts','create_sales_receipts','show_sales_receipts','view_sales_receipts','edit_sales_receipts','delete_sales_receipts',
    'access_sale_returns','create_sale_returns','show_sale_returns','edit_sale_returns','delete_sale_returns',
    'access_sale_return_payments',
    'access_purchases','create_purchases','show_purchases','view_purchases','edit_purchases','delete_purchases',
    'access_purchase_payments',
    'access_purchases_receipts','create_purchases_receipts','show_purchases_receipts','view_purchases_receipts','edit_purchases_receipts','delete_purchases_receipts',
    'access_purchase_returns','create_purchase_returns','show_purchase_returns','edit_purchase_returns','delete_purchase_returns',
    'access_purchase_return_payments',
    'access_reports',
    'access_currencies','create_currencies','edit_currencies','delete_currencies',
    'access_settings',
    'access_units','create_units','edit_units','delete_units',
    'access_racks','create_racks','show_racks','edit_racks','delete_racks',
    'access_bins','create_bins','show_bins','edit_bins','delete_bins',
];

$dbPermissions = Permission::where('guard_name', 'web')->pluck('name')->toArray();
$missing = array_diff($seederPermissions, $dbPermissions);
$extra = array_diff($dbPermissions, $seederPermissions);

echo "=== MISSING FROM DB (in seeder but not in permissions table) ===\n";
if (empty($missing)) {
    echo "  None - all seeder permissions exist in DB.\n";
} else {
    foreach ($missing as $m) {
        echo "  - {$m}\n";
    }
}

echo "\n=== EXTRA IN DB (in permissions table but not in seeder) ===\n";
if (empty($extra)) {
    echo "  None.\n";
} else {
    foreach ($extra as $e) {
        echo "  - {$e}\n";
    }
}

echo "\nTotal in seeder: " . count($seederPermissions) . "\n";
echo "Total in DB: " . count($dbPermissions) . "\n";
echo "Missing: " . count($missing) . "\n";
echo "Extra: " . count($extra) . "\n";
