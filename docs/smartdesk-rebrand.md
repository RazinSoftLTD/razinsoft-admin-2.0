# SmartDesk — where the rebrand stands

Branch `SmartDesk` in both repos, branched from `main` and **not merged**. `main` is untouched and
still what production runs.

## Done

### Admin panel
- `config/brand.php` carries the shipped identity: product, tagline, vendor, marks.
- **Settings → Branding** overrides it at runtime — name, tagline, wordmark, icon, colour. One
  colour picker; the hover and tint shades are derived so they cannot drift apart.
- The three things that all used to read "RazinSoft" are now told apart:

  | | Comes from | Seen in |
  |---|---|---|
  | the software | `BrandSetting::productName()` | sidebar, sign-in, page titles, favicon |
  | the operator | `InvoiceSetting::brand_name` | invoices, licences, exports |
  | the vendor | `config('brand.vendor')` | "SmartDesk by RazinSoft" |

  That third distinction was a real bug: invoices and licence certificates were printing the
  vendor's name on documents belonging to whoever runs the software.

### Website
- Home page rebuilt as a product page: what it replaces, the eight modules, why owning beats
  renting, the stack, two licences, and the six questions a buyer asks before clicking buy.
- About Us is about the product now, including a deliberate "what it is not" list.
- Careers and Life at RazinSoft removed — they belong to the agency site.
- Header, footer and marks are SmartDesk; the footer credits RazinSoft as the maker.

## Still to do

**A hosted demo.** Buyers expect a live preview at a URL. The data for it already exists — see
below — so what is left is somewhere to put it.

## Running the generators

```bash
node tools/gen-smartdesk-mark.mjs    # the SmartDesk icon and wordmark
node tools/gen-smartdesk-hero.mjs    # the fallback hero illustration
```

Both need `sharp`, resolved from the website repo's `node_modules`.

## The demo, and the screenshots

Screenshots on the site come from a demo database, never from a live install — that is the whole
reason the demo exists. Every name, company and figure in it is invented.

```bash
# 1. an empty database of its own; the real one is never touched
touch /tmp/demo.sqlite
DB_CONNECTION=sqlite DB_DATABASE=/tmp/demo.sqlite php artisan migrate --force
DB_CONNECTION=sqlite DB_DATABASE=/tmp/demo.sqlite php artisan email:seed-templates
DB_CONNECTION=sqlite DB_DATABASE=/tmp/demo.sqlite php artisan smartdesk:demo-seed

# 2. serve it
DB_CONNECTION=sqlite DB_DATABASE=/tmp/demo.sqlite php artisan serve --port 8099

# 3. capture — writes into the website repo's public/images/screens
node tools/gen-screens.mjs
```

`smartdesk:demo-seed` refuses to run against a database that already has people in it. The capture
script drives Chrome over the DevTools protocol rather than pulling in Playwright — it runs once,
and a 300MB download to take six pictures is not a trade worth making.

Sign in to the demo as `ariana@smartdesk.example` / `demo1234`.
