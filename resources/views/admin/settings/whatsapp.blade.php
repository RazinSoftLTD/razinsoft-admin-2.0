@extends('admin.layouts.app')
@section('title', 'WhatsApp API')

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">WhatsApp API</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Settings &rsaquo; WhatsApp Business Cloud API — connect, configure and test.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.whatsapp-connection') }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M20 20h.01M17 20h.01M20 17h.01"/></svg>
                Connect via QR
            </a>
            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold {{ $settings->isConnected() ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                <span class="h-2 w-2 rounded-full {{ $settings->isConnected() ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                {{ $settings->isConnected() ? 'Connected'.($settings->display_number ? ' · '.$settings->display_number : '') : 'Not connected' }}
            </span>
        </div>
    </div>

    @if (session('error'))<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>@endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Credentials --}}
        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.whatsapp-settings.update') }}" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm" x-data="{ driver: @js(old('driver', $settings->driver ?? 'baileys')) }">
                @csrf
                {{-- Connection method --}}
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Connection Method</h2>
                <div class="mb-5 grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition" :class="driver === 'baileys' ? 'border-[var(--color-primary)] bg-[var(--color-primary-soft)]' : 'border-gray-200 hover:bg-gray-50'">
                        <input type="radio" name="driver" value="baileys" x-model="driver" class="mt-0.5 accent-[var(--color-primary)]">
                        <span><span class="block text-sm font-bold text-[var(--color-heading)]">QR / WhatsApp Web</span><span class="block text-xs text-[var(--color-muted)]">Scan a QR — no Meta account needed. (Phase 1)</span></span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition" :class="driver === 'cloud_api' ? 'border-[var(--color-primary)] bg-[var(--color-primary-soft)]' : 'border-gray-200 hover:bg-gray-50'">
                        <input type="radio" name="driver" value="cloud_api" x-model="driver" class="mt-0.5 accent-[var(--color-primary)]">
                        <span><span class="block text-sm font-bold text-[var(--color-heading)]">Meta Cloud API</span><span class="block text-xs text-[var(--color-muted)]">Official API with a WhatsApp Business account.</span></span>
                    </label>
                </div>

                {{-- Baileys gateway --}}
                <div x-show="driver === 'baileys'" x-cloak class="mb-5 grid gap-5 sm:grid-cols-2 border-t border-gray-100 pt-5">
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Gateway URL</label>
                        <input type="url" name="gateway_url" value="{{ old('gateway_url', $settings->gateway_url) }}" placeholder="https://wa-gateway.yourserver.com" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                        <p class="mt-1 text-xs text-gray-400">Where the Node.js Baileys gateway is running.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Gateway Secret</label>
                        <input type="password" name="gateway_secret" value="" placeholder="{{ $settings->gateway_secret ? '•••••••• (saved)' : 'Shared secret between Laravel & gateway' }}" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                    </div>
                </div>

                <h2 class="mb-5 text-sm font-bold text-[var(--color-heading)]" x-show="driver === 'cloud_api'" x-cloak>API Credentials</h2>
                <div class="grid gap-5 sm:grid-cols-2" x-show="driver === 'cloud_api'" x-cloak>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Phone Number ID</label>
                        <input type="text" name="phone_number_id" value="{{ old('phone_number_id', $settings->phone_number_id) }}" placeholder="e.g. 1029384756XXXX" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">WhatsApp Business Account ID</label>
                        <input type="text" name="business_account_id" value="{{ old('business_account_id', $settings->business_account_id) }}" placeholder="e.g. 1122334455XXXX" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Access Token</label>
                        <input type="password" name="access_token" value="" placeholder="{{ $settings->access_token ? '•••••••• (saved — leave blank to keep)' : 'Permanent access token' }}" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                        <p class="mt-1 text-xs text-gray-400">Use a permanent System User token from Meta Business.</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">App Secret</label>
                        <input type="password" name="app_secret" value="" placeholder="{{ $settings->app_secret ? '•••••••• (saved)' : 'Verifies webhook signatures' }}" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">API Version</label>
                        <input type="text" name="api_version" value="{{ old('api_version', $settings->api_version) }}" placeholder="v21.0" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                    </div>
                </div>
                <div class="mt-6 flex items-center gap-2">
                    <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save Settings</button>
                    <button type="submit" formaction="{{ route('admin.whatsapp-settings.test') }}" class="inline-flex items-center gap-2 rounded-lg border border-emerald-300 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg>
                        Test Connection
                    </button>
                </div>
            </form>

            {{-- Webhook --}}
            <div class="mt-6 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Webhook</h2>
                <p class="mb-3 text-xs text-[var(--color-muted)]">In Meta &rsaquo; WhatsApp &rsaquo; Configuration, set the Callback URL and Verify Token below, then subscribe to the <strong>messages</strong> field.</p>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Callback URL</label>
                        <div class="flex items-center gap-2" x-data="{ c: false }">
                            <input type="text" readonly value="{{ $webhookUrl }}" class="h-10 flex-1 rounded-lg border-gray-200 bg-gray-50 text-xs">
                            <button type="button" @click="navigator.clipboard.writeText('{{ $webhookUrl }}'); c = true; setTimeout(() => c = false, 1500)" class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-200" x-text="c ? 'Copied' : 'Copy'"></button>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Verify Token</label>
                        <div class="flex items-center gap-2" x-data="{ c: false }">
                            <input type="text" readonly value="{{ $settings->verify_token }}" class="h-10 flex-1 rounded-lg border-gray-200 bg-gray-50 text-xs">
                            <button type="button" @click="navigator.clipboard.writeText('{{ $settings->verify_token }}'); c = true; setTimeout(() => c = false, 1500)" class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-200" x-text="c ? 'Copied' : 'Copy'"></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Labels + quick replies --}}
        <div class="space-y-6">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Labels</h2>
                <form method="POST" action="{{ route('admin.whatsapp-settings.labels.store') }}" class="mb-4 flex items-center gap-2">
                    @csrf
                    <input type="text" name="name" required placeholder="Label name" class="h-9 flex-1 rounded-lg border-gray-200 text-sm">
                    <input type="color" name="color" value="#6366f1" class="h-9 w-11 cursor-pointer rounded-lg border-gray-200 p-1">
                    <button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white">Add</button>
                </form>
                <div class="flex flex-wrap gap-2">
                    @foreach ($labels as $lbl)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold" style="background: {{ $lbl->color }}1a; color: {{ $lbl->color }};">
                            {{ $lbl->name }}
                            <form method="POST" action="{{ route('admin.whatsapp-settings.labels.destroy', $lbl) }}" onsubmit="return confirm('Remove label?')">@csrf @method('DELETE')<button class="opacity-60 hover:opacity-100">×</button></form>
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Quick Replies</h2>
                <form method="POST" action="{{ route('admin.whatsapp-settings.quick.store') }}" class="mb-4 space-y-2">
                    @csrf
                    <input type="text" name="shortcut" placeholder="Shortcut (e.g. /hi)" class="h-9 w-full rounded-lg border-gray-200 text-sm">
                    <textarea name="body" required rows="2" placeholder="Message…" class="w-full rounded-lg border-gray-200 text-sm"></textarea>
                    <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-white">Add Quick Reply</button>
                </form>
                <ul class="space-y-2">
                    @foreach ($quickReplies as $qr)
                        <li class="flex items-start justify-between gap-2 rounded-lg border border-gray-50 px-3 py-2">
                            <div class="min-w-0">
                                @if ($qr->shortcut)<span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-500">{{ $qr->shortcut }}</span>@endif
                                <p class="mt-0.5 text-xs text-[var(--color-muted)]">{{ \Illuminate\Support\Str::limit($qr->body, 80) }}</p>
                            </div>
                            <form method="POST" action="{{ route('admin.whatsapp-settings.quick.destroy', $qr) }}">@csrf @method('DELETE')<button class="text-gray-300 hover:text-red-500">×</button></form>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection
