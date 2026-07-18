<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RazinSoft — Smart Software for Growing Businesses</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{
            --p:#5b6cf7; --p2:#7c3aed; --pink:#ec4899; --cyan:#06b6d4; --orange:#f59e0b; --green:#22c55e;
            --ink:#0b1020;
        }
        html,body{height:100%}
        body{
            font-family:'Inter',ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;
            color:var(--ink);
            background:#070a16;
            overflow-x:hidden;
            -webkit-font-smoothing:antialiased;
        }
        .bg{position:fixed;inset:0;z-index:0;overflow:hidden;background:
            radial-gradient(1200px 600px at 15% -10%, rgba(91,108,247,.35), transparent 60%),
            radial-gradient(1000px 700px at 110% 10%, rgba(236,72,153,.28), transparent 55%),
            radial-gradient(900px 700px at 50% 120%, rgba(6,182,212,.28), transparent 55%),
            #070a16;}
        .blob{position:absolute;border-radius:50%;filter:blur(60px);opacity:.55;animation:float 16s ease-in-out infinite}
        .b1{width:420px;height:420px;background:var(--p);top:-80px;left:-60px}
        .b2{width:380px;height:380px;background:var(--pink);top:20%;right:-90px;animation-delay:-4s}
        .b3{width:340px;height:340px;background:var(--cyan);bottom:-100px;left:25%;animation-delay:-8s}
        .b4{width:300px;height:300px;background:var(--orange);bottom:5%;right:15%;animation-delay:-12s}
        @keyframes float{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(30px,-30px) scale(1.08)}}

        .wrap{position:relative;z-index:1;min-height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 20px}
        .card{
            width:100%;max-width:920px;
            background:rgba(255,255,255,.06);
            -webkit-backdrop-filter:blur(18px);backdrop-filter:blur(18px);
            border:1px solid rgba(255,255,255,.12);
            border-radius:28px;
            padding:48px 44px;
            box-shadow:0 30px 80px rgba(0,0,0,.45);
            text-align:center;color:#fff;
        }
        .logo{display:inline-flex;align-items:center;justify-content:center;background:#fff;border-radius:18px;padding:12px 18px;box-shadow:0 10px 30px rgba(91,108,247,.35)}
        .logo img{height:40px;display:block}
        .badge{display:inline-block;margin-top:22px;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
            background:linear-gradient(90deg,rgba(91,108,247,.25),rgba(236,72,153,.25));border:1px solid rgba(255,255,255,.18);color:#e7e9ff}
        h1{margin-top:18px;font-size:44px;line-height:1.08;font-weight:800;letter-spacing:-.02em}
        h1 .grad{background:linear-gradient(90deg,#8ea2ff,#c084fc,#f472b6,#f59e0b);-webkit-background-clip:text;background-clip:text;color:transparent}
        p.lead{margin:18px auto 0;max-width:620px;font-size:17px;line-height:1.6;color:rgba(255,255,255,.78)}

        .chips{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-top:26px}
        .chip{font-size:12.5px;font-weight:600;padding:7px 13px;border-radius:999px;border:1px solid rgba(255,255,255,.16);color:#fff}
        .c1{background:rgba(91,108,247,.22)} .c2{background:rgba(236,72,153,.22)} .c3{background:rgba(6,182,212,.22)}
        .c4{background:rgba(245,158,11,.22)} .c5{background:rgba(34,197,94,.22)} .c6{background:rgba(124,58,237,.22)}

        .cta{display:flex;flex-wrap:wrap;gap:14px;justify-content:center;margin-top:34px}
        .btn{display:inline-flex;align-items:center;gap:10px;padding:15px 26px;border-radius:14px;font-size:15px;font-weight:700;text-decoration:none;transition:transform .15s ease,box-shadow .15s ease,filter .15s ease}
        .btn:hover{transform:translateY(-2px)}
        .btn svg{width:18px;height:18px}
        .btn-web{background:linear-gradient(90deg,#5b6cf7,#7c3aed);color:#fff;box-shadow:0 12px 30px rgba(91,108,247,.45)}
        .btn-web:hover{filter:brightness(1.08);box-shadow:0 16px 40px rgba(91,108,247,.55)}
        .btn-admin{background:#fff;color:#0b1020;box-shadow:0 12px 30px rgba(0,0,0,.3)}
        .btn-admin:hover{filter:brightness(.97)}

        .foot{margin-top:30px;font-size:12.5px;color:rgba(255,255,255,.5)}
        .foot a{color:rgba(255,255,255,.8);text-decoration:none;font-weight:600}
        .tag{position:relative;z-index:1;margin-top:22px;color:rgba(255,255,255,.55);font-size:12px}

        @media (max-width:640px){
            .card{padding:34px 22px;border-radius:22px}
            h1{font-size:32px}
            p.lead{font-size:15px}
            .btn{width:100%;justify-content:center}
        }
    </style>
</head>
<body>
    <div class="bg">
        <span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span><span class="blob b4"></span>
    </div>

    <div class="wrap">
        <div class="card">
            <span class="logo"><img src="{{ asset('razinsoft-logo.png') }}" alt="RazinSoft"></span>

            <span class="badge">Software · Products · Solutions</span>

            <h1>Smart software for<br><span class="grad">growing businesses</span></h1>

            <p class="lead">
                RazinSoft builds ready-made business products and custom software that help companies
                run smarter — from POS &amp; inventory to eCommerce, learning platforms, booking systems
                and complete enterprise tools. One team, end-to-end: design, development, delivery &amp; support.
            </p>

            <div class="chips">
                <span class="chip c1">POS &amp; Inventory</span>
                <span class="chip c2">eCommerce</span>
                <span class="chip c3">LMS</span>
                <span class="chip c4">Booking &amp; CRM</span>
                <span class="chip c5">Custom Software</span>
                <span class="chip c6">Installation &amp; Support</span>
            </div>

            <div class="cta">
                <a class="btn btn-web" href="https://razinsoft.com" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
                    Visit Website
                </a>
                <a class="btn btn-admin" href="{{ route('admin.login') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                    Admin Panel
                </a>
            </div>

            <p class="foot">Need help? Reach us at <a href="https://razinsoft.com" target="_blank" rel="noopener">razinsoft.com</a></p>
        </div>

        <p class="tag">© {{ date('Y') }} RazinSoft. All rights reserved.</p>
    </div>
</body>
</html>
