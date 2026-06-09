# CLAUDE.md — Mobile Shop Inventory

Developer reference for AI-assisted work on this project.

## Project Overview

**Mobile Shop Inventory** is a customised POS/inventory management system built specifically for a mobile phone shop. It is a fork of the **GoFast POS** system (`D:\Redmind project\trianglepos`, branch `sornalatha`), adapted by removing India-specific fields and adding mobile-shop-specific features.

- Working directory: `D:\Redmind project\mobileshop-inventory`
- Original project: `D:\Redmind project\trianglepos` (branch: `sornalatha`) — **do not modify**
- App name: `Mobile Shop Inventory`
- Database: `mobileshop_inventory` on host `195.35.4.179`

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 10 |
| Module system | NWIDART Laravel Modules |
| Frontend | Bootstrap 4 / CoreUI 3 |
| Reactive UI | Livewire v3 |
| Tables | Yajra DataTables (server-side) |
| Auth/Permissions | Spatie Laravel Permission |
| Media | Spatie Laravel MediaLibrary |
| Export | Maatwebsite Excel |
| PDF | Barryvdh DomPDF |
| PHP | 8.1+ |

---

## Module Status

Modules are enabled/disabled via `modules_statuses.json` in the project root.

**Active modules:** Product, Upload, User, Adjustment, Expense, People, Currency, Setting, Sale, SalesReceipt, Purchase, SaleReturn, SalesReturn, PurchasesReturn, PurchasesReceipt, Quotation, Reports

**Removed modules (from GoFast):**
- `Rack` — module directory deleted, removed from `modules_statuses.json`, `config/app.php`, `routes/web.php`, and menu
- `Bin` — same as Rack

---

## Completed Customisations

### Phase 1 — Project Setup
- Copied GoFast codebase to new folder `mobileshop-inventory`
- Set `APP_NAME="Mobile Shop Inventory"`, `DB_DATABASE=mobileshop_inventory` in `.env`
- Initialised new git repo (`master` branch)

### Phase 2 — Module Removal
- **Rack module**: deleted `Modules/Rack/`, removed from `modules_statuses.json`, `config/app.php`, `routes/web.php`, menu
- **Bin module**: deleted `Modules/Bin/`, removed from `modules_statuses.json`, menu
- Removed Sub-category and Print Barcode from menu

### Phase 3 — Field Removals

#### Product (`Modules/Product`)
Removed: subcategory, barcode, equivalent product code, rack no, bin no, MRP, HSN, image upload (Dropzone)
Changed: "Compatibility" label → "Comments"
Added: Supplier Name moved to main form (row 2)
Files changed: `create.blade.php`, `edit.blade.php`, `ProductController.php`

#### Customer (`Modules/People` — customers)
Removed: country, gst_no, pan_no, aadhar_no, terms_days, cash_discount, additional_discount, discount_percent, salesman, lr_through
Added: `vat_id` field (varchar 20)
Files changed: `create.blade.php`, `edit.blade.php`, `StoreCustomerRequest.php`, `UpdateCustomerRequest.php`, `Customer.php`, `CustomersController.php`
Migration: `database/migrations/2026_06_09_155808_add_vat_id_to_customers_table.php`
**DB status:** `php artisan migrate:fresh --seed` completed successfully on 2026-06-09

#### Supplier (`Modules/People` — suppliers)
Removed: country, gst_no, bank_name, account_no, ifsc, branch, style (Type dropdown), less_discount_percent (additional discount%)
Files changed: `create.blade.php`, `edit.blade.php`, `StoreSupplierRequest.php`, `UpdateSupplierRequest.php`, `Supplier.php`

#### Sale (`Modules/Sale`)
Removed: due_date, due_days, HSN column, Discount %, Additional Discount %, Cash Discount Amount, Net Rate (row-level), CGST, SGST, IGST, TCS%, Other (+/-), Adj (overall section); "Discount Amount" payment field removed
Renamed: "Net Rate" → "Total Amount" in Overall Calculations section
Balance formula: `balance = total_amount - paid_amount` (no discount subtraction)
`days` validation: always nullable (was required for non-draft)
Files changed: `resources/views/livewire/product-cart.blade.php`, `Modules/Sale/Resources/views/create.blade.php`, `Modules/Sale/Resources/views/edit.blade.php`, `Modules/Sale/Http/Requests/StoreSaleRequest.php`

