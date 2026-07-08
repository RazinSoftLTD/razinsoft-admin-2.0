<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <div style="max-width:520px;margin:0 auto;padding:32px 20px;">
        <div style="background:#ffffff;border-radius:12px;padding:32px;">
            <h1 style="margin:0 0 8px;font-size:20px;color:#111827;">Verify your email</h1>
            <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#4b5563;">
                Hi {{ $user->name }}, please confirm this is your email address for your RazinSoft account.
            </p>
            <a href="{{ $url }}"
               style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:8px;">
                Verify email address
            </a>
            <p style="margin:22px 0 0;font-size:12px;line-height:1.6;color:#9ca3af;">
                This link expires in 1 hour. If you didn’t request this, you can safely ignore this email.
            </p>
        </div>
        <p style="text-align:center;margin:18px 0 0;font-size:12px;color:#9ca3af;">© RazinSoft</p>
    </div>
</body>
</html>
