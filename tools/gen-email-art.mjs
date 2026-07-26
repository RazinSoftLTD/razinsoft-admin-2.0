// One-off: rasterises the welcome-email artwork to PNG.
//
// Email clients do not render SVG (Gmail in particular drops it entirely), so every graphic in the
// template has to ship as a PNG. Drawn at 2x and displayed at 1x so it stays sharp on phones.
//
// Run from the website repo so `sharp` resolves:
//   node gen-email-art.mjs
import sharp from '/Users/shofikulislam/Codes/razinsoft-website-2.0/node_modules/sharp/lib/index.js';
import { mkdirSync, writeFileSync } from 'node:fs';

const OUT = '/Users/shofikulislam/Codes/razinsoft-admin-2.0/public/images/email';
mkdirSync(`${OUT}/social`, { recursive: true });

const BLUE = '#1a6dff';
const NAVY = '#0f172a';
const INK = '#1e293b';

const svg = (w, h, inner) =>
    `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}">${inner}</svg>`;

async function png(name, markup, w, h, scale = 2) {
    const buf = Buffer.from(svg(w, h, markup));
    await sharp(buf, { density: 72 * scale })
        .resize(w * scale, h * scale)
        .png({ compressionLevel: 9 })
        .toFile(`${OUT}/${name}.png`);
    console.log(`  ${name}.png  ${w * scale}x${h * scale}`);
}

// ---------------------------------------------------------------- social icons
// Paths lifted verbatim from the website footer so the marks match the site exactly.
const SOCIAL = {
    facebook: 'M24 12.07C24 5.41 18.63 0 12 0S0 5.41 0 12.07c0 6.02 4.39 11.01 10.13 11.93v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.08 24 18.09 24 12.07Z',
    instagram: 'M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.43.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.43.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.72 3.72 0 0 1-1.38-.9 3.72 3.72 0 0 1-.9-1.38c-.16-.43-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.43-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16M12 0C8.74 0 8.33.01 7.05.07 5.78.13 4.9.33 4.14.63c-.79.31-1.46.72-2.13 1.38A5.9 5.9 0 0 0 .63 4.14C.33 4.9.13 5.78.07 7.05.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.06 1.27.26 2.15.56 2.91.31.79.72 1.46 1.38 2.13.67.66 1.34 1.07 2.13 1.38.76.3 1.64.5 2.91.56C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c1.27-.06 2.15-.26 2.91-.56a5.9 5.9 0 0 0 2.13-1.38 5.9 5.9 0 0 0 1.38-2.13c.3-.76.5-1.64.56-2.91.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.06-1.27-.26-2.15-.56-2.91a5.9 5.9 0 0 0-1.38-2.13A5.9 5.9 0 0 0 19.86.63c-.76-.3-1.64-.5-2.91-.56C15.67.01 15.26 0 12 0Zm0 5.84A6.16 6.16 0 1 0 18.16 12 6.16 6.16 0 0 0 12 5.84Zm0 10.16A4 4 0 1 1 16 12a4 4 0 0 1-4 4Zm6.41-11.85a1.44 1.44 0 1 0 1.44 1.44 1.44 1.44 0 0 0-1.44-1.44Z',
    linkedin: 'M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.13 1.44-2.13 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.45v6.29ZM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14ZM7.12 20.45H3.56V9h3.56v11.45ZM22.22 0H1.77C.8 0 0 .78 0 1.73v20.53C0 23.22.8 24 1.77 24h20.45c.98 0 1.78-.78 1.78-1.73V1.73C24 .78 23.2 0 22.22 0Z',
    youtube: 'M23.5 6.19a3.02 3.02 0 0 0-2.12-2.14C19.5 3.55 12 3.55 12 3.55s-7.5 0-9.38.5A3.02 3.02 0 0 0 .5 6.19C0 8.07 0 12 0 12s0 3.93.5 5.81a3.02 3.02 0 0 0 2.12 2.14c1.88.5 9.38.5 9.38.5s7.5 0 9.38-.5a3.02 3.02 0 0 0 2.12-2.14C24 15.93 24 12 24 12s0-3.93-.5-5.81ZM9.6 15.6V8.4l6.25 3.6L9.6 15.6Z',
};

