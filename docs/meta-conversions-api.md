# Meta Conversions API

The server-side half of the Facebook pixel. The browser pixel loses a large share of events to ad
blockers, tracking prevention and tabs closed mid-request; these are sent from here instead, and
carry the customer's real email and phone, which is what Meta matches on.

Settings → **Meta Conversions API**.

## Setting it up

1. **Events Manager** (business.facebook.com/events_manager) → pick your dataset → **Settings**.
2. Copy the **Dataset ID** (the pixel id) into the panel.
3. Same page → **Conversions API** → **Generate access token** → paste it in.
4. Optional but recommended for the first run: Events Manager → **Test Events** gives a code. While
   that code is filled in, every event is flagged as test data and stays out of real reporting.
   **Clear it when you are done** or nothing will ever be counted for real.
5. Turn **Send events to Meta** on, save, then **Send test event** and watch it arrive in Test Events.

## What gets sent

| Event | When | Event ID |
|---|---|---|
| `Purchase` | An order is fulfilled | `order-<order number>` |
| `Lead` | Contact form submitted | `contact-<id>` |
| `Lead` | Meeting booked | `meeting-<id>` |
| `CompleteRegistration` | Customer creates an account | `signup-<user id>` |

Each can be switched off on its own.

## Deduplication — do not skip this

The pixel already fires in the browser through GTM. If it sends a conversion the server also
sends, Meta counts it **twice** unless both carry the same `event_id`.

The ids above are deterministic on purpose. Set the matching GTM tag's Event ID to the same value —
for Purchase that means the order number, prefixed with `order-`.

Get this wrong and reported revenue is double the real figure, which is worse than not sending
server events at all.

## Privacy

Email, phone, name, city, country and the customer id are SHA-256 hashed before they leave, as
Meta requires — lower-cased and trimmed first, phone reduced to digits with the country code. Raw
values never go over the wire; there is a test asserting exactly that.

The visitor's `_fbp` / `_fbc` cookies and IP are passed through when the event came from a browser
request. They do more for match quality than anything else, and they are the pixel's own cookies —
nothing new is collected for this.

## When something is wrong

The settings screen shows whether the last event was accepted, and Meta's own words when it was
not. Common causes:

- **Invalid OAuth token** — the token was generated for a different dataset, or revoked.
- **Nothing appears in Events Manager** — check the Test Event Code matches the one currently shown
  there; codes rotate.
- **Everything counted twice** — the `event_id` on the GTM tag does not match the table above.

A failure is recorded and logged, never thrown. Tracking must not be able to break a purchase, a
lead or a signup.
