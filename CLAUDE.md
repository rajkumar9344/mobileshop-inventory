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
**DB action required:** Create `mobileshop_inventory` DB and run `php artisan migrate`

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

## Pending Work

### Phase 3 — COMPLETE ✓
- [x] Sales: remove due date, due days, HSN, all discount%, CGST, IGST, TCS%, other, adj; renamed "Net Rate" → "Total Amount"; UpdateSaleRequest days always nullable
- [x] Purchase: remove due date, due days from UI (kept as hidden); removed updateDueDate JS; removed supplier discount auto-apply; days always nullable in Store+Update requests
- [x] Purchase Return: fields already hidden-only — no UI changes needed
- [x] Sales Return: fields already hidden-only — no UI changes needed

### Phase 5 — New Features
- [ ] Purchase: reorder/repurchase option, save as draft, payment status
- [ ] Sales: editable product name for silicon mobile cover only
- [ ] Sales/Purchase receipt: credit adjustment against specific invoice
- [ ] Reports: Profit/Loss (monthly/per invoice/per customer), Current Stock
- [ ] Invoice/Purchase redesign as customer's invoice format

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
