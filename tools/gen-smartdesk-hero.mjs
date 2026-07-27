// The homepage hero illustration: the SmartDesk panel, drawn rather than screenshotted.
// A real screenshot would either show a customer's data or an empty demo — this reads as the
// product at a glance and carries no one's records. Replace before listing if real shots exist.
import sharp from '/Users/shofikulislam/Codes/razinsoft-website-2.0/node_modules/sharp/lib/index.js';

const OUT = '/Users/shofikulislam/Codes/razinsoft-website-2.0/public/images/smartdesk-hero.png';
const W = 1000, H = 640;

const bar = (x, y, w, h, fill, r = 4) => `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${r}" fill="${fill}"/>`;
const card = (x, y, w, h) => `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="14" fill="#fff"/>`;

const svg = `
<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">
  <defs>
    <linearGradient id="acc" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#3aa6ff"/><stop offset="1" stop-color="#7b4bff"/>
    </linearGradient>
    <linearGradient id="line" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="#8fc0ff"/><stop offset="1" stop-color="#5b6cf7"/>
    </linearGradient>
  </defs>

  <rect width="${W}" height="${H}" rx="18" fill="#f4f5fa"/>

  <!-- sidebar -->
  <rect x="0" y="0" width="212" height="${H}" rx="18" fill="#141b26"/>
  <rect x="194" y="0" width="18" height="${H}" fill="#141b26"/>
  <rect x="26" y="30" width="30" height="30" rx="9" fill="url(#acc)"/>
  ${bar(66, 38, 84, 13, '#ffffff', 6)}
  ${[96,136,176,216,256,296,336,376].map((y,i) =>
    i === 1
      ? `<rect x="18" y="${y-9}" width="176" height="34" rx="9" fill="url(#acc)"/>${bar(38,y,14,14,'#ffffff',4)}${bar(62,y+2,92,10,'#ffffff',5)}`
      : `${bar(38,y,14,14,'#4a5666',4)}${bar(62,y+2,88,10,'#39424f',5)}`
  ).join('')}

  <!-- topbar -->
  <rect x="212" y="0" width="${W-212}" height="64" fill="#ffffff"/>
  ${bar(244, 26, 112, 13, '#2b3444', 6)}
  ${bar(W-150, 24, 34, 17, '#eef1f6', 8)}
  ${bar(W-104, 22, 22, 22, '#e6eaf2', 11)}
  ${bar(W-70, 22, 46, 22, '#e6eaf2', 11)}

  <!-- stat tiles -->
  ${[0,1,2,3].map(i => {
    const x = 240 + i*182;
    return card(x, 92, 162, 92)
      + bar(x+18, 112, 26, 26, '#eef1ff', 8)
      + bar(x+18, 150, 62, 9, '#c9d0dc', 5)
      + bar(x+18, 166, 40, 12, '#2b3444', 6)
      + `<rect x="${x+112}" y="${152}" width="34" height="16" rx="8" fill="#e8f7ef"/>`;
  }).join('')}

  <!-- revenue chart -->
  ${card(240, 204, 526, 236)}
  ${bar(266, 228, 118, 12, '#2b3444', 6)}
  ${bar(266, 248, 78, 9, '#c9d0dc', 5)}
  <path d="M270 396c46-14 66-70 108-70s60 62 104 50 66-92 116-92 58 40 96 30"
        fill="none" stroke="url(#line)" stroke-width="6" stroke-linecap="round"/>
  ${[0,1,2,3,4,5].map(i => bar(272 + i*82, 412, 44, 8, '#e9edf4', 4)).join('')}

  <!-- side panel -->
  ${card(786, 204, 190, 236)}
  ${bar(810, 228, 84, 12, '#2b3444', 6)}
  ${[0,1,2,3].map(i => bar(810, 258 + i*44, 142, 30, '#f2f5fa', 9)).join('')}

  <!-- lower cards -->
  ${card(240, 460, 344, 156)}
  ${bar(266, 484, 96, 12, '#2b3444', 6)}
  ${[0,1,2].map(i => `${bar(266, 512 + i*30, 22, 22, '#eef1ff', 11)}${bar(298, 518 + i*30, 176, 10, '#dfe4ec', 5)}`).join('')}

  ${card(604, 460, 372, 156)}
  ${bar(630, 484, 112, 12, '#2b3444', 6)}
  ${[46,74,58,88,64].map((h,i) => bar(646 + i*64, 588 - h, 34, h, i === 3 ? 'url(#acc)' : '#e3e9f3', 6)).join('')}
</svg>`;

await sharp(Buffer.from(svg), { density: 144 }).resize(W*2, H*2).png({ compressionLevel: 9 }).toFile(OUT);
console.log('wrote', OUT);