#### Purchase (`Modules/Purchase`)
Removed: due_date, due_days visible fields (kept as hidden inputs); removed updateDueDate() JS; removed supplier discount auto-apply from supplier change handler; discount_amount was already in commented-out block so not visible
Balance formula: `balance = total_amount - paid_amount` (no discount)
`days` validation: always nullable in both Store and Update requests
Files changed: `Modules/Purchase/Resources/views/create.blade.php`, `Modules/Purchase/Resources/views/edit.blade.php`, `Modules/Purchase/Http/Requests/StorePurchaseRequest.php`, `Modules/Purchase/Http/Requests/UpdatePurchaseRequest.php`

Note: SalesReturn and PurchasesReturn had no visible due_date/days/discount UI — their related fields were already hidden-only.

---

## Migration Cleanup (COMPLETE ✓)

All People module migration files were edited directly (DB not yet initialised — no DROP migrations needed).

**Customer migrations cleaned:**
- `create_customers_table` — removed `country`
- `add_more_fields_to_customers_table` — removed `gst_no`, `pan_no`, `aadhar_no`, `lr_through`, `cash_discount`, `less_discount`, `discount_percent`, `terms_days`, `salesman`
- `update_customers_table` — removed `less_discount→additional_discount` rename block, `discount_percent` MODIFY block, `salesman` MODIFY block

**Supplier migrations cleaned:**
- `create_suppliers_table` — removed `country`
- `add_new_fields_to_suppliers_table` — removed `gst_no`, `bank_name`, `account_no`, `branch`, `ifsc`, `less_discount_percent`; fixed `after()` refs (`open_balance` → `after('address')`, `status` → `after('tax_percent')`)
- `make_supplier_email_nullable` — removed `style` column addition
- `add_due_days_to_suppliers_table` (app-level) — fixed `after('less_discount_percent')` → `after('tax_percent')`

**Show views cleaned:**
- `customers/show.blade.php` — removed Country, GST, PAN, Aadhaar, discounts, salesman, Del.Mode; added VAT ID
- `suppliers/show.blade.php` — removed Country, GST, bank details, style, discount%

### Bigint Conversion Migration Fixes (COMPLETE ✓)

The bigint conversion migrations (added before the India-field cleanup) referenced removed columns in `->after()` and `DB::statement()` calls. Fixed during `migrate:fresh` on 2026-06-09:

**Files fixed:**
- `2026_01_23_132000_convert_sales_details_decimals_to_bigint_final.php` — removed `->after('hsn')` (hsn removed from sales_details); prevented `cash_discount_amount` re-creation in step 5
- `2026_01_27_000002_add_create_receipt_to_sale_returns.php` — changed `->after('overall_net_rate')` → `->after('overall_amount')`
- `2026_02_12_171205_convert_purchase_details_amounts_to_bigint.php` — guarded `cash_discount_amount_temp` creation; removed `->after('discount_percent')` / `->after('cash_discount_percent')`
- `2026_02_12_171537_convert_purchase_return_details_amounts_to_bigint.php` — same pattern as purchase_details
- `2026_02_12_174808_convert_sale_return_details_remaining_amounts_to_bigint.php` — guarded `cash_discount_amount_temp`; removed bad `->after()` refs
- `2026_02_12_173413_convert_purchase_returns_amounts_to_bigint.php` — comprehensive fix: guarded all `overall_cgst/sgst/igst/other/adj/net_rate` temp columns and their UPDATEs; fixed `overall_amount_temp ->after('overall_tcs_percent')` → `->after('overall_tax_amount_temp')`
- `2026_02_12_174326_convert_sale_returns_amounts_to_bigint.php` — guarded `overall_tcs_percent_temp` creation and its UPDATE

**Pattern:** For each removed column X: `&& Schema::hasColumn(table, 'X')` guard on temp column creation; `if (Schema::hasColumn(table, 'X'))` guard on UPDATE statement; `->after('removed_col')` changed to valid existing column or removed.

---

## Pending Work

### Phase 3 — COMPLETE ✓
- [x] Sales: remove due date, due days, HSN, all discount%, CGST, IGST, TCS%, other, adj; renamed "Net Rate" → "Total Amount"; UpdateSaleRequest days always nullable
- [x] Purchase: remove due date, due days from UI (kept as hidden); removed updateDueDate JS; removed supplier discount auto-apply; days always nullable in Store+Update requests
- [x] Purchase Return: fields already hidden-only — no UI changes needed
- [x] Sales Return: fields already hidden-only — no UI changes needed

