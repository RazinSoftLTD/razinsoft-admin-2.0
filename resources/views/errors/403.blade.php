<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 — Access Restricted</title>
    <style>
        :root { --primary: #635bff; --primary-2: #8b7dff; --ink: #1e2233; --muted: #6b7280; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: radial-gradient(1200px 600px at 50% -10%, #eef0ff 0%, #f7f8fc 45%, #f4f5fa 100%);
            display: flex; align-items: center; justify-content: center;
            padding: 24px; color: var(--ink);
        }
        .card {
            position: relative; width: 100%; max-width: 560px; background: #fff;
            border: 1px solid #eef0f6; border-radius: 28px; padding: 48px 40px 40px;
            text-align: center; box-shadow: 0 30px 60px -25px rgba(60,50,140,.28);
            animation: rise .6s cubic-bezier(.2,.8,.2,1) both;
            overflow: hidden;
        }
        .card::before {
            content: ""; position: absolute; inset: 0; pointer-events: none;
            background:
                radial-gradient(140px 140px at 12% 8%, rgba(99,91,255,.10), transparent 70%),
                radial-gradient(160px 160px at 90% 4%, rgba(139,125,255,.10), transparent 70%);
        }
        .ghost {
            position: absolute; top: 6px; left: 0; right: 0; text-align: center;
            font-size: 210px; font-weight: 900; letter-spacing: 6px;
            color: rgba(99,91,255,.05); user-select: none; line-height: 1; z-index: 0;
        }
        .stage { position: relative; z-index: 1; height: 150px; margin-bottom: 8px; }
        .halo {
            position: absolute; left: 50%; top: 52%; width: 130px; height: 130px;
            transform: translate(-50%,-50%); border-radius: 50%;
            background: radial-gradient(circle, rgba(99,91,255,.16), transparent 65%);
            animation: pulse 2.6s ease-in-out infinite;
        }
        .lock { position: relative; z-index: 2; animation: floaty 3.2s ease-in-out infinite; }
        .shackle { transform-origin: 32px 26px; animation: jiggle 3.2s ease-in-out infinite; }
        .spark { animation: twinkle 2.2s ease-in-out infinite; transform-origin: center; }
        .spark.b { animation-delay: .7s; } .spark.c { animation-delay: 1.3s; }
        h1 { font-size: 24px; font-weight: 800; margin-bottom: 8px; letter-spacing: -.2px; }
        .code {
            display: inline-flex; align-items: center; gap: 7px; margin-bottom: 18px;
            padding: 5px 12px; border-radius: 999px; background: #f0efff; color: var(--primary);
            font-size: 12px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase;
        }
        .code .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--primary); animation: pulse 1.6s ease-in-out infinite; }
        p.msg { color: var(--muted); font-size: 15px; line-height: 1.6; max-width: 380px; margin: 0 auto 8px; }
        p.sub { color: #9aa1af; font-size: 13px; margin-bottom: 28px; }
        .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px; height: 46px; padding: 0 22px;
            border-radius: 14px; font-size: 14px; font-weight: 700; text-decoration: none;
            cursor: pointer; border: 1px solid transparent; transition: transform .12s ease, box-shadow .2s ease, background .2s ease;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-2)); color: #fff; box-shadow: 0 12px 24px -10px rgba(99,91,255,.7); }
        .btn-primary:hover { box-shadow: 0 16px 30px -10px rgba(99,91,255,.8); }
        .btn-ghost { background: #fff; border-color: #e6e8f0; color: var(--ink); }
        .btn-ghost:hover { background: #f7f8fc; }
        .hint { margin-top: 24px; font-size: 12px; color: #aab0bd; }
        @keyframes rise { from { opacity: 0; transform: translateY(16px) scale(.98); } to { opacity: 1; transform: none; } }
        @keyframes floaty { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-9px); } }
        @keyframes jiggle { 0%,88%,100% { transform: rotate(0); } 92% { transform: rotate(-9deg); } 96% { transform: rotate(6deg); } }
        @keyframes pulse { 0%,100% { transform: translate(-50%,-50%) scale(.9); opacity: .7; } 50% { transform: translate(-50%,-50%) scale(1.12); opacity: 1; } }
        @keyframes twinkle { 0%,100% { opacity: .25; transform: scale(.7); } 50% { opacity: 1; transform: scale(1.15); } }
        @media (prefers-reduced-motion: reduce) { * { animation: none !important; } }
    </style>
</head>
<body>
    <div class="card">
        <div class="ghost">403</div>

        <div class="stage">
            <span class="halo"></span>
            <svg class="lock" width="150" height="150" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- sparkles -->
                <g stroke="#8b7dff" stroke-width="2.4" stroke-linecap="round">
                    <path class="spark a" d="M10 16 v5 M7.5 18.5 h5"/>
                    <path class="spark b" d="M54 12 v4 M52 14 h4"/>
                    <path class="spark c" d="M55 34 v4 M53 36 h4"/>
                </g>
                <!-- shackle -->
                <path class="shackle" d="M20 30 v-6 a12 12 0 0 1 24 0 v6" stroke="#635bff" stroke-width="4.5" stroke-linecap="round"/>
                <!-- body -->
                <rect x="14" y="30" width="36" height="26" rx="7" fill="#635bff"/>
                <rect x="14" y="30" width="36" height="26" rx="7" fill="url(#g)"/>
                <!-- keyhole -->
                <circle cx="32" cy="41" r="4" fill="#fff"/>
                <rect x="30.4" y="43" width="3.2" height="8" rx="1.6" fill="#fff"/>
                <defs>
                    <linearGradient id="g" x1="14" y1="30" x2="50" y2="56" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#ffffff" stop-opacity=".18"/>
                        <stop offset="1" stop-color="#ffffff" stop-opacity="0"/>
                    </linearGradient>
                </defs>
            </svg>
        </div>

        <span class="code"><span class="dot"></span> Error 403 · Restricted</span>
        <h1>Hold up — this area is locked 🔒</h1>
        <p class="msg">You don’t have permission to open this section. Your role covers other parts of the workspace, not this one.</p>
        <p class="sub">If you think you should have access, ask your administrator to update your role.</p>

        <div class="actions">
            <a href="javascript:history.length > 1 ? history.back() : location.assign('/admin')" class="btn btn-ghost">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg>
                Go back
            </a>
            <a href="/admin" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9v11h14V9"/></svg>
                Back to Dashboard
            </a>
        </div>

        <p class="hint">RazinSoft Workspace · Access controlled by your role &amp; permissions</p>
    </div>
</body>
</html>
