// One-off: draws the SmartDesk mark.
//
// A desk seen from above with a screen on it — literal enough to read at 32px in a sidebar, which
// is the size it spends its life at. The gradient is the same blue→violet the panel already uses
// for its primary colour, so the mark belongs to the UI rather than sitting on top of it.
import sharp from '/Users/shofikulislam/Codes/razinsoft-website-2.0/node_modules/sharp/lib/index.js';
import { writeFileSync } from 'node:fs';

const OUT = '/Users/shofikulislam/Codes/razinsoft-admin-2.0/public/images';

const GRAD = `<linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="#3aa6ff"/><stop offset="1" stop-color="#7b4bff"/>
  </linearGradient>`;

// The mark itself, on a transparent square. Used alone as the app icon.
const icon = (size = 200) => `
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="${size}" height="${size}">
    <defs>${GRAD}</defs>
    <rect width="200" height="200" rx="46" fill="url(#g)"/>
    <!-- screen -->
    <rect x="52" y="46" width="96" height="66" rx="9" fill="#fff"/>
    <rect x="64" y="60" width="42" height="7" rx="3.5" fill="#7b4bff" opacity=".55"/>
    <rect x="64" y="74" width="60" height="7" rx="3.5" fill="#3aa6ff" opacity=".45"/>
    <rect x="64" y="88" width="30" height="7" rx="3.5" fill="#7b4bff" opacity=".3"/>
    <!-- stand -->
    <rect x="92" y="112" width="16" height="14" fill="#fff" opacity=".92"/>
    <!-- desk -->
    <rect x="34" y="126" width="132" height="13" rx="6.5" fill="#fff"/>
    <rect x="52" y="139" width="9" height="22" rx="4.5" fill="#fff" opacity=".8"/>
    <rect x="139" y="139" width="9" height="22" rx="4.5" fill="#fff" opacity=".8"/>
  </svg>`;

// Wordmark: the icon plus "SmartDesk", for the sign-in screen and anywhere with room.
const wordmark = `
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 760 180" width="760" height="180">
    <defs>${GRAD}</defs>
    <g transform="translate(0 10) scale(0.8)">
      <rect width="200" height="200" rx="46" fill="url(#g)"/>
      <rect x="52" y="46" width="96" height="66" rx="9" fill="#fff"/>
      <rect x="64" y="60" width="42" height="7" rx="3.5" fill="#7b4bff" opacity=".55"/>
      <rect x="64" y="74" width="60" height="7" rx="3.5" fill="#3aa6ff" opacity=".45"/>
      <rect x="64" y="88" width="30" height="7" rx="3.5" fill="#7b4bff" opacity=".3"/>
      <rect x="92" y="112" width="16" height="14" fill="#fff" opacity=".92"/>
      <rect x="34" y="126" width="132" height="13" rx="6.5" fill="#fff"/>
      <rect x="52" y="139" width="9" height="22" rx="4.5" fill="#fff" opacity=".8"/>
      <rect x="139" y="139" width="9" height="22" rx="4.5" fill="#fff" opacity=".8"/>
    </g>
    <text x="196" y="118" font-family="Instrument Sans, Segoe UI, Helvetica, Arial, sans-serif"
          font-size="86" font-weight="800" fill="#1e2735">Smart<tspan fill="url(#g)">Desk</tspan></text>
  </svg>`;

writeFileSync(`${OUT}/smartdesk-icon.svg`, icon().trim());
writeFileSync(`${OUT}/smartdesk-logo.svg`, wordmark.trim());

// PNGs for anywhere SVG is awkward (PDFs, email).
await sharp(Buffer.from(icon(512))).png({ compressionLevel: 9 }).toFile(`${OUT}/smartdesk-icon.png`);
await sharp(Buffer.from(wordmark), { density: 200 }).resize(1520).png({ compressionLevel: 9 })
  .toFile(`${OUT}/smartdesk-logo.png`);

console.log('wrote smartdesk-icon.svg/.png and smartdesk-logo.svg/.png');
