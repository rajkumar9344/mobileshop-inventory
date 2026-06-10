# CLAUDE.md — Mobile Shop Inventory

## Project

Mobile Shop Inventory — a POS/inventory system for a mobile phone shop, forked from **GoFast POS** (`D:\Redmind project\trianglepos`, branch `sornalatha`).

- Working dir: `D:\Redmind project\mobileshop-inventory`
- **Do NOT modify** the original GoFast project
- DB: `mobileshop_inventory` (MySQL port 3307, root, no password)
- Git remote: `https://github.com/rajkumar9344/mobileshop-inventory.git` (branch: `master`)
- DB is initialised: `migrate:fresh --seed` ran clean on 2026-06-09

---

## Tech Stack

Laravel 10 · NWIDART Modules · Livewire v3 · Bootstrap 4 / CoreUI 3 · Yajra DataTables · Spatie Permission · Barryvdh DomPDF · PHP 8.1+

---

## What Was Changed from GoFast

| Area | Changes |
|---|---|
| Rack, Bin modules | Deleted entirely (dirs, providers, routes, menu) |
| Product | Removed: barcode, sub-category, MRP, HSN, rack/bin, image upload, supplier field from form/view. "Compatibility" → "Comments" |
| Customer | Removed: GST, PAN, Aadhaar, country, discounts, salesman. **Added: `vat_id`** |
| Supplier | Removed: GST, country, bank details, style/type, additional discount% |
| Sale | Removed: HSN col, discount% col (DataTable), CGST, SGST, IGST, TCS%, adj, due date/days. "Net Rate" → "Total Amount". Balance = total − paid |
| Purchase | Removed same fields. Added: draft save redirect, reorder button, payment status badge in DataTable |
| Sale Return / Purchase Return | Removed same India fields (were already hidden-only) |
| Sale Invoice | Removed: HSN/SGST/CGST cols, "Rupees" text, vehicle fields. Added: Tax col, Paid row, Balance Due row |
| Purchase Invoice | Removed: HSN/SGST/CGST cols, supplier GST No. Added: Tax col (matches Sale invoice) |
| Product Cart | Silicon cover items get editable product name (`custom_product_names[]` in `ProductCart.php`) |
| Reports | Added Profit/Loss report. "Purchase Order Report" → "Current Stock Report". GSTR removed from menu. Outstanding reports use invoice `date` for aging (due_date removed from sales) |
| Migrations | All India-specific columns removed from migration files directly (pre-first-run). Bigint conversion migrations fixed to guard removed columns |

---

## Pending / Future Work

- [ ] Sales/Purchase receipt: credit adjustment against specific invoice (deferred — requirements unclear)

---

## Key Files

```
Modules/
  People/           — Customers & Suppliers (Controllers, Entities, Views, Requests)
  Product/          — Products
  Sale/             — Sales (invoice: Resources/views/partials/invoice.blade.php)
  Purchase/         — Purchases
  SaleReturn/       — Sales Returns
  PurchasesReturn/  — Purchase Returns
  SalesReceipt/     — Sales Receipts
  PurchasesReceipt/ — Purchase Receipts
  Reports/          — All reports
app/Livewire/ProductCart.php          — Shared cart component (sale/purchase/returns/quotation)
resources/views/livewire/product-cart.blade.php
resources/views/layouts/menu.blade.php   — Edit here to add/remove nav items
modules_statuses.json                 — Enable/disable modules
database/migrations/                  — App-level migrations
Modules/*/Database/Migrations/        — Module-level migrations
```

---

## Dev Rules

- Commit all features together, not one by one
- Edit existing migration files directly (don't create new DROP migrations) when DB is not yet initialised
- After deleting a module: run `composer dump-autoload`, remove its ServiceProvider from `config/app.php`, remove its route from `routes/web.php`, remove its nav block from `menu.blade.php`
- Currency inputs use `x-currency-input` Blade component; `prepareForValidation()` strips commas before validation
- Monetary amounts stored as bigint (paise ×100); models use `toMinor()` setters and `/ 100` getters
