// RazinSoft WhatsApp Gateway (Phase 1)
// A thin Baileys (WhatsApp Web / QR) bridge. It owns the WhatsApp connection and exposes a small
// HTTP API to Laravel (status / connect / logout / send) while pushing inbound events to Laravel's
// webhook. All WhatsApp-specific code lives here — the Laravel app talks only to this gateway, so it
// can later be swapped for the Meta Cloud API without touching business logic.

import express from 'express'
import pino from 'pino'
import qrcode from 'qrcode'
import { Buffer } from 'node:buffer'
import fs from 'node:fs'
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
const SESSION_DIR = process.env.SESSION_DIR || './session'
const log = pino({ level: 'info' })

let sock = null
let state = 'disconnected'   // disconnected | qr | connecting | connected
let qrDataUrl = null
let number = null
const lastKeys = new Map()       // jid -> newest message key (for read receipts)
const groupNames = new Map()     // jid -> cached group subject

// Resolve (and cache) a group's subject/title.
async function groupSubject(jid) {
  if (groupNames.has(jid)) return groupNames.get(jid)
  try {
    const meta = await sock.groupMetadata(jid)
    const subject = meta?.subject || null
    groupNames.set(jid, subject)
    return subject
  } catch {
    return null
  }
}

// ---- push an event to Laravel ----
async function push(payload) {
  if (!WEBHOOK) return
  try {
    await fetch(WEBHOOK, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Gateway-Secret': SECRET },
      body: JSON.stringify(payload),
    })
  } catch (e) {
    log.warn('webhook push failed: ' + e.message)
  }
}

// ---- start / restart the WhatsApp socket ----
async function start() {
  if (!fs.existsSync(SESSION_DIR)) fs.mkdirSync(SESSION_DIR, { recursive: true })
  const { state: authState, saveCreds } = await useMultiFileAuthState(SESSION_DIR)
  state = 'connecting'

  // Pin to the latest WhatsApp Web protocol version — a stale version triggers 405 rejections.
  const { version } = await fetchLatestBaileysVersion()
  sock = makeWASocket({
    version,
    auth: authState,
    logger: pino({ level: 'silent' }),
    browser: Browsers.ubuntu('Chrome'),
  })

  sock.ev.on('creds.update', saveCreds)

  sock.ev.on('connection.update', async (u) => {
    const { connection, lastDisconnect, qr } = u
    if (qr) {
      state = 'qr'
      qrDataUrl = await qrcode.toDataURL(qr)
      push({ event: 'connection', state: 'qr' })
    }
    if (connection === 'open') {
      state = 'connected'
      qrDataUrl = null
      number = sock?.user?.id?.split(':')[0] || null
      push({ event: 'connection', state: 'connected', number })
      log.info('WhatsApp connected: ' + number)
    }
    if (connection === 'close') {
      const code = lastDisconnect?.error?.output?.statusCode
      if (code === DisconnectReason.loggedOut) {
        state = 'disconnected'
        try { fs.rmSync(SESSION_DIR, { recursive: true, force: true }) } catch {}
        push({ event: 'connection', state: 'disconnected' })
      } else {
        // Any other drop → auto-reconnect.
        log.warn('connection closed (' + code + '), reconnecting…')
        setTimeout(start, 2000)
      }
    }
  })

  // ---- inbound messages ----
  sock.ev.on('messages.upsert', async ({ messages, type }) => {
    if (type !== 'notify') return
    for (const m of messages) {
      if (!m.message) continue
      const jid = m.key.remoteJid || ''
      if (jid === 'status@broadcast') continue // skip status/story broadcasts
      const isGroup = jid.endsWith('@g.us')
      // The thread address: the group jid for groups, else the contact's number.
      const from = isGroup ? jid : jid.replace('@s.whatsapp.net', '')
      // Remember the newest inbound key so we can send a read receipt when an agent opens the chat.
      if (!m.key.fromMe) lastKeys.set(jid, m.key)
      // Resolve the real phone number. For a plain @s.whatsapp.net JID it's the JID itself; for a
      // privacy LID (@lid) WhatsApp exposes the underlying number via remoteJidAlt / senderPn.
      // In a group the number belongs to the participant who sent the message.
      const phone = isGroup ? resolvePhone(m.key.participant || '', m.key) : resolvePhone(jid, m.key)
      const payload = {
        event: 'message',
        id: m.key.id,
        from,
        phone,
        chat_type: isGroup ? 'group' : 'single',
        group_subject: isGroup ? await groupSubject(jid) : null,
        sender_name: isGroup ? (m.pushName || null) : null,
        from_me: !!m.key.fromMe,
        name: isGroup ? await groupSubject(jid) : (m.pushName || null),
        timestamp: m.messageTimestamp ? Number(m.messageTimestamp) : Math.floor(Date.now() / 1000),
        ...parseMessage(m.message),
      }
      // Attach media inline (base64) for common types.
      if (payload._mediaType) {
        try {
          const buf = await downloadMediaMessage(m, 'buffer', {})
          payload.media = 'data:' + (payload.media_mime || 'application/octet-stream') + ';base64,' + Buffer.from(buf).toString('base64')
        } catch (e) { log.warn('media download failed: ' + e.message) }
        delete payload._mediaType
      }
      push(payload)
    }
  })

  // ---- delivery / read receipts ----
  sock.ev.on('messages.update', (updates) => {
    for (const u of updates) {
      const s = u.update?.status
      if (!s) continue
      const map = { 2: 'sent', 3: 'delivered', 4: 'read', 5: 'read' }
      push({ event: 'status', id: u.key.id, status: map[s] || 'sent' })
    }
  })
}

