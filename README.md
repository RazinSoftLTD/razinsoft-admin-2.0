# RazinSoft — Admin & API

Backend for the RazinSoft software marketplace. **Laravel 13** providing:

- **REST API** (Laravel Sanctum, Bearer tokens) consumed by the Nuxt website (`../website`).
- **Admin panel** — custom **Blade + Tailwind CSS 4 + Alpine.js** (Materio-style). _Filament was removed._

> Sibling project: `../website` (Nuxt 3 storefront). It reads this API via `NUXT_PUBLIC_API_BASE`.

---

## Local setup

- Served locally via **Herd** at **http://razinsoft.test** (or `php artisan serve`).
- MySQL database: `razinsoft` (root, no password).
- Admin panel: **/admin** → login `admin@razinsoft.com` / `password`.
- Seeded customer: `customer@razinsoft.com` / `password`.

```bash
composer install
php artisan migrate:fresh --seed     # schema + 6 demo products + users + coupons
php artisan storage:link             # public disk symlink (product images)
npm install && npm run build         # Tailwind/Vite assets  (npm run dev to watch)
```

---

## Useful commands

| Command | What it does |
|---|---|
| `php artisan documents:regenerate` | **Re-render ALL invoice + license PDFs in the current design.** Run after editing a PDF template. |
| `php artisan documents:regenerate --type=invoices` | Only invoices |
| `php artisan documents:regenerate --type=licenses` | Only licenses |
| `php artisan migrate:fresh --seed` | Reset DB + reseed (also writes placeholder source zips) |
| `php artisan storage:link` | Symlink `public/storage` → `storage/app/public` |
| `php artisan test` | Run the feature suite (26 tests) |
| `php artisan view:clear` | **Run this if you see a stale compiled-Blade 500** (e.g. after big template/design changes) |
| `npm run build` / `npm run dev` | Build / watch Tailwind + Vite assets |

---

## PDF documents (invoice + license)

- On a **paid order**, fulfilment generates an **Invoice PDF** + a **License certificate PDF** (per item) and stores them on the private disk.
- Templates:
  - Invoice → `resources/views/invoices/pdf.blade.php`
  - License → `resources/views/licenses/pdf.blade.php`
- Source code is a **zip** uploaded per product (`storage/app/private/sources/…`), gated behind a signed download.

**Design-change workflow:**
1. Edit the relevant `…/pdf.blade.php` template.
2. Run `php artisan documents:regenerate` → every existing PDF is rebuilt in the new design.
3. New orders use the new design automatically.

> **Trade-off chosen:** PDFs are **pre-generated & stored** (instant downloads). The cost is disk usage; the `documents:regenerate` command keeps old files in sync when the design changes. (Alternative: generate on-demand per download — zero storage but re-renders each time. Not used here.)

---

## Storage & git

Generated/uploaded files are **never committed** — Laravel's default ignores handle it:

| Files | Location | Ignored by |
|---|---|---|
| Invoices, licenses, source zips | `storage/app/private/` | `storage/app/private/.gitignore` (`* !.gitignore`) |
| Uploaded product images | `storage/app/public/` | `storage/app/public/.gitignore` |
| Symlink, build assets | `public/storage`, `public/build` | root `.gitignore` |

Nothing under those paths is tracked. Safe to `git init` without leaking customer documents.

---

## Admin structure (Blade)

- **Routes:** `routes/admin.php` (registered via `bootstrap/app.php` `then:` closure; prefix `/admin`, names `admin.*`).
- **Auth:** session guard — `app/Http/Controllers/Admin/Auth/LoginController.php`; `EnsureAdmin` middleware (alias `admin`, requires `role === 'admin'`).
- **Controllers:** `app/Http/Controllers/Admin/` — `Dashboard`, `Product`, `ProductRelation`, `Order`, `Coupon`, `User`.
- **Views:** `resources/views/admin/` — `layouts/app` shell + `partials/{sidebar,topbar}` + reusable `<x-admin.field / status / del-button / add-form>` components.
- **Products** → list + create + edit with **9 tabs** (General + Plans, Features, Gallery [groups + image upload], Tech, Suitable For, Docs, FAQs, Files [zip upload]); relations via `ProductRelationController` at `/admin/products/{p}/{relation}[/{id}]`.
- **Orders** → list + **manual order builder** (Alpine repeater → `OrderService::createFromCheckout` + `markPaid`, issues invoice + license) + view.
- **Dashboard** → TailAdmin-style analytics (KPI cards + ApexCharts area/bar/donut + recent orders / top products).

---

## API (Sanctum)

- Auth: `/api/auth/{register,login,me,logout}` (Bearer). `me` returns a resource → wrapped in `{ data: {…} }`.
- Public: `/api/products` (lean list, includes `from_plan` first-plan price), `/api/products/{slug}` (full detail + `seo`), `/api/categories`.
- Checkout: `POST /api/checkout` → returns Stripe **embedded** descriptor (`ui_mode: embedded_page`) or dev-pay URL; `POST /api/orders/{n}/repay`, `/confirm`; webhooks `/api/webhooks/{stripe,paypal}`.
- Account (auth): `/api/account/dashboard`, `/account/orders[/{n}]`, owner-gated `invoices/{i}/download`, `licenses/{l}/download`, signed `products/{p}/source`.

---

## Gotchas / notes

- **Stale Blade 500** (`Livewire\…\ExtendBlade` not found, or after big design edits) → `php artisan view:clear`.
- **Products have no standalone price** — pricing is per-**plan**. Cards show the first plan's price (`from_plan`). When adding nullable fields in a controller's `validated()`, guard absent keys (e.g. `$data['slug'] ?? ''` before `Str::slug()`).
- **License/Invoice are PDFs** — if older orders still have `.txt` licenses, `php artisan documents:regenerate` converts them.
- `NUXT_PUBLIC_API_BASE` in `../website/.env` must point at this app's host (e.g. `http://razinsoft.test/api`).
