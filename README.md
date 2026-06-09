# Mobile Shop Inventory

A POS and inventory management system built for mobile phone shops.
Customised from the GoFast POS (Triangle POS) base by Redmind Technologies.

---

## Setup

```bash
composer install
npm install && npm run dev
cp .env.example .env
php artisan key:generate
# Create mobileshop_inventory DB on your MySQL server, then:
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Default admin: `super.admin@test.com` / `12345678`

---

## Features

- Product management (with comments, supplier name, alert qty, reorder level)
- Customer management (with VAT ID, credit limit)
- Supplier management (with due days, tax%, open balance)
- Sales & Purchase orders with return management
- Receipts and credit adjustments
- Stock adjustments
- Expense tracking
- Role-based user management (Spatie Permission)
- Reports: Sales, Purchase, Stock, Profit/Loss

---

## Differences from GoFast POS Base

| Module | Change |
|---|---|
| Rack Management | Removed entirely |
| Bin Management | Removed entirely |
| Product | Removed barcode, sub-category, MRP, HSN, rack/bin, image. "Compatibility" → "Comments" |
| Customer | Removed GST, PAN, Aadhaar, country, discounts, salesman. Added VAT ID |
| Supplier | Removed GST, country, bank details, type/style, additional discount% |
| Sales | Removed HSN, discounts, CGST, IGST, TCS, adj, Net Rate. Invoice redesigned (no India-specific fields) |
| Purchase | Removed same as Sales. Added draft save, payment status, reorder option |
| Sale Invoice | Redesigned: removed HSN/SGST/CGST columns, added Tax column, removed "Rupees" text, added paid/balance due rows |
| Migrations | Cleaned existing migrations: removed all India-specific columns from customers/suppliers; fixed after() references in bigint conversion migrations that referenced removed columns |
| Sale — product cart | Silicon mobile cover items have editable product name field |
| Reports | Added Profit/Loss report route. Renamed "Purchase Order Report" → "Current Stock Report". Removed GSTR from menu |

---

## Tech Stack

- Laravel 10 + NWIDART Modules
- Livewire v3
- Bootstrap 4 / CoreUI 3
- Yajra DataTables
- Spatie Permission + MediaLibrary
- Barryvdh DomPDF

---

## Developer Reference

See [CLAUDE.md](CLAUDE.md) for detailed file locations, completed changes, and pending work.

---

## License

Private — Redmind Technologies
