<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RazinSoft — Smart Software for Growing Businesses</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--ink:#0f1e3d;--muted:#64748b;--line:#e6e9f2;--p:#4f5bd5;--p2:#3f49c0}
        html,body{height:100%}
        body{
            font-family:'Inter',ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;
            color:var(--ink);line-height:1.55;-webkit-font-smoothing:antialiased;
            background:linear-gradient(180deg,#f7f8fd 0%,#eef1fb 100%);
            position:relative;overflow-x:hidden}

        /* decorative layer */
        .deco{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none}
        .circle{position:absolute;border-radius:50%;background:radial-gradient(closest-side,rgba(99,116,231,.14),rgba(99,116,231,.03) 75%,transparent)}
        .cir-tr{width:520px;height:520px;top:-160px;right:-150px}
        .cir-bl{width:460px;height:460px;bottom:-170px;left:-160px}
        .dots{position:absolute;width:132px;height:120px;
            background-image:radial-gradient(circle,#b9c2ec 1.5px,transparent 1.7px);background-size:19px 19px;opacity:.6}
        .dots-tl{top:44px;left:44px}
        .dots-br{bottom:64px;right:56px}

        .wrap{position:relative;z-index:1;min-height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:56px 24px;text-align:center}
        .inner{width:100%;max-width:760px}

        @keyframes up{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
        .r{opacity:0;animation:up .6s cubic-bezier(.2,.7,.2,1) forwards}
        .r1{animation-delay:.02s}.r2{animation-delay:.09s}.r3{animation-delay:.16s}.r4{animation-delay:.23s}.r5{animation-delay:.30s}.r6{animation-delay:.37s}.r7{animation-delay:.46s}

        .logo img{height:42px;display:inline-block}

        .eyebrow{display:flex;align-items:center;justify-content:center;gap:14px;margin-top:26px;
            font-size:12px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#94a0b8}
        .eyebrow .ln{width:34px;height:1px;background:#cfd6e6}

        h1{margin-top:22px;font-size:clamp(34px,6vw,54px);line-height:1.08;font-weight:800;letter-spacing:-.03em;color:var(--ink)}
        h1 em{font-style:normal;background:linear-gradient(90deg,#4f5bd5,#7c3aed);-webkit-background-clip:text;background-clip:text;color:transparent}

        p.lead{margin:22px auto 0;max-width:560px;font-size:17px;line-height:1.7;color:#5c6b86}

        .chips{display:flex;flex-wrap:wrap;gap:12px;justify-content:center;margin-top:34px}
        .chip{display:inline-flex;align-items:center;gap:10px;padding:8px 16px 8px 8px;border-radius:999px;
            background:#fff;border:1px solid var(--line);box-shadow:0 3px 10px rgba(20,40,90,.05);
            font-size:14.5px;font-weight:600;color:#334063;transition:transform .15s ease,box-shadow .15s ease}
        .chip:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(20,40,90,.09)}
        .ic{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;flex:none}
        .ic svg{width:17px;height:17px}
        .ic-blue{background:#e8ebff;color:#4f5bd5}.ic-green{background:#dcfce7;color:#16a34a}
        .ic-purple{background:#ede9fe;color:#7c3aed}.ic-orange{background:#ffedd5;color:#ea9012}.ic-indigo{background:#e0e7ff;color:#4f46e5}

        .cta{display:flex;gap:16px;justify-content:center;margin-top:44px}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:16px 30px;border-radius:14px;
            font-size:15.5px;font-weight:700;text-decoration:none;transition:all .18s ease}
        .btn svg{width:19px;height:19px;flex:none;transition:transform .18s ease}
        .btn-web{background:linear-gradient(90deg,#4f5bd5,#5b6cf7);color:#fff;box-shadow:0 12px 26px -8px rgba(79,91,213,.65)}
        .btn-web:hover{transform:translateY(-2px);box-shadow:0 16px 34px -8px rgba(79,91,213,.7)}
        .btn-admin{background:#fff;color:var(--ink);border:1px solid #dfe3ee;box-shadow:0 4px 12px rgba(20,40,90,.05)}
        .btn-admin:hover{transform:translateY(-2px);border-color:#c9d0e2;box-shadow:0 10px 22px rgba(20,40,90,.09)}

        .foot{margin-top:56px;padding-top:26px;border-top:1px solid var(--line);font-size:13.5px;color:#8a97b0}
        .foot a{color:var(--p);text-decoration:none;font-weight:700}
        .foot a:hover{color:var(--p2)}

        @media (max-width:560px){
            .cta{flex-direction:column}.btn{width:100%}
            .dots{display:none}
            p.lead{font-size:15.5px}
        }
        @media (prefers-reduced-motion:reduce){.r{animation:none;opacity:1}}
    </style>
</head>
<body>
    <div class="deco">
        <span class="circle cir-tr"></span>
        <span class="circle cir-bl"></span>
        <span class="dots dots-tl"></span>
        <span class="dots dots-br"></span>
    </div>

    <div class="wrap">
        <div class="inner">
            <span class="logo r r1"><img src="{{ asset('razinsoft-logo.png') }}" alt="RazinSoft"></span>

            <div class="eyebrow r r2"><span class="ln"></span> Software · Products · Solutions <span class="ln"></span></div>

            <h1 class="r r3">Smart software for <em>growing businesses</em></h1>

            <p class="lead r r4">
                RazinSoft builds ready-made products and custom software — from POS &amp; inventory to
                eCommerce, LMS, booking and enterprise tools. Design, development, delivery &amp; support, all in one place.
            </p>

            <div class="chips r r5">
                <span class="chip"><span class="ic ic-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V4h12v5M6 18H5a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-1M8 15h8v5H8z"/></svg></span>POS &amp; Inventory</span>
                <span class="chip"><span class="ic ic-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="9" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/><path stroke-linecap="round" stroke-linejoin="round" d="M2 3h2.2l2.3 12.3a1.6 1.6 0 0 0 1.6 1.3h8.4a1.6 1.6 0 0 0 1.6-1.3L21 7H5.3"/></svg></span>eCommerce</span>
                <span class="chip"><span class="ic ic-purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M22 10 12 5 2 10l10 5 10-5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"/></svg></span>LMS</span>
                <span class="chip"><span class="ic ic-orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path stroke-linecap="round" d="M8 2.5v4M16 2.5v4M3 10h18"/></svg></span>Booking &amp; CRM</span>
                <span class="chip"><span class="ic ic-indigo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 8-4 4 4 4M15 8l4 4-4 4"/></svg></span>Custom Software</span>
            </div>

            <div class="cta r r6">
                <a class="btn btn-web" href="https://razinsoft.com" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
                    Visit Website
                </a>
                <a class="btn btn-admin" href="{{ route('admin.login') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                    Admin Panel
                </a>
            </div>

            <p class="foot r r7">© {{ date('Y') }} RazinSoft — <a href="https://razinsoft.com" target="_blank" rel="noopener">razinsoft.com</a></p>
        </div>
    </div>
</body>
</html>
