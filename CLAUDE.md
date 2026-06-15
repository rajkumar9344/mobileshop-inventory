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
| Customer | Removed: GST, PAN, Aadhaar, country, discounts, salesman. **Added: `vat_id`**. Removed `outstanding` Yes/No status flag + its auto-sync (2026-06-15) |
| Supplier | Removed: GST, country, bank details, style/type, additional discount% |
| Sale | Removed: HSN col, discount% col (DataTable), CGST, SGST, IGST, TCS%, adj, due date/days. "Net Rate" → "Total Amount". Balance = total − paid. Removed customer "Outstanding Bills are available" warning (2026-06-15) |
| Purchase (Type pricing) | Removed the supplier "Type" (1–4) pricing engine entirely — `purchase_type` column dropped from `purchases`/`purchase_returns`, `ProductCart::setPurchaseType()` + all type branching gone. Purchase Rate is now plain-editable (pre-fills `product_cost`) — one flow (2026-06-15) |
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

---

## Security Rules

**These rules apply to every feature, every modification, every session, every PR. Check all new and changed code against them. Never skip them.**

### Controllers

- **Explicit `$fillable`** — every new Model must declare a `$fillable` array. Never use `$guarded = []`. (Legacy entities still use `$guarded = []` — do not copy that pattern into new code.)
- **File path traversal** — always wrap any user-supplied filename with `basename()` before using it in a Storage call:
  ```php
  $filename = basename((string) $request->file_name);
  if (empty($filename) || !Storage::exists('temp/dropzone/' . $filename)) {
      return response()->json(['error' => 'File not found'], 404);
  }
  ```
