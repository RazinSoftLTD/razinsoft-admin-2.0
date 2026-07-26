{{-- The test message. Deliberately plain, inline-styled, table-free: if this lands in spam the
     problem is the account or the DNS, not the markup. --}}
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>SMTP test</title></head>
<body style="margin:0;padding:24px;background:#f6f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;padding:28px">
        <h1 style="margin:0 0 12px;font-size:18px;color:#111827">SMTP is working ✅</h1>
        <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#4b5563">
            This test was sent from <strong>{{ config('app.name') }}</strong> through the
            <strong>{{ $config->name }}</strong> account.
        </p>
        <p style="margin:0;font-size:13px;line-height:1.9;color:#6b7280">
            Host: {{ $config->host }}:{{ $config->port }}<br>
            Encryption: {{ strtoupper($config->encryption ?: 'none') }}<br>
            From: {{ $config->from_email }}<br>
            Sent: {{ now()->format('d M Y, g:i A') }}
        </p>
    </div>
</body>
</html>
