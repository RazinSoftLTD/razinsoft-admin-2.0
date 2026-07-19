// RazinSoft WhatsApp Gateway (Phase 1) — MULTI-ACCOUNT
// A thin Baileys (WhatsApp Web / QR) bridge. It owns one WhatsApp connection PER account (session key)
// and exposes a small HTTP API to Laravel while pushing inbound events (tagged with the session key)
// to Laravel's webhook. One gateway process runs many sessions, each in its own auth folder.

import express from 'express'
import pino from 'pino'
import qrcode from 'qrcode'
import { Buffer } from 'node:buffer'
import fs from 'node:fs'
import path from 'node:path'
import {
  default as makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  downloadMediaMessage,
  fetchLatestBaileysVersion,
  Browsers,
} from '@whiskeysockets/baileys'

const PORT = process.env.PORT || 8090
const SECRET = process.env.GATEWAY_SECRET || 'change-me'
const WEBHOOK = process.env.LARAVEL_WEBHOOK || ''
const LEGACY_DIR = process.env.SESSION_DIR || './session' // the original single-session folder
const SESSIONS_ROOT = process.env.SESSIONS_ROOT || './sessions'
const log = pino({ level: 'info' })

// sessionKey -> { sock, state, qr, number, lastKeys:Map, groupNames:Map, starting:bool }
const sessions = new Map()

function dirFor(key) {
  // Keep the currently-linked number working: 'default' maps to the old single-session folder.
  return key === 'default' ? LEGACY_DIR : path.join(SESSIONS_ROOT, key.replace(/[^a-zA-Z0-9_-]/g, ''))
}

function getSession(key) {
  if (!sessions.has(key)) {
    sessions.set(key, { sock: null, state: 'disconnected', qr: null, number: null, lastKeys: new Map(), groupNames: new Map(), starting: false })
  }
  return sessions.get(key)
}

// ---- push an event (tagged with its session) to Laravel ----
async function push(key, payload) {
  if (!WEBHOOK) return
  try {
    await fetch(WEBHOOK, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Gateway-Secret': SECRET },
      body: JSON.stringify({ session: key, ...payload }),
    })
  } catch (e) {
    log.warn('webhook push failed: ' + e.message)
  }
}

async function groupSubject(s, jid) {
  if (s.groupNames.has(jid)) return s.groupNames.get(jid)
  try {
    const meta = await s.sock.groupMetadata(jid)
    const subject = meta?.subject || null
    s.groupNames.set(jid, subject)
    return subject
  } catch {
    return null
  }
}

// ---- start / restart a WhatsApp socket for one session ----
async function start(key) {
  const s = getSession(key)
  if (s.starting) return
  s.starting = true
  try {
    const dir = dirFor(key)
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true })
    const { state: authState, saveCreds } = await useMultiFileAuthState(dir)
    s.state = 'connecting'

    const { version } = await fetchLatestBaileysVersion()
    const sock = makeWASocket({
      version,
      auth: authState,
      logger: pino({ level: 'silent' }),
      browser: Browsers.ubuntu('Chrome'),
      syncFullHistory: true,
    })
    s.sock = sock

    sock.ev.on('creds.update', saveCreds)

    sock.ev.on('connection.update', async (u) => {
      const { connection, lastDisconnect, qr } = u
      if (qr) {
        s.state = 'qr'
        s.qr = await qrcode.toDataURL(qr)
        push(key, { event: 'connection', state: 'qr' })
      }
      if (connection === 'open') {
        s.state = 'connected'
        s.qr = null
        s.number = sock?.user?.id?.split(':')[0] || null
        push(key, { event: 'connection', state: 'connected', number: s.number })
        log.info(`[${key}] connected: ${s.number}`)
      }
      if (connection === 'close') {
        const code = lastDisconnect?.error?.output?.statusCode
        if (code === DisconnectReason.loggedOut) {
          s.state = 'disconnected'
          try { fs.rmSync(dirFor(key), { recursive: true, force: true }) } catch {}
          push(key, { event: 'connection', state: 'disconnected' })
        } else {
          log.warn(`[${key}] connection closed (${code}), reconnecting…`)
          setTimeout(() => start(key), 2000)
        }
      }
    })

    sock.ev.on('messages.upsert', async ({ messages, type }) => {
      // 'notify' = live/real-time; 'append' = messages synced on (re)connect that we were offline for.
      // Import 'append' quietly (historic) so a disconnect→reconnect still catches up with the phone.
      if (type !== 'notify' && type !== 'append') return
      const historic = type === 'append'
      for (const m of messages) await handleMessage(key, m, historic)
    })

    sock.ev.on('messaging-history.set', async ({ messages }) => {
      if (!Array.isArray(messages) || !messages.length) return
      log.info(`[${key}] history sync: ${messages.length} messages`)
      for (const m of messages) await handleMessage(key, m, true)
    })

    sock.ev.on('messages.reaction', (reactions) => {
      for (const r of reactions) {
        push(key, { event: 'reaction', id: r.key?.id, emoji: r.reaction?.text || '', from_me: !!r.reaction?.key?.fromMe })
      }
    })

    sock.ev.on('messages.update', (updates) => {
      for (const u of updates) {
        const st = u.update?.status
        if (!st) continue
        const map = { 2: 'sent', 3: 'delivered', 4: 'read', 5: 'read' }
        push(key, { event: 'status', id: u.key.id, status: map[st] || 'sent' })
      }
    })
  } finally {
    s.starting = false
  }
}