### Phase 5 — New Features (COMPLETE ✓)
- [x] Purchase: save as draft redirect, reorder button, payment status in DataTable
- [x] Sales: editable product name for silicon mobile cover items
- [x] Invoice redesign: removed India-specific fields (HSN, SGST, CGST columns, "Rupees" text, vehicle fields, GST No); added Tax column, paid/balance due rows, "Thank you" footer
- [x] Reports: Profit/Loss report route added and accessible from menu; "Purchase Order Report" renamed to "Current Stock Report"; GSTR report removed from menu
- [ ] Sales/Purchase receipt: credit adjustment against specific invoice — DEFERRED (requirements unclear)

#### Purchase Phase 5 detail
- **Save as Draft**: clicking "Save as Draft" button now auto-saves via AJAX and redirects to purchases.index (was staying on page)
- **Reorder**: `GET /purchases/{purchase}/reorder` → creates a new Draft purchase with same supplier/items, redirects to edit for review/confirmation. Button shown in actions dropdown for non-Draft purchases (requires create_purchases permission)
- **Payment Status**: DataTable now shows Payment Status badge (Paid/Partial/Unpaid) column; removed CGST, SGST, Discount columns (always 0)
- Files: `Routes/web.php`, `Http/Controllers/PurchaseController.php`, `Resources/views/partials/actions.blade.php`, `DataTables/PurchaseDataTable.php`, `Resources/views/create.blade.php`

#### Sale Invoice Phase 5 detail
- **Redesigned** `Modules/Sale/Resources/views/partials/invoice.blade.php`
- Removed: HSN column, SGST column, CGST column, Vehicle Name/No, "GST NO:" always-on label, "Rupees X only" amount-in-words
- Added: single "Tax" column, Paid row, Balance Due row (only if > 0), "Thank you for your purchase!" footer
- VAT No shown conditionally if `settings()->company_gst` is set
- Customer phone and VAT ID shown if present

#### Sale — Editable Product Name Phase 5 detail
- Silicon mobile cover items show an editable text input in the product cart (create/edit sale)
- `wire:model.blur` bound to `custom_product_names[rowId]` Livewire property
- `updatedCustomProductNames()` calls `Cart::update()` to persist the new name to the cart so controllers receive the custom name
- Initialized on mount (edit mode) and on `productSelected()` (new additions)
- File: `app/Livewire/ProductCart.php`, `resources/views/livewire/product-cart.blade.php`

#### Reports Phase 5 detail
- Route added: `GET /profit-loss-report` → `ReportsController@profitLossReport` → `reports::profit-loss.index`
- Menu: Profit/Loss Report added at top of reports list; "Purchase Order Report" renamed "Current Stock Report"; GSTR Report removed
- Files: `Modules/Reports/Routes/web.php`, `Modules/Reports/Http/Controllers/ReportsController.php`, `resources/views/layouts/menu.blade.php`

---

## Key File Locations

```
app/                        — Core Laravel app
Modules/                    — NWIDART modules (each has Controllers, Entities, Views, Requests)
  People/                   — Customers & Suppliers
  Product/                  — Products & Categories
  Sale/                     — Sales
  Purchase/                 — Purchases
  SaleReturn/               — Sales Returns
  PurchasesReturn/          — Purchase Returns
  SalesReceipt/             — Sales Receipts
  PurchasesReceipt/         — Purchase Receipts
  Reports/                  — All reports
resources/views/layouts/
  menu.blade.php            — Main navigation menu (edit here to show/hide nav items)
  app.blade.php             — Main layout
modules_statuses.json       — Enable/disable modules
config/app.php              — Service providers (remove deleted module providers here)
routes/web.php              — Root-level routes (module routes are in each module)
database/migrations/        — App-level migrations (module migrations are in Modules/*/Database/Migrations/)
```

---

## Development Notes

- Always run `composer dump-autoload` after deleting a module directory
- Always remove deleted module's `ServiceProvider` from `config/app.php`
- Always remove deleted module's route from `routes/web.php`
- Always remove deleted module's nav block from `resources/views/layouts/menu.blade.php`
- Bootstrap cache may need clearing after module deletion: delete `bootstrap/cache/packages.php` and `bootstrap/cache/services.php`
- Validation requests use `prepareForValidation()` to sanitise currency inputs (strip commas) before rule execution
- Currency inputs use `x-currency-input` Blade component with a hidden field for raw value
- Phone number auto-fills customer code on the customer create form (JS in blade)