console.log('social icons');
for (const [name, path] of Object.entries(SOCIAL)) {
    const inner =
        `<circle cx="18" cy="18" r="18" fill="${NAVY}"/>` +
        `<g transform="translate(9 9) scale(0.75)"><path d="${path}" fill="#ffffff"/></g>`;
    const buf = Buffer.from(svg(36, 36, inner));
    await sharp(buf, { density: 144 }).resize(72, 72).png({ compressionLevel: 9 })
        .toFile(`${OUT}/social/${name}.png`);
    console.log(`  social/${name}.png  72x72`);
}

// ---------------------------------------------------------------- line icons
const stroke = (d, color = BLUE, w = 1.8) =>
    `<path d="${d}" fill="none" stroke="${color}" stroke-width="${w}" stroke-linecap="round" stroke-linejoin="round"/>`;

console.log('line icons');
// Account-detail rows
await png('icon-user', stroke('M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4.5 20a7.5 7.5 0 0 1 15 0'), 24, 24);
await png('icon-mail', stroke('M3 6h18v12H3zM3 7l9 6 9-6'), 24, 24);
await png('icon-calendar', stroke('M4 6h16v14H4zM4 10h16M8 3v4M16 3v4'), 24, 24);
// Avatar tile beside "Your Account Details"
await png('icon-avatar',
    `<circle cx="22" cy="22" r="22" fill="#e3edff"/>` +
    `<circle cx="22" cy="18" r="6.5" fill="none" stroke="${BLUE}" stroke-width="2"/>` +
    `<path d="M11.5 34a10.5 10.5 0 0 1 21 0" fill="none" stroke="${BLUE}" stroke-width="2" stroke-linecap="round"/>`,
    44, 44);
// Contact row
await png('icon-mail-sm', stroke('M3 6h18v12H3zM3 7l9 6 9-6'), 24, 24);
await png('icon-globe', stroke('M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9S14.5 18.4 12 21c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3Z'), 24, 24);

// Feature-card icons, each on its own tinted rounded tile
await png('icon-box',
    `<rect width="48" height="48" rx="12" fill="#e3edff"/>` +
    `<g transform="translate(12 12)">${stroke('M12 2 21.5 7v10L12 22 2.5 17V7L12 2ZM2.5 7 12 12l9.5-5M12 12v10')}</g>`,
    48, 48);
await png('icon-code',
    `<rect width="48" height="48" rx="12" fill="#dcf5e8"/>` +
    `<g transform="translate(12 12)">${stroke('m9 8-5 4 5 4M15 8l5 4-5 4', '#10a37f')}</g>`,
    48, 48);

// The "</>" badge that floats over the hero
await png('badge-code',
    `<rect width="56" height="56" rx="14" fill="${BLUE}"/>` +
    `<g transform="translate(16 16)">${stroke('m8 6-5 6 5 6M16 6l5 6-5 6M13.5 5l-3 14', '#ffffff', 2.2)}</g>`,
    56, 56);

// Support headset, on the pale disc it sits on in the help panel
await png('icon-support',
    `<circle cx="36" cy="36" r="36" fill="#ffffff"/>` +
    `<g transform="translate(18 18)">` +
    stroke('M4 22v-8a14 14 0 0 1 28 0v8', BLUE, 2.4) +
    `<rect x="2" y="20" width="8" height="12" rx="4" fill="${BLUE}"/>` +
    `<rect x="26" y="20" width="8" height="12" rx="4" fill="${BLUE}"/>` +
    stroke('M32 32a8 8 0 0 1-8 6h-4', BLUE, 2.4) +
    `</g>`,
    72, 72);

