// Captures product screenshots from the demo install, driving Chrome over the DevTools protocol.
//
// Written against the protocol directly rather than pulling in Playwright: this runs once, and a
// 300MB browser download to take five pictures is not a trade worth making.
//
// It signs in as the demo admin, so every name in the shots is invented — the point of the demo
// database is that no screenshot ever carries a real customer's details.
import { spawn } from 'node:child_process';
import { writeFileSync, mkdirSync } from 'node:fs';

const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const BASE = 'http://127.0.0.1:8099';
const OUT = '/Users/shofikulislam/Codes/razinsoft-website-2.0/public/images/screens';
const PORT = 9222;

const SHOTS = [
  ['dashboard', '/admin'],
  ['deals', '/admin/deals'],
  ['clients', '/admin/clients'],
  ['whatsapp', '/admin/whatsapp'],
  ['email-templates', '/admin/email/templates'],
  ['branding', '/admin/branding'],
  ['projects', '/admin/projects'],
  ['hr', '/admin/staff'],
  ['analytics', '/admin/finance'],
  ['messenger', '/admin/chat'],
];

mkdirSync(OUT, { recursive: true });

const chrome = spawn(CHROME, [
  '--headless=new',
  `--remote-debugging-port=${PORT}`,
  '--window-size=1440,900',
  '--hide-scrollbars',
  '--no-first-run',
  '--user-data-dir=/tmp/smartdesk-shots',
], { stdio: 'ignore' });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function target() {
  for (let i = 0; i < 40; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${PORT}/json/new?about:blank`, { method: 'PUT' });
      if (res.ok) return res.json();
    } catch {}
    await sleep(250);
  }
  throw new Error('Chrome never came up');
}

const page = await target();
const ws = new WebSocket(page.webSocketDebuggerUrl);
await new Promise((r) => (ws.onopen = r));

let id = 0;
const pending = new Map();
ws.onmessage = (e) => {
  const msg = JSON.parse(e.data);
  if (msg.id && pending.has(msg.id)) { pending.get(msg.id)(msg.result); pending.delete(msg.id); }
};
const send = (method, params = {}) =>
  new Promise((resolve) => { pending.set(++id, resolve); ws.send(JSON.stringify({ id, method, params })); });

await send('Page.enable');
await send('Runtime.enable');

async function goto(path) {
  await send('Page.navigate', { url: BASE + path });
  await sleep(2200);
}

async function evaluate(expression) {
  const r = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
  return r?.result?.value;
}

// Sign in once; the session cookie carries through the rest.
await goto('/admin/login');
await evaluate(`
  (() => {
    const e = document.querySelector('input[name=email]'), p = document.querySelector('input[name=password]');
    e.value = 'ariana@smartdesk.example'; p.value = 'demo1234';
    e.dispatchEvent(new Event('input', { bubbles: true }));
    p.dispatchEvent(new Event('input', { bubbles: true }));
    document.querySelector('form').submit();
    return true;
  })()
`);
await sleep(2500);

for (const [name, path] of SHOTS) {
  await goto(path);

  // An inbox with nothing open shows an empty half — open the first conversation so the shot
  // says what the screen is for.
  if (name === 'messenger') {
    await evaluate(`(() => {
      const rows = [...document.querySelectorAll('a, button')].filter(b => /Product team/.test(b.textContent));
      if (rows[0]) { rows[0].click(); return true; }
      return false;
    })()`);
    await sleep(1600);
  }

  if (name === 'whatsapp') {
    // The list rows are Alpine buttons; the first one in the thread column is the first chat.
    await evaluate(`(() => {
      const rows = [...document.querySelectorAll('button')].filter(b => /minutes ago|hours ago|Yesterday/.test(b.textContent));
      if (rows[0]) { rows[0].click(); return true; }
      return false;
    })()`);
    await sleep(1400);
  }

  // Sidebar submenus animate open; give them a beat so nothing is caught mid-slide.
  await sleep(700);
  const { data } = await send('Page.captureScreenshot', { format: 'png' });
  writeFileSync(`${OUT}/${name}.png`, Buffer.from(data, 'base64'));
  console.log(`  ${name}.png`);
}

ws.close();
chrome.kill();
console.log('done →', OUT);