// Build the flat payload for one message and push it (tagged with its session) to Laravel.
async function handleMessage(key, m, historic = false) {
  const s = getSession(key)
  if (!m || !m.message) return
  const jid = m.key.remoteJid || ''
  if (jid === 'status@broadcast') return
  if (m.message.reactionMessage) {
    const r = m.message.reactionMessage
    push(key, { event: 'reaction', id: r.key?.id, emoji: r.text || '', from_me: !!m.key.fromMe })
    return
  }
  const isGroup = jid.endsWith('@g.us')
  const from = isGroup ? jid : jid.replace('@s.whatsapp.net', '')
  if (!m.key.fromMe && !historic) s.lastKeys.set(jid, m.key)
  const phone = isGroup ? resolvePhone(m.key.participant || '', m.key) : resolvePhone(jid, m.key)
  const q = extractQuoted(m.message)
  const payload = {
    event: 'message',
    id: m.key.id,
    from,
    phone,
    historic,
    quoted_id: q?.id || null,
    quoted_body: q?.text || null,
    quoted_participant: q?.participant || null,
    chat_type: isGroup ? 'group' : 'single',
    group_subject: isGroup ? await groupSubject(s, jid) : null,
    sender_name: isGroup ? (m.pushName || null) : null,
    participant: isGroup ? (m.key.participant || null) : null,
    from_me: !!m.key.fromMe,
    name: isGroup ? await groupSubject(s, jid) : (m.pushName || null),
    timestamp: m.messageTimestamp ? Number(m.messageTimestamp) : Math.floor(Date.now() / 1000),
    ...parseMessage(m.message),
  }
  if (payload._mediaType) {
    try {
      const buf = await downloadMediaMessage(m, 'buffer', {})
      payload.media = 'data:' + (payload.media_mime || 'application/octet-stream') + ';base64,' + Buffer.from(buf).toString('base64')
    } catch (e) { log.warn('media download failed: ' + e.message) }
    delete payload._mediaType
  }
  push(key, payload)
}

function resolvePhone(jid, mkey) {
  if (jid.endsWith('@s.whatsapp.net')) return jid.replace('@s.whatsapp.net', '')
  const alt = mkey?.remoteJidAlt || mkey?.senderPn || mkey?.participantAlt || mkey?.participantPn || ''
  if (alt && alt.includes('@s.whatsapp.net')) return alt.replace('@s.whatsapp.net', '')
  if (alt && /^\d+$/.test(alt.replace('@lid', ''))) {
    const digits = alt.replace('@lid', '').replace('@s.whatsapp.net', '')
    return alt.includes('@lid') ? null : digits
  }
  return null
}

// Pull the quoted (replied-to) reference out of a message's contextInfo, if any.
function extractQuoted(msg) {
  const ctx = msg.extendedTextMessage?.contextInfo || msg.imageMessage?.contextInfo
    || msg.videoMessage?.contextInfo || msg.documentMessage?.contextInfo || msg.audioMessage?.contextInfo || null
  if (!ctx || !ctx.stanzaId) return null
  const qm = ctx.quotedMessage || {}
  const text = qm.conversation || qm.extendedTextMessage?.text || qm.imageMessage?.caption
    || (qm.imageMessage ? '📷 Photo' : qm.videoMessage ? '🎥 Video' : qm.audioMessage ? '🎵 Voice message'
      : qm.documentMessage ? '📄 Document' : qm.stickerMessage ? 'Sticker' : '') || ''
  return { id: ctx.stanzaId, text, participant: ctx.participant || null }
}

