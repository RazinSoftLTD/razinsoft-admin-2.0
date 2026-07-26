<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unsubscribed</title>
    <style>
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background:#f7f7fb; color:#1f2937; }
        .card { max-width: 460px; margin: 12vh auto; background:#fff; border-radius:16px; padding:40px 32px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,.06); }
        .tick { width:52px; height:52px; margin:0 auto 18px; border-radius:50%; background:#ecfdf5; color:#059669; display:grid; place-items:center; font-size:26px; }
        h1 { font-size:20px; margin:0 0 8px; }
        p { margin:0; color:#6b7280; line-height:1.6; font-size:14px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="tick">✓</div>
        <h1>You've been unsubscribed</h1>
        <p>
            @if ($email)
                <strong>{{ $email }}</strong> has been removed from our mailing list.
            @else
                That address has been removed from our mailing list.
            @endif
            You will no longer receive marketing email from us.
        </p>
    </div>
</body>
</html>
