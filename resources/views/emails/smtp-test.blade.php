{{--
  Body of the "Send test" message from Email Manager > SMTP Accounts.

  Its whole job is to prove one account works, so it names which account carried
  it. When several are configured, an arriving test is otherwise indistinguishable
  from any other, and the usual reason for sending one is that a particular
  account is suspect.

  Inline styles only and no images: this is the same constraint every template
  here works under, and a test that renders differently from real mail would not
  be testing much.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test email</title>
</head>
<body style="margin:0;padding:24px;background:#f7f7fb;
             font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;color:#1f2937">

    <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:14px;
                padding:28px 30px;box-shadow:0 1px 3px rgba(0,0,0,.06)">

        <h1 style="margin:0 0 6px;font-size:19px">Your SMTP account works</h1>
        <p style="margin:0 0 20px;color:#6b7280;font-size:14px;line-height:1.6">
            This message was sent from {{ config('app.name') }} to check that mail
            can actually leave the server. If you are reading it, delivery through
            this account is working.
        </p>

        <table role="presentation" cellpadding="0" cellspacing="0" width="100%"
               style="border-collapse:collapse;font-size:13px">
            <tbody>
                @foreach ([
                    'Account' => $config->name,
                    'Host' => $config->host.($config->port ? ':'.$config->port : ''),
                    'Encryption' => $config->encryption ?: 'none',
                    'From' => trim(($config->from_name ? $config->from_name.' ' : '').'<'.$config->from_email.'>'),
                    'Sent at' => now()->format('d M Y, g:i a'),
                ] as $label => $value)
                    <tr>
                        <td style="padding:7px 0;color:#6b7280;width:110px;vertical-align:top">{{ $label }}</td>
                        <td style="padding:7px 0;font-weight:600;word-break:break-all">{{ $value }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p style="margin:22px 0 0;padding-top:16px;border-top:1px solid #e5e7eb;
                  color:#9ca3af;font-size:12px;line-height:1.6">
            Sent from the SMTP Accounts screen. Nobody was added to a mailing list,
            and no reply is needed.
        </p>
    </div>

</body>
</html>