function parseMessage(msg) {
  if (msg.conversation) return { type: 'text', text: msg.conversation }
  if (msg.extendedTextMessage) return { type: 'text', text: msg.extendedTextMessage.text }
  if (msg.imageMessage) return { type: 'image', text: msg.imageMessage.caption || null, media_mime: msg.imageMessage.mimetype, _mediaType: 1 }
  if (msg.videoMessage) return { type: 'video', text: msg.videoMessage.caption || null, media_mime: msg.videoMessage.mimetype, _mediaType: 1 }
  if (msg.audioMessage) return { type: 'audio', media_mime: msg.audioMessage.mimetype, _mediaType: 1 }
  if (msg.documentMessage) return { type: 'document', text: msg.documentMessage.caption || null, filename: msg.documentMessage.fileName, media_mime: msg.documentMessage.mimetype, _mediaType: 1 }
  if (msg.stickerMessage) return { type: 'sticker', media_mime: msg.stickerMessage.mimetype, _mediaType: 1 }
  return { type: 'text', text: '[Unsupported message]' }
}

// ---- HTTP API for Laravel ----
const app = express()
app.use(express.json({ limit: '25mb' }))
app.use((req, res, next) => {
  if (req.headers['x-gateway-secret'] !== SECRET) return res.status(401).json({ error: 'Unauthorized' })
  next()
})

// Resolve the session key from the request (body or query), defaulting to 'default'.
function keyOf(req) {
  return String(req.body?.session || req.query?.session || 'default')
}
function connected(req, res) {
  const s = getSession(keyOf(req))
  if (s.state !== 'connected' || !s.sock) { res.status(409).json({ error: 'WhatsApp is not connected.' }); return null }
  return s
}
function jidOf(to) {
  return to.includes('@') ? to : to + '@s.whatsapp.net'
}

app.get('/status', (req, res) => {
  const s = getSession(keyOf(req))
  res.json({ state: s.state, qr: s.qr, number: s.number })
})

app.post('/connect', async (req, res) => {
  const key = keyOf(req)
  const s = getSession(key)
  if (s.state === 'connected') return res.json({ ok: true, state: s.state })
  try { await start(key) } catch (e) { return res.status(500).json({ error: e.message }) }
  res.json({ ok: true, state: s.state })
})

// Force a reconnect so WhatsApp re-delivers anything we missed while offline (manual "Sync now").
app.post('/resync', async (req, res) => {
  const key = keyOf(req)
  const s = getSession(key)
  try {
    try { s.sock?.end(undefined) } catch {}
    await start(key)
    res.json({ ok: true, state: s.state })
  } catch (e) {
    res.status(500).json({ error: e.message })
  }
})

app.post('/logout', async (req, res) => {
  const key = keyOf(req)
  const s = getSession(key)
  try { await s.sock?.logout() } catch {}
  try { fs.rmSync(dirFor(key), { recursive: true, force: true }) } catch {}
  s.state = 'disconnected'; s.qr = null; s.sock = null
  res.json({ ok: true })
})

app.post('/send', async (req, res) => {
  const s = connected(req, res); if (!s) return
  const { to, type = 'text', text, url, caption, filename, mentions, quoted } = req.body
  const jid = jidOf(to)
  const mm = Array.isArray(mentions) && mentions.length ? { mentions } : {}
  // Reply-to (quoted) — build a minimal message object; the stanza id links it to the original.
  const opts = {}
  if (quoted && quoted.id) {
    opts.quoted = {
      key: { remoteJid: jid, id: quoted.id, fromMe: !!quoted.fromMe, ...(quoted.participant ? { participant: quoted.participant } : {}) },
      message: { conversation: quoted.text || '' },
    }
  }
  try {
    let content
    if (type === 'text') content = { text, ...mm }
    else if (type === 'image') content = { image: { url }, caption, ...mm }
    else if (type === 'video') content = { video: { url }, caption, ...mm }
    else if (type === 'audio') content = { audio: { url }, mimetype: 'audio/mp4', ptt: true }
    else if (type === 'document') content = { document: { url }, fileName: filename || 'document', caption, ...mm }
    else content = { text: text || '' }
    const sent = await s.sock.sendMessage(jid, content, opts)
    res.json({ id: sent?.key?.id || '' })
  } catch (e) { res.status(500).json({ error: e.message }) }
})

