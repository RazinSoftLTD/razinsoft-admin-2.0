@extends('admin.layouts.app')
@section('title', 'WhatsApp Connection')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Connect: {{ $account->name }}</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Scan the QR with the phone for this number ({{ $account->name }}). Each number keeps its own session &amp; inbox.</p>
        </div>
        <a href="{{ route('admin.whatsapp-settings') }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Settings</a>
    </div>

    <div class="mx-auto max-w-xl rounded-2xl border border-gray-100 bg-white p-8 shadow-sm" x-data="waConnection()" x-init="init()">
        {{-- Status pill --}}
        <div class="mb-6 flex items-center justify-center">
            <span class="inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold"
                  :class="{ 'bg-emerald-50 text-emerald-700': state === 'connected', 'bg-amber-50 text-amber-700': state === 'qr' || state === 'connecting', 'bg-gray-100 text-gray-500': state === 'disconnected' }">
                <span class="h-2 w-2 rounded-full" :class="{ 'bg-emerald-500': state === 'connected', 'bg-amber-500': state === 'qr' || state === 'connecting', 'bg-gray-400': state === 'disconnected' }"></span>
                <span x-text="stateLabel()"></span>
            </span>
        </div>

        {{-- Connected --}}
        <div x-show="state === 'connected'" x-cloak class="text-center">
            <span class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-emerald-50 text-emerald-500">
                <svg class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
            </span>
            <p class="mt-4 text-lg font-bold text-[var(--color-heading)]">WhatsApp is connected</p>
            <p class="text-sm text-gray-500" x-text="number ? '+' + number : 'Your account is linked and receiving messages.'"></p>
            <div class="mt-6 flex justify-center gap-2">
                <a href="{{ route('admin.whatsapp.index') }}" class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Open Inbox</a>
                <button type="button" @click="logout()" class="rounded-lg border border-red-200 px-5 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Disconnect</button>
            </div>
        </div>

        {{-- QR --}}
        <div x-show="state === 'qr'" x-cloak class="text-center">
            <p class="mb-4 text-sm text-gray-500">Open WhatsApp on your phone → <strong>Settings → Linked Devices → Link a Device</strong>, then scan this code.</p>
            <div class="mx-auto grid h-64 w-64 place-items-center rounded-xl border border-gray-100 bg-white p-3">
                <template x-if="qr"><img :src="qr" alt="WhatsApp QR" class="h-full w-full object-contain"></template>
                <template x-if="!qr"><span class="text-sm text-gray-400">Generating QR…</span></template>
            </div>
            <p class="mt-4 text-xs text-gray-400">The QR refreshes automatically. Keep this page open while scanning.</p>
        </div>

        {{-- Disconnected / connecting --}}
        <div x-show="state === 'disconnected' || state === 'connecting'" x-cloak class="text-center">
            <template x-if="!configured">
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    Set the gateway URL in <a href="{{ route('admin.whatsapp-settings') }}" class="font-semibold underline">Settings</a> first, then come back to connect.
                </div>
            </template>
            <template x-if="configured">
                <div>
                    <span class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-gray-50 text-gray-400">
                        <svg class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2a10 10 0 0 0-8.6 15L2 22l5-1.4A10 10 0 1 0 12 2Z"/></svg>
                    </span>
                    <p class="mt-4 text-sm text-gray-500" x-text="message || 'Not connected. Click connect to generate a QR code.'"></p>
                    <button type="button" @click="connect()" :disabled="busy" class="mt-6 rounded-lg bg-emerald-500 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-600 disabled:opacity-50">
                        <span x-text="busy ? 'Starting…' : 'Connect WhatsApp'"></span>
                    </button>
                </div>
            </template>
        </div>
    </div>

    <script>
        function waConnection() {
            return {
                state: @js($settings->session_state ?? 'disconnected'),
                qr: null, number: @js($settings->display_number), configured: {{ $settings->isConfigured() ? 'true' : 'false' }},
                message: '', busy: false, timer: null,
                csrf: document.querySelector('meta[name=csrf-token]').content,
                stateLabel() { return { connected: 'Connected', qr: 'Scan the QR code', connecting: 'Connecting…', disconnected: 'Disconnected' }[this.state] || this.state; },
                init() { this.poll(); this.timer = setInterval(() => this.poll(), 3000); },
                async poll() {
                    try {
                        const r = await fetch(@js(route('admin.whatsapp-connection.status', $account)));
                        const d = await r.json();
                        this.state = d.state; this.qr = d.qr; this.number = d.number; this.configured = d.configured; this.message = d.message;
                    } catch {}
                },
                async connect() {
                    this.busy = true;
                    try { await fetch(@js(route('admin.whatsapp-connection.connect', $account)), { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf } }); await this.poll(); }
                    finally { this.busy = false; }
                },
                async logout() {
                    if (!confirm('Disconnect WhatsApp? You will need to scan the QR again.')) return;
                    await fetch(@js(route('admin.whatsapp-connection.logout', $account)), { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf } });
                    this.state = 'disconnected'; this.qr = null;
                },
                destroy() { clearInterval(this.timer); },
            };
        }
    </script>
@endsection
