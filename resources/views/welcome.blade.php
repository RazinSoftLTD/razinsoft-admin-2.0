<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RazinSoft Marketing</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    {{-- MARKETING BRANCH: this deployment has no public website, so the product pitch and the
         "Visit Website" link are gone — this page only greets you and points at the panel. --}}
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--ink:#0f1e3d;--line:#e6e9f2;--p:#4f5bd5;--p2:#3f49c0}
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
        .inner{width:100%;max-width:640px}

        @keyframes up{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
        .r{opacity:0;animation:up .6s cubic-bezier(.2,.7,.2,1) forwards}
        .r1{animation-delay:.02s}.r2{animation-delay:.10s}.r3{animation-delay:.18s}.r4{animation-delay:.28s}.r5{animation-delay:.38s}

        .logo{display:inline-flex;align-items:center;gap:10px}
        .logo img{height:44px;width:44px;border-radius:12px;box-shadow:0 4px 12px rgba(20,40,90,.10)}
        .logo span{font-size:24px;font-weight:800;letter-spacing:-.02em}

        .eyebrow{display:flex;align-items:center;justify-content:center;gap:14px;margin-top:26px;
            font-size:12px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#94a0b8}
        .eyebrow .ln{width:34px;height:1px;background:#cfd6e6}

        h1{margin-top:22px;font-size:clamp(32px,5.5vw,50px);line-height:1.1;font-weight:800;letter-spacing:-.03em;color:var(--ink)}
        h1 em{font-style:normal;background:linear-gradient(90deg,#4f5bd5,#7c3aed);-webkit-background-clip:text;background-clip:text;color:transparent}

        .cta{display:flex;justify-content:center;margin-top:40px}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:16px 32px;border-radius:14px;
            font-size:15.5px;font-weight:700;text-decoration:none;transition:all .18s ease;
            background:linear-gradient(90deg,#4f5bd5,#5b6cf7);color:#fff;box-shadow:0 12px 26px -8px rgba(79,91,213,.65)}
        .btn:hover{transform:translateY(-2px);box-shadow:0 16px 34px -8px rgba(79,91,213,.7)}
        .btn svg{width:19px;height:19px;flex:none}

        .foot{margin-top:52px;padding-top:24px;border-top:1px solid var(--line);font-size:13.5px;color:#8a97b0}

        @media (max-width:560px){
            .btn{width:100%}
            .dots{display:none}
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
            {{-- razinsoft-logo.png was referenced here but has never existed in public/ (broken on
                 production too); this is the icon the sign-in page already uses. --}}
            <span class="logo r r1">
                <img src="{{ asset('images/razinsoft-icon.svg') }}" alt="">
                <span>RazinSoft</span>
            </span>

            <div class="eyebrow r r2"><span class="ln"></span> Marketing <span class="ln"></span></div>

            <h1 class="r r3">Welcome to <em>RazinSoft Marketing</em></h1>

            <div class="cta r r4">
                <a class="btn" href="{{ route('admin.login') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                    Admin Panel
                </a>
            </div>

            <p class="foot r r5">© {{ date('Y') }} RazinSoft</p>
        </div>
    </div>
</body>
</html>