// ---------------------------------------------------------------- hero artwork
// The dashboard mock beside the greeting. Flat shapes only — it has to read at 260px wide.
console.log('hero');
const hero = `
  <defs>
    <linearGradient id="chart" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="#7fb0ff"/><stop offset="1" stop-color="${BLUE}"/>
    </linearGradient>
  </defs>
  <circle cx="405" cy="150" r="130" fill="#dbe8ff"/>
  <g fill="#c7d9ff">
    ${Array.from({ length: 5 }, (_, r) =>
        Array.from({ length: 6 }, (_, c) => `<circle cx="${18 + c * 18}" cy="${40 + r * 18}" r="2.6"/>`).join('')
    ).join('')}
  </g>

  <!-- back card -->
  <rect x="120" y="60" width="330" height="215" rx="16" fill="#ffffff"/>
  <rect x="120" y="60" width="330" height="26" rx="16" fill="#f1f5f9"/>
  <rect x="120" y="74" width="330" height="12" fill="#f1f5f9"/>
  <circle cx="140" cy="73" r="4.5" fill="#ff6b5e"/>
  <circle cx="156" cy="73" r="4.5" fill="#ffc12e"/>
  <circle cx="172" cy="73" r="4.5" fill="#3ecf6a"/>

  <!-- sidebar -->
  <rect x="60" y="88" width="72" height="200" rx="14" fill="${NAVY}"/>
  <circle cx="96" cy="118" r="15" fill="#ffffff"/>
  <circle cx="96" cy="114" r="5" fill="${NAVY}"/>
  <path d="M88 126a8 8 0 0 1 16 0Z" fill="${NAVY}"/>
  ${[160, 196, 232].map(y => `<rect x="86" y="${y}" width="20" height="20" rx="6" fill="#ffffff" opacity="0.28"/>`).join('')}
  <rect x="88" y="266" width="16" height="10" rx="4" fill="${BLUE}"/>

  <!-- content rows -->
  <circle cx="168" cy="112" r="13" fill="#e2e8f0"/>
  <rect x="190" y="104" width="86" height="7" rx="3.5" fill="#e2e8f0"/>
  <rect x="190" y="117" width="54" height="6" rx="3" fill="#eef2f7"/>
  <rect x="380" y="106" width="34" height="6" rx="3" fill="#eef2f7"/>
  <rect x="422" y="106" width="12" height="6" rx="3" fill="#eef2f7"/>

  <rect x="152" y="140" width="112" height="34" rx="8" fill="#f4f7fb"/>
  <rect x="278" y="140" width="112" height="34" rx="8" fill="#f4f7fb"/>

  <!-- chart panel -->
  <rect x="152" y="188" width="238" height="76" rx="10" fill="#f8fafc"/>
  <path d="M168 244c20-8 28-28 44-28s24 24 42 20 28-34 48-34 24 16 40 12"
        fill="none" stroke="url(#chart)" stroke-width="4" stroke-linecap="round"/>
  <rect x="140" y="196" width="18" height="7" rx="3.5" fill="${BLUE}"/>

  <!-- code badge, sitting on the card's right edge -->
  <rect x="404" y="128" width="56" height="56" rx="14" fill="${BLUE}"/>
  <path d="M423 145l-9 11 9 11M441 145l9 11-9 11M434 143l-4 26" fill="none" stroke="#ffffff"
        stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"/>

  <!-- floating bar-chart card -->
  <rect x="368" y="196" width="94" height="72" rx="14" fill="#ffffff"/>
  ${[[386, 28], [402, 40], [418, 20], [434, 46]].map(([x, h]) =>
      `<rect x="${x}" y="${250 - h}" width="10" height="${h}" rx="3" fill="${BLUE}"/>`).join('')}
`;
await png('hero-welcome', hero, 500, 300, 2);

writeFileSync(`${OUT}/README.txt`,
    'Generated by scratchpad/gen-email-art.mjs — email artwork must be PNG, no client renders SVG.\n');
console.log('done');