- **File uploads** — always validate MIME type server-side. The dropzone endpoint (`Modules/Upload/Http/Controllers/UploadController.php`) enforces `file|image|mimes:png,jpeg,jpg,gif,webp|max:5120` — extend the whitelist deliberately, never remove it. Never move a temp file without checking `Storage::exists()` first.
- **Auth on every route** — all module routes must be inside the `auth` middleware group. AJAX endpoints must also use `abort_if(!auth()->check(), 401)`.
- **Permission on every route** — closure routes (PDF generation etc.) must check `abort_if(Gate::denies('...'), 403)` exactly like controller methods. This codebase uses `abort_if(Gate::denies(...), 403)` — NOT `$this->authorize()` (controllers don't use the `AuthorizesRequests` trait).
- **Folder names** — any user-controlled string used as a folder/path must match `/^[a-zA-Z0-9_\-]+$/` before use.
- **Password changes** — always require the current password before accepting a new one (`MatchCurrentPassword` rule). New passwords must use the complexity rule: `regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&\#])[A-Za-z\d@$!%*?&\#]+$/`.
- **Audit log sensitive actions** — call `AuditLogger::log()` for: user created/updated/deleted, role created/updated/deleted, password changed. See `app/Services/AuditLogger.php`; entries go to the `audit` log channel (`storage/logs/audit.log`, 90-day retention). Never pass passwords/tokens in the context array.

### Views (Blade)

- **Never use `{!! !!}` for user-supplied data** — always use `{{ }}` which auto-escapes HTML. The only allowed exceptions are DataTable rendering (`{!! $dataTable->table() !!}`), barcode SVG output from the trusted DNS1D facade, and values explicitly escaped with `e()` before concatenation.
- **CSRF** — every POST/PUT/DELETE form must have `@csrf`. Every AJAX mutation must send `X-CSRF-TOKEN` in headers. `VerifyCsrfToken::$except` must stay empty.
- **No inline secrets** — never embed DB credentials, API keys, or `.env` values directly in Blade templates or JS.

### File Attachments (Spatie Media Library pattern)

When adding attachments/images to any module, follow the Product module pattern exactly:

1. Model implements `HasMedia`, uses `InteractsWithMedia`, registers a media collection.
2. In `store()`: iterate `$request->input('document', [])`, call `basename()` on each filename, then `addMedia(Storage::path('temp/dropzone/' . $name))->toMediaCollection(...)` — only after the DB transaction commits (avoids orphaned files on rollback).
3. In `update()`: sync — delete media not present in the request, add new ones from temp.
4. In `destroy()`: media is cascade-deleted automatically by Spatie when the model is deleted — no manual cleanup.
5. In views: always `{{ $doc->file_name }}` (escaped), never `{!! !!}` on filenames.

### Configuration (never change these)

- `config/session.php`: `encrypt = true`, `same_site = strict`, `http_only = true`, `lifetime` default 60.
- `config/auth.php`: password reset `expire = 30`.
- `.env.example` must always show `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true` as defaults.
- Login route has `throttle:5,1` (LoginController constructor) — do not remove. Password reset email has `throttle:5,1` (ForgotPasswordController) — do not remove.
- `VerifyCsrfToken::$except` stays empty.

### Email — CRLF Injection Mitigation (REQUIRED)

- **Always use `email:rfc,strict` — never bare `email`** in validation rules. CVE-2026-48019 (CRLF injection in Laravel's default email rule) has no Laravel 10 patch; the strict rule is this app's mitigation and is documented in composer.json `audit.ignore`. Every new email field/endpoint must use `email:rfc,strict`.

### Dependencies

- Run `composer audit` and `npm audit` before every production deploy and after adding any new package.
- `composer audit` is expected to show exactly **1 ignored advisory** (CVE-2026-48019, see above). Anything else must be fixed.
- **No wildcard `*` versions in composer.json** — always pin to a caret range `^X.Y`.
- Do not add `laravel-mix` — intentionally removed (was the source of 30+ unfixable npm vulnerabilities). The project uses **Vite only** (vite 8 + laravel-vite-plugin 3).
- Do not add `axios` — intentionally removed (unused; carried 22 advisories).
- Do not add a package that shows advisories in `composer audit`/`npm audit` without reviewing and accepting the risk.

### Database Queries — SQL Injection Prevention

- **Always use Eloquent or the Query Builder with bindings.** Never concatenate user input into a raw query string.
- Allowed: `->where('name', $request->name)`, `->whereIn('id', $ids)`, `DB::select('... WHERE id = ?', [$id])`
- Forbidden: `DB::statement("... WHERE id = $id")`, `->whereRaw("name = '$name'")`
- If `whereRaw`/`selectRaw` is genuinely needed, always use bindings: `->whereRaw('amount > ?', [$min])`

### Input Validation — All User Input

- **Every public controller method that accepts user input must validate it** — via a Form Request class or inline `$request->validate([...])`, at the controller boundary, before the data touches any model or service.
- Reference the People module Form Requests (`Modules/People/Http/Requests/`) as the pattern — type, format, length, uniqueness.
- Never rely on frontend-only validation (JS, HTML `required`) as a security gate.

### Writing to `.env` / System Config

- Any value written to `.env` via `str_replace()` (e.g. SMTP settings) **must be sanitized first**:
  ```php
  $sanitize = fn($v) => str_replace(['"', "\n", "\r", "\0"], '', (string) $v);
  ```

### Null Safety

- Always null-check before accessing properties on a model fetched by a user-supplied ID. `->firstOrFail()` is acceptable when a 404 is the correct behavior. Never use `->first()->property` without checking.

### Authorization — IDOR / Broken Object-Level Access

- **Every `show`, `edit`, `update`, `destroy` method must verify permission** for the record being accessed (`abort_if(Gate::denies('show_sales'), 403)` etc.). A logged-in user must not be able to view or mutate records their role doesn't permit by changing an ID in the URL.
- Never rely on the route definition alone for access control — always check inside the controller/closure (the sales/purchases/quotations PDF routes are the reference pattern).

### Cookie / Session Security

- `config/session.php` must keep `'http_only' => true`. Set `SESSION_SECURE_COOKIE=true` in the production `.env` (HTTPS-only cookie).
- The `SecurityHeaders` middleware (`app/Http/Middleware/SecurityHeaders.php`, registered in the `web` group) sets X-Frame-Options, X-Content-Type-Options, Referrer-Policy, and Permissions-Policy — do not remove it.

### Subresource Integrity (SRI) for CDN Scripts

- Every `<script src="https://cdn...">` and `<link href="https://cdn...">` must have an `integrity` attribute and `crossorigin="anonymous"`. Without SRI, a compromised CDN silently serves malicious code to all users.

### File Storage Security

- **Never store user-uploaded files on the `public` disk** unless genuinely public (e.g. logos). Sensitive documents go on the `local` (private) disk via Spatie Media Library and are served through a controller that authorizes first, then `Storage::disk('local')->download($path)`.
- Never write exports, backups, or DB dumps to `public/` or `storage/app/public/`.

### Sensitive Data — Logging and API Responses

- **Never log passwords, tokens, or PII.** Use `$request->except(['password', 'password_confirmation'])` before any `Log::` call.
- **Never return a raw Eloquent model from an API/JSON response.** Use `->only([...])` or an API Resource. Keep `$hidden = ['password', 'remember_token']` on User and any model with secrets.
- **Never expose stack traces in production responses** — full exception goes to logs, not the response.

### Rate Limiting

- Login keeps `throttle:5,1` — do not remove. Apply the same to password reset email, email verification resend, and any OTP endpoint.

### Debug Tools in Production

- **Debugbar** — `require-dev` only; set `DEBUGBAR_ENABLED=false` in production `.env`.
- `APP_DEBUG=false` and `APP_ENV=production` in the production `.env` — debug mode exposes credentials in stack traces.

### Token and Secret Comparison

- **Never use `===`/`==` to compare tokens, API keys, or signatures.** Use `hash_equals($known, $user_supplied)` — constant-time, prevents timing attacks.

### Open Redirect Prevention

- **Never redirect to a URL taken from user input without validation.** Only allow same-domain relative paths (`str_starts_with($next, '/') && !str_starts_with($next, '//')`), or use `redirect()->intended('/dashboard')` for post-login redirects.

### Host Header Injection

- Keep `\App\Http\Middleware\TrustHosts` enabled in the global middleware stack. Always set `APP_URL` explicitly in `.env` and use `config('app.url')` when constructing absolute URLs — never `$request->getHost()`.