// Resolve the underlying phone number for a chat address (digits only, no @domain).
// A LID (@lid) hides the number; Baileys surfaces the real one on the message key as
// remoteJidAlt or senderPn — fall back to null when neither is present.
function resolvePhone(jid, key) {
  if (jid.endsWith('@s.whatsapp.net')) return jid.replace('@s.whatsapp.net', '')
  const alt = key?.remoteJidAlt || key?.senderPn || key?.participantAlt || key?.participantPn || ''
  if (alt && alt.includes('@s.whatsapp.net')) return alt.replace('@s.whatsapp.net', '')
  if (alt && /^\d+$/.test(alt.replace('@lid', ''))) {
    const digits = alt.replace('@lid', '').replace('@s.whatsapp.net', '')
    return alt.includes('@lid') ? null : digits
  }
  return null
}

// Normalise a Baileys message into our flat shape.
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

app.get('/status', (req, res) => res.json({ state, qr: qrDataUrl, number }))

app.post('/connect', async (req, res) => {
  if (state === 'connected') return res.json({ ok: true, state })
  try { await start() } catch (e) { return res.status(500).json({ error: e.message }) }
  res.json({ ok: true, state })
})

// Mark a chat's incoming messages as read on WhatsApp (blue ticks for the other side).
app.post('/read', async (req, res) => {
  if (state !== 'connected' || !sock) return res.status(409).json({ error: 'WhatsApp is not connected.' })
  const { to } = req.body
  const jid = to?.includes('@') ? to : to + '@s.whatsapp.net'
  const key = lastKeys.get(jid)
  if (!key) return res.json({ ok: true, skipped: 'no-key' })
  try {
    await sock.readMessages([key])
    res.json({ ok: true })
  } catch (e) {
    res.status(500).json({ error: e.message })
  }
})

app.post('/logout', async (req, res) => {
  try { await sock?.logout() } catch {}
  try { fs.rmSync(SESSION_DIR, { recursive: true, force: true }) } catch {}
  state = 'disconnected'; qrDataUrl = null; sock = null
  res.json({ ok: true })
})

app.post('/send', async (req, res) => {
  if (state !== 'connected' || !sock) return res.status(409).json({ error: 'WhatsApp is not connected.' })
  const { to, type = 'text', text, url, caption, filename } = req.body
  const jid = to.includes('@') ? to : to + '@s.whatsapp.net'
  try {
    let content
    if (type === 'text') content = { text }
    else if (type === 'image') content = { image: { url }, caption }
    else if (type === 'video') content = { video: { url }, caption }
    else if (type === 'audio') content = { audio: { url }, mimetype: 'audio/mp4', ptt: true }
    else if (type === 'document') content = { document: { url }, fileName: filename || 'document', caption }
    else content = { text: text || '' }
    const sent = await sock.sendMessage(jid, content)
    res.json({ id: sent?.key?.id || '' })
  } catch (e) {
    res.status(500).json({ error: e.message })
  }
})

app.listen(PORT, () => log.info('WhatsApp gateway listening on :' + PORT))
// Resume an existing session on boot (persistent sessions + auto-reconnect).
start().catch((e) => log.error(e))
