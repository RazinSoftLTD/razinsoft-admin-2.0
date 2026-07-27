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

1. **Real screenshots.** The hero is a drawn illustration (`tools/gen-smartdesk-hero.mjs`). It
   carries nobody's data, which is why it is there — but real screens sell better. Take them from a
   demo install, never from production.
2. **A demo install.** Buyers expect a live preview: its own database, seeded with invented data.

Everything else that used to be on this list is now two commands — see [install.md](install.md):

```bash
php artisan smartdesk:prepare-release   # empty the vendor's data before shipping
php artisan smartdesk:admin             # the buyer's first account
```

## Running the generators

```bash
node tools/gen-smartdesk-mark.mjs    # the SmartDesk icon and wordmark
node tools/gen-smartdesk-hero.mjs    # the website hero illustration
```

Both need `sharp`, which is resolved from the website repo's `node_modules`.
