# RazinSoft WhatsApp Gateway (Phase 1)

A small Node.js service that owns a WhatsApp Web (QR) session via **Baileys** and bridges it to the
Laravel admin. Laravel never touches Baileys directly — it talks to this gateway over HTTP through the
`BaileysProvider`. Swapping to the Meta Cloud API later is just a driver change in the admin, no rewrite.

```
WhatsApp phone ──(QR link)── Baileys gateway (this) ──HTTP──> Laravel admin inbox
                                     │  push inbound/status/connection events
                                     └──────────────────────────────────────────┘
```

## Run

```bash
cd whatsapp-gateway
cp .env.example .env      # then edit the values
npm install
npm start                 # keep it running (use pm2 in production)
```

Production (pm2):

```bash
pm2 start index.js --name wa-gateway
pm2 save
```

## Configure in the admin

1. **Settings → WhatsApp API** → Connection Method = **QR / WhatsApp Web**.
2. Set **Gateway URL** to where this service runs (e.g. `http://127.0.0.1:8090` or a reverse-proxied HTTPS URL).
3. Set **Gateway Secret** to the same value as `GATEWAY_SECRET` in this service's `.env`. Save.
4. **Messenger → WhatsApp Connection** → **Connect WhatsApp** → scan the QR from your phone
   (WhatsApp → Settings → Linked Devices → Link a Device).

Once linked, incoming messages appear in **Messenger → WhatsApp** in real time and agents can reply.

## HTTP API (called by Laravel, secret-guarded)

| Method | Path        | Purpose                                  |
|--------|-------------|------------------------------------------|
| GET    | `/status`   | `{ state, qr, number }`                  |
| POST   | `/connect`  | start/resume the session                 |
| POST   | `/logout`   | end the session & clear creds            |
| POST   | `/send`     | `{ to, type, text?, url?, caption?, filename? }` |

The gateway pushes to `LARAVEL_WEBHOOK` with events: `connection`, `message`, `status`.

## Notes

- The session is persisted in `SESSION_DIR` (multi-file auth). Keep it — it survives restarts and auto-reconnects.
- Both support agents use the **same** linked session (like WhatsApp Web on multiple tabs).
- This uses an unofficial library; use a dedicated WhatsApp Business number and follow WhatsApp's fair-use policy.
