# Installing SmartDesk

Five steps. A standard VPS or cPanel account is enough — PHP 8.3+, MySQL 8, Node 20+.

## 1. Files and dependencies

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

## 2. Database

Create an empty database, put its details in `.env`, then:

```bash
php artisan migrate
php artisan storage:link
```

## 3. Your first admin

```bash
php artisan smartdesk:admin
```

It asks for a name, an email and a password. There is no default account on purpose — a shipped
password is how installations get taken over in their first week.

## 4. Sign in and make it yours

Go to `/admin/login`, then **Settings → Branding**: your name, logo, icon and colour. Nothing here
needs a file edited or a rebuild.

Then, as you need them:

| Where | For |
|---|---|
| Settings → Invoice Configuration | your company name and logo on invoices |
| Settings → Email Settings | SMTP account, so the system can send |
| Settings → WhatsApp Config | QR or Meta Cloud API numbers |

## 5. Keep it running

One worker, for queued email and background jobs:

```bash
php artisan queue:work --tries=3 --timeout=120
```

Run it under Supervisor or PM2 so it restarts on its own. Without it, queued email waits forever.

---

## Preparing a copy to hand on

If you are shipping this to someone else, empty your own data first:

```bash
php artisan smartdesk:prepare-release
```

It lists every table and how many rows before deleting anything, and refuses to run in production
unless forced. It deliberately leaves three things to you:

- rotating the keys in `.env`
- clearing `storage/app/public` — uploaded logos, invoice PDFs, licence files
- creating the new owner's admin account
