// Draws a cover image for each demo article.
//
// Stock photography would need a licence that does not travel with a CodeCanyon package, and a
// buyer replaces these on day one anyway. So they are drawn: a flat brand-coloured field, the
// category, the headline, and the mark. Legible at card size, ~30KB each.
//
// Run from the website repo so `sharp` resolves.
import sharp from '/Users/shofikulislam/Codes/razinsoft-website-2.0/node_modules/sharp/lib/index.js';
import { mkdirSync } from 'node:fs';

// Written into the repo, not into storage: the seeder copies them out at seed time.
const OUT = process.argv[2] || new URL('../database/demo-assets/articles', import.meta.url).pathname;
mkdirSync(OUT, { recursive: true });

const W = 1200, H = 675;

const COVERS = [
  { file: 'why-we-stopped-paying-per-seat', cat: 'Product',       title: 'Why we stopped\npaying per seat',            a: '#1d4ed8', b: '#4f46e5' },
  { file: 'setting-up-whatsapp-cloud-api',  cat: 'Guides',        title: 'Setting up\nWhatsApp Cloud API',             a: '#047857', b: '#0d9488' },
  { file: 'moving-your-invoices-across',    cat: 'Guides',        title: 'Moving your invoices\nwithout losing history', a: '#b45309', b: '#c2410c' },
  { file: 'what-shipped-this-quarter',      cat: 'Release notes', title: 'What shipped\nthis quarter',                 a: '#7c3aed', b: '#a21caf' },
];

const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

for (const c of COVERS) {
  const lines = c.title.split('\n');

  // Everything is centred, because the blog crops these two different ways: the featured card
  // trims the sides, the grid cards trim top and bottom. Text pinned to a corner survives neither.
  const size = Math.max(...lines.map((l) => l.length)) > 24 ? 58 : 70;
  const block = lines.length * (size + 16);
  const top = (H - block) / 2 + size;

  // No category label here — the blog overlays its own pill, and two of them stacked reads as a
  // mistake rather than a design.
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="${c.a}"/><stop offset="1" stop-color="${c.b}"/>
    </linearGradient>
  </defs>
  <rect width="${W}" height="${H}" fill="url(#bg)"/>

  <circle cx="${W - 80}" cy="90" r="230" fill="#ffffff" opacity="0.06"/>
  <circle cx="60" cy="${H - 60}" r="180" fill="#ffffff" opacity="0.05"/>

  ${lines.map((l, i) =>
    `<text x="${W / 2}" y="${top + i * (size + 16)}" text-anchor="middle"
           font-family="Manrope, Inter, Helvetica, Arial, sans-serif"
           font-size="${size}" font-weight="800" fill="#ffffff">${esc(l)}</text>`
  ).join('')}

  <g transform="translate(${W / 2 - 74} ${H - 132})" opacity="0.92">
    <rect width="34" height="34" rx="9" fill="#ffffff"/>
    <rect x="8" y="10" width="18" height="12" rx="2.5" fill="${c.a}"/>
    <rect x="11" y="24" width="12" height="2.5" rx="1.25" fill="${c.a}"/>
    <text x="46" y="24" font-family="Manrope, Inter, Helvetica, Arial, sans-serif" font-size="20"
          font-weight="800" fill="#ffffff">SmartDesk</text>
  </g>
</svg>`;

  await sharp(Buffer.from(svg)).png({ compressionLevel: 9 }).toFile(`${OUT}/${c.file}.png`);
  console.log(`  ${c.file}.png`);
}

console.log('→', OUT);
