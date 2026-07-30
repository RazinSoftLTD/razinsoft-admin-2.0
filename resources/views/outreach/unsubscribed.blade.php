{{--
  Opt-out confirmation. Standalone and dependency-free: it is opened from an
  email by someone who is not logged in, and it must render even if the admin
  asset build is broken.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $unknown ? 'Link not recognised' : 'You have been unsubscribed' }}</title>
    <style>
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
               background:#f6f8fb; color:#16232f;
               font:15px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
        .card { max-width:460px; margin:24px; padding:28px 30px; background:#fff;
                border:1px solid #e2e8f0; border-radius:12px; text-align:center; }
        h1 { margin:0 0 10px; font-size:19px; }
        p { margin:0 0 10px; color:#475569; }
        code { padding:2px 6px; background:#f1f5f9; border-radius:5px; font-size:13px; }
        .muted { margin-top:18px; font-size:13px; color:#94a3b8; }
    </style>
</head>
<body>
<div class="card">
    @if ($unknown)
        <h1>This link is not recognised</h1>
        <p>It may already have been used, or the address may have been removed from our records.</p>
        <p>You can reply to any message you received from us and we will remove your address by hand.</p>
    @else
        <h1>You have been unsubscribed</h1>
        @if ($email)
            <p><code>{{ $email }}</code> has been removed from our list.</p>
        @endif
        <p>You will not receive any further outreach from us at this address.</p>
    @endif

    <div class="muted">{{ config('app.name') }}</div>
</div>
</body>
</html>
