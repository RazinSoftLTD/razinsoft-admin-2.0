# WhatsApp numbers

Each number chooses its own transport, so a scanned WhatsApp Web number and a Meta Cloud API
number can be connected at the same time. It used to be one setting for the whole installation —
switching it silently took every other number offline.

## The two drivers

| | QR / WhatsApp Web (`baileys`) | Meta Cloud API (`cloud_api`) |
|---|---|---|
| Pairing | Scan a QR from the phone | Verify credentials with Meta |
| Needs | The gateway service running | A verified business number in Meta |
| Credentials | Gateway URL + secret (shared) | Phone Number ID + Access Token, **per number** |
| Inbound | Gateway posts to `/api/whatsapp/gateway` | Meta posts to `/api/whatsapp/webhook` |
| Session | Stays paired; can drop | Nothing to hold open |

## Adding a Cloud API number

**Settings → WhatsApp → WhatsApp Numbers → Add a WhatsApp number**, choose *Meta Cloud API*, then
fill in from Meta ▸ WhatsApp ▸ API Setup:

- **Phone Number ID** and **WhatsApp Business Account ID**
- **Permanent Access Token** — a System User token, not the temporary 24-hour one
- **App Secret** — optional but recommended; without it an incoming webhook cannot be proved to
  have come from Meta

Save, then **Verify**. That asks Meta whether the credentials work and reads back the number they
answer for, which is what appears in the list.

Finally, in Meta ▸ WhatsApp ▸ Configuration set the Callback URL and Verify Token shown on the
number's edit panel, and subscribe to the **messages** field. Every number has its own verify
token, so several can share the one callback URL — Meta names the `phone_number_id` in each event
and the webhook routes on that.

## The 24-hour window

Meta refuses a plain message more than 24 hours after the customer last wrote. Only an approved
Message Template may be sent until they reply.

The panel handles this rather than letting the send fail:

- Opening a chat asks whether the window is closed (`GET .../templates`).
- If it is, a note appears above the reply box and the approved templates for that number are
  offered. Fill any `{{1}}`, `{{2}}` placeholders, and the preview shows what the customer will
  read — that text is what goes in the thread, not the template's name.
- Sending a plain message anyway is refused with the same explanation instead of leaving a failed
  message in the thread.
- Our own replies do not reopen the window. Only the customer writing does.

Templates come from Meta ▸ WhatsApp ▸ Message Templates and must be **approved** there; the picker
lists nothing else. They are read from the WhatsApp Business Account, so a number without a
**Business Account ID** has no templates to offer. The list is cached for ten minutes.

## What the Cloud API cannot do

Meta's API simply has no equivalent for some of what a paired phone can do. These raise a clear
error rather than failing quietly:

- Editing or deleting a sent message
- Anything to do with groups — reading members, renaming, changing the picture

## Where the code is

- `WhatsappManager::provider()` — picks the transport for one account
- `CloudApiProvider` / `BaileysProvider` — the two implementations of `WhatsappProvider`
- `WhatsappService::for($account)` — what business logic actually calls
- `Api\WhatsappWebhookController` — Meta inbound, routed by `phone_number_id`
- `Api\WhatsappGatewayController` — gateway inbound, routed by session key