app.post('/check', async (req, res) => {
  const s = connected(req, res); if (!s) return
  try {
    const r = await s.sock.onWhatsApp(String(req.body.number).replace(/\D/g, ''))
    res.json({ exists: !!(r && r[0]?.exists), jid: r?.[0]?.jid || null })
  } catch (e) { res.status(500).json({ error: e.message }) }
})

app.post('/group-info', async (req, res) => {
  const s = connected(req, res); if (!s) return
  try {
    const meta = await s.sock.groupMetadata(req.body.jid)
    let picture = null
    try { picture = await s.sock.profilePictureUrl(req.body.jid, 'image') } catch {}
    const participants = await Promise.all((meta.participants || []).map((p) => resolveParticipant(s, p)))
    res.json({ subject: meta.subject || null, desc: meta.desc || null, picture, participants })
  } catch (e) { res.status(500).json({ error: e.message }) }
})

// Best-effort resolve a group participant's phone + name (WhatsApp hides many behind @lid).
async function resolveParticipant(s, p) {
  const jid = p.id || ''
  let phone = null, name = null
  if (jid.endsWith('@s.whatsapp.net')) phone = jid.replace('@s.whatsapp.net', '')
  else if (jid.endsWith('@lid')) {
    try {
      const pn = await s.sock.signalRepository?.lidMapping?.getPNForLID?.(jid)
      if (pn) phone = String(pn).replace(/@.*/, '')
    } catch {}
  }
  try {
    const c = s.sock.contacts?.[jid]
    name = c?.name || c?.notify || c?.verifiedName || null
  } catch {}
  return { id: jid, admin: p.admin || null, phone, name }
}

app.post('/group-subject', async (req, res) => {
  const s = connected(req, res); if (!s) return
  try { await s.sock.groupUpdateSubject(req.body.jid, req.body.subject); res.json({ ok: true }) }
  catch (e) { res.status(500).json({ error: e.message }) }
})

app.post('/group-picture', async (req, res) => {
  const s = connected(req, res); if (!s) return
  try { await s.sock.updateProfilePicture(req.body.jid, { url: req.body.url }); res.json({ ok: true }) }
  catch (e) { res.status(500).json({ error: e.message }) }
})

app.post('/react', async (req, res) => {
  const s = connected(req, res); if (!s) return
  const { to, id, emoji, from_me } = req.body
  const jid = jidOf(to)
  try { await s.sock.sendMessage(jid, { react: { text: emoji || '', key: { remoteJid: jid, fromMe: !!from_me, id } } }); res.json({ ok: true }) }
  catch (e) { res.status(500).json({ error: e.message }) }
})

app.post('/edit', async (req, res) => {
  const s = connected(req, res); if (!s) return
  const { to, id, text } = req.body
  const jid = jidOf(to)
  try { await s.sock.sendMessage(jid, { text, edit: { remoteJid: jid, fromMe: true, id } }); res.json({ ok: true }) }
  catch (e) { res.status(500).json({ error: e.message }) }
})

app.post('/delete', async (req, res) => {
  const s = connected(req, res); if (!s) return
  const { to, id } = req.body
  const jid = jidOf(to)
  try { await s.sock.sendMessage(jid, { delete: { remoteJid: jid, fromMe: true, id } }); res.json({ ok: true }) }
  catch (e) { res.status(500).json({ error: e.message }) }
})

app.post('/read', async (req, res) => {
  const s = connected(req, res); if (!s) return
  const jid = jidOf(req.body.to)
  const mkey = s.lastKeys.get(jid)
  if (!mkey) return res.json({ ok: true, skipped: 'no-key' })
  try { await s.sock.readMessages([mkey]); res.json({ ok: true }) }
  catch (e) { res.status(500).json({ error: e.message }) }
})

app.listen(PORT, () => log.info('WhatsApp gateway (multi-account) listening on :' + PORT))

// Resume every existing session on boot (persistent sessions + auto-reconnect).
function bootExisting() {
  const keys = new Set()
  if (fs.existsSync(LEGACY_DIR) && fs.readdirSync(LEGACY_DIR).length) keys.add('default')
  if (fs.existsSync(SESSIONS_ROOT)) {
    for (const d of fs.readdirSync(SESSIONS_ROOT)) {
      try { if (fs.statSync(path.join(SESSIONS_ROOT, d)).isDirectory()) keys.add(d) } catch {}
    }
  }
  if (!keys.size) keys.add('default') // nothing yet — prime default so the first QR works
  for (const k of keys) start(k).catch((e) => log.error(`[${k}] ${e.message}`))
}
bootExisting()
