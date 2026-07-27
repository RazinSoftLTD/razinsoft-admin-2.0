@extends('admin.layouts.app')
@section('title', 'WhatsApp API')

@section('content')
    @php
        $u = auth()->user();
        $canConnection = $u->hasPermission('whatsapp.connection');
        $canNumbers = $u->hasPermission('whatsapp.numbers');
        $canWebhook = $u->hasPermission('whatsapp.webhook');
        $canLabels = $u->hasPermission('whatsapp.labels');
        $canQuick = $u->hasPermission('whatsapp.quick_replies');
        // Left column = gateway/numbers/webhook; right column = labels/quick replies.
        // When only one side is visible we drop the 2-column grid so nothing floats alone.
        $hasLeft = $canConnection || $canNumbers || $canWebhook;
        $hasRight = $canLabels || $canQuick;
        $bothCols = $hasLeft && $hasRight;
    @endphp
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">WhatsApp Config</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Each number picks its own connection method, so QR and Meta Cloud API numbers can run side by side. Set one up from the list below.</p>
        </div>
    </div>

    @if (session('error'))<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>@endif

    <div class="grid gap-6 {{ $bothCols ? 'lg:grid-cols-3' : '' }}">
        {{-- Credentials --}}
        @if ($hasLeft)
        <div class="{{ $bothCols ? 'lg:col-span-2' : 'max-w-3xl' }}">
            @if ($canConnection)
            <form method="POST" action="{{ route('admin.whatsapp-settings.update') }}" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                @csrf
                <h2 class="text-sm font-bold text-[var(--color-heading)]">QR Gateway</h2>
                <p class="mb-5 mt-1 text-xs text-[var(--color-muted)]">
                    Shared by every number connected by QR. Meta Cloud API numbers do not use it — their
                    credentials live on the number itself, so both kinds can be connected at the same time.
                </p>

                <div class="mb-1 grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Gateway URL</label>
                        <input type="url" name="gateway_url" value="{{ old('gateway_url', $settings->gateway_url) }}" placeholder="https://wa-gateway.yourserver.com" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                        <p class="mt-1 text-xs text-gray-400">Where the Node.js Baileys gateway is running.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Gateway Secret</label>
                        <input type="password" name="gateway_secret" value="" placeholder="{{ $settings->gateway_secret ? '•••••••• (saved)' : 'Shared secret between Laravel & gateway' }}" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                        <p class="mt-1 text-xs text-gray-400">Leave blank to keep the saved one.</p>
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-2">
                    <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save Settings</button>
                    <button type="submit" formaction="{{ route('admin.whatsapp-settings.test') }}" class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg>
                        Test gateway
                    </button>
                </div>
            </form>
            @endif

            {{-- WhatsApp numbers (accounts) --}}
            @if ($canNumbers)
            <div class="mt-6 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">WhatsApp Numbers</h2>
                        <p class="mt-0.5 text-xs text-[var(--color-muted)]">Connect several numbers (Support, Tech, Sales…). Each has its own inbox; only assigned team members can access it.</p>
                    </div>
                </div>

                <div class="space-y-3">
                    @foreach ($accounts as $acc)
                        <div class="rounded-xl border border-gray-100 p-4" x-data="{ open: false }">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <span class="grid h-9 w-9 place-items-center rounded-full text-white" style="background: {{ $acc->color }}">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Z"/></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="flex flex-wrap items-center gap-2 text-sm font-bold text-[var(--color-heading)]">
                                            {{ $acc->name }}
                                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $acc->isCloudApi() ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                                                {{ $acc->isCloudApi() ? 'Cloud API' : 'QR' }}
                                            </span>
                                        </p>
                                        <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-[var(--color-muted)]">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $acc->isConnected() ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                            @if ($acc->isConnected())
                                                Connected{{ $acc->display_number ? ' · +'.$acc->display_number : '' }}
                                            @elseif (! $acc->isConfigured())
                                                <span class="text-amber-700">{{ $acc->isCloudApi() ? 'Needs its Meta credentials' : 'Needs the gateway URL' }}</span>
                                            @else
                                                {{ $acc->isCloudApi() ? 'Not verified yet' : 'Not connected' }}
                                            @endif
                                            · {{ $acc->users->count() }} member{{ $acc->users->count() === 1 ? '' : 's' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.whatsapp-connection', $acc) }}" class="rounded-lg bg-emerald-500 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-600">{{ $acc->isConnected() ? 'Manage' : ($acc->isCloudApi() ? 'Verify' : 'Connect (QR)') }}</a>
                                    <button type="button" @click="open = !open" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Edit</button>
                                    @php $cc = (int) ($chatCounts[$acc->id] ?? 0); @endphp
                                    <form method="POST" action="{{ route('admin.whatsapp-accounts.destroy', $acc) }}"
                                          onsubmit="return confirm('Delete “{{ $acc->name }}”{{ $acc->display_number ? ' (+'.$acc->display_number.')' : '' }}?\n\nThis will move to the Trash:\n• {{ $cc }} conversation{{ $cc === 1 ? '' : 's' }} (with all their messages)\n• its team assignments\n• the WhatsApp session (you will need to re-scan the QR)\n\nIt stays in the Trash for 1 month (super admin can restore it), then auto-deletes permanently. Continue?')">@csrf @method('DELETE')<button class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">Delete</button></form>
                                </div>
                            </div>

                            {{-- Edit --}}
                            <form method="POST" action="{{ route('admin.whatsapp-accounts.update', $acc) }}" x-show="open" x-cloak class="mt-4 border-t border-gray-100 pt-4"
                                  x-data="{ driver: @js($acc->driver ?: 'baileys') }">
                                @csrf
                                <div class="mb-4 grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Name</label>
                                        <input type="text" name="name" value="{{ $acc->name }}" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Colour</label>
                                        <input type="color" name="color" value="{{ $acc->color }}" class="h-10 w-16 rounded-lg border-gray-200">
                                    </div>
                                </div>

                        <p class="mb-2 text-xs font-semibold text-[var(--color-muted)]">How this number connects</p>
                                <div class="mb-4 grid gap-3 sm:grid-cols-2">
                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                                           :class="driver === 'baileys' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:bg-gray-50'">
                                        <input type="radio" name="driver" value="baileys" x-model="driver" class="mt-0.5" style="accent-color:#10b981">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-bold text-[var(--color-heading)]">QR / WhatsApp Web</span>
                                            <span class="mt-0.5 block text-xs leading-relaxed text-[var(--color-muted)]">Scan a code with the phone. Groups and message editing keep working.</span>
                                        </span>
                                    </label>
                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                                           :class="driver === 'cloud_api' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:bg-gray-50'">
                                        <input type="radio" name="driver" value="cloud_api" x-model="driver" class="mt-0.5" style="accent-color:#10b981">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-bold text-[var(--color-heading)]">Meta Cloud API</span>
                                            <span class="mt-0.5 block text-xs leading-relaxed text-[var(--color-muted)]">Meta&rsquo;s official API. The number leaves the WhatsApp app.</span>
                                        </span>
                                    </label>
                                </div>

                                <div x-show="driver === 'cloud_api'" x-cloak class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                                    <p class="text-xs font-bold text-[var(--color-heading)]">From Meta</p>
                                    <p class="mb-3 mt-0.5 text-xs leading-relaxed text-[var(--color-muted)]">
                                        developers.facebook.com &rsaquo; your app &rsaquo; <strong>WhatsApp &rsaquo; API Setup</strong> has the first two.
                                    </p>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Phone Number ID</label>
                                            <input type="text" name="phone_number_id" value="{{ $acc->phone_number_id }}" placeholder="109876543210987" class="h-10 w-full rounded-lg border-gray-200 bg-white text-sm">
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">WhatsApp Business Account ID</label>
                                            <input type="text" name="business_account_id" value="{{ $acc->business_account_id }}" placeholder="102233445566778" class="h-10 w-full rounded-lg border-gray-200 bg-white text-sm">
                                            <p class="mt-1 text-xs text-gray-400">Without it there are no message templates to send.</p>
                                        </div>
                                        <div class="sm:col-span-2" x-data="{ show: false }">
                                            <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Permanent Access Token</label>
                                            <div class="flex items-center gap-2">
                                                <input :type="show ? 'text' : 'password'" name="access_token" autocomplete="new-password"
                                                       value="{{ $acc->access_token }}" placeholder="Paste the token from Meta"
                                                       class="h-10 min-w-0 flex-1 rounded-lg border-gray-200 bg-white text-sm">
                                                <button type="button" @click="show = !show" title="Show / hide"
                                                        class="grid h-10 w-10 shrink-0 place-items-center rounded-lg border border-gray-200 bg-white text-gray-400 transition hover:bg-gray-50">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                                </button>
                                            </div>
                                            <p class="mt-1 text-xs leading-relaxed text-gray-400">
                                                business.facebook.com &rsaquo; Business Settings &rsaquo; <strong>Users &rsaquo; System Users</strong> &rsaquo; Generate token, expiry <strong>Never</strong>.
                                                The one on API Setup lasts 24 hours &mdash; do not use it.
                                            </p>
                                        </div>
                                        <div x-data="{ show: false }">
                                            <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">App Secret</label>
                                            <div class="flex items-center gap-2">
                                                <input :type="show ? 'text' : 'password'" name="app_secret" autocomplete="off"
                                                       value="{{ $acc->app_secret }}"
                                                       class="h-10 min-w-0 flex-1 rounded-lg border-gray-200 bg-white text-sm">
                                                <button type="button" @click="show = !show" title="Show / hide"
                                                        class="grid h-10 w-10 shrink-0 place-items-center rounded-lg border border-gray-200 bg-white text-gray-400 transition hover:bg-gray-50">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                                </button>
                                            </div>
                                            <p class="mt-1 text-xs text-gray-400">App Settings &rsaquo; Basic &rsaquo; App secret.</p>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">API version</label>
                                            <input type="text" name="api_version" value="{{ $acc->api_version ?: 'v21.0' }}" class="h-10 w-full rounded-lg border-gray-200 bg-white text-sm">
                                        </div>
                                    </div>
                                </div>

                                @if ($acc->isCloudApi() && $acc->verify_token)
                                    <div x-show="driver === 'cloud_api'" x-cloak class="mb-4 rounded-xl border border-blue-200 bg-blue-50 p-4">
                                        <p class="text-xs font-bold text-[var(--color-heading)]">Point Meta at this number</p>
                                        <p class="mb-3 mt-0.5 text-xs leading-relaxed text-[var(--color-muted)]">
                                            Meta app &rsaquo; <strong>WhatsApp &rsaquo; Configuration &rsaquo; Webhook &rsaquo; Edit</strong>, paste both, save, then
                                            <strong>Manage</strong> &rsaquo; subscribe to <code class="rounded bg-white px-1">messages</code>.
                                        </p>
                                        <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Callback URL</label>
                                        <input type="text" readonly value="{{ url('/api/whatsapp/webhook') }}" onclick="this.select()" class="mb-2 h-9 w-full rounded-lg border-gray-200 bg-white font-mono text-xs">
                                        <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Verify Token &mdash; this number&rsquo;s own</label>
                                        <input type="text" readonly value="{{ $acc->verify_token }}" onclick="this.select()" class="h-9 w-full rounded-lg border-gray-200 bg-white font-mono text-xs">
                                        <p class="mt-2 text-xs text-gray-400">Click a box to select it. Several numbers may share the one URL.</p>
                                    </div>
                                @endif

                                <p class="mb-1.5 text-xs font-semibold text-[var(--color-muted)]">Who can use this number</p>
                                <div class="mb-4 flex flex-wrap gap-2">
                                    @foreach ($panelUsers as $u2)
                                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-gray-200 px-2.5 py-1 text-xs">
                                            <input type="checkbox" name="members[]" value="{{ $u2->id }}" @checked($acc->users->contains($u2->id)) class="rounded border-gray-300 text-emerald-500 focus:ring-emerald-400">
                                            {{ $u2->name }}
                                        </label>
                                    @endforeach
                                </div>

                                <div class="flex items-center gap-2">
                                    <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-white">Save changes</button>
                                    <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>

                {{-- Add number --}}
                <form method="POST" action="{{ route('admin.whatsapp-accounts.store') }}" class="mt-4 rounded-xl border border-dashed border-gray-200 p-4" x-data="{ open: false, driver: 'baileys' }">
                    @csrf
                    <button type="button" @click="open = true" x-show="!open" class="flex items-center gap-2 text-sm font-semibold text-emerald-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        Add a WhatsApp number
                    </button>

                    <div x-show="open" x-cloak>
                        <div class="mb-4 grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Name</label>
                                <input type="text" name="name" placeholder="Sales" class="h-10 w-full rounded-lg border-gray-200 text-sm" required>
                                <p class="mt-1 text-xs text-gray-400">Only your team sees this.</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Colour</label>
                                <input type="color" name="color" value="#25d366" class="h-10 w-16 rounded-lg border-gray-200">
                            </div>
                        </div>

                        <p class="mb-2 text-xs font-semibold text-[var(--color-muted)]">How this number connects</p>
                        <div class="mb-4 grid gap-3 sm:grid-cols-2">
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                                   :class="driver === 'baileys' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:bg-gray-50'">
                                <input type="radio" name="driver" value="baileys" x-model="driver" class="mt-0.5" style="accent-color:#10b981">
                                <span class="min-w-0">
                                    <span class="block text-sm font-bold text-[var(--color-heading)]">QR / WhatsApp Web</span>
                                    <span class="mt-0.5 block text-xs leading-relaxed text-[var(--color-muted)]">Scan a code with the phone. Nothing to set up at Meta, and groups and message editing keep working.</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                                   :class="driver === 'cloud_api' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:bg-gray-50'">
                                <input type="radio" name="driver" value="cloud_api" x-model="driver" class="mt-0.5" style="accent-color:#10b981">
                                <span class="min-w-0">
                                    <span class="block text-sm font-bold text-[var(--color-heading)]">Meta Cloud API</span>
                                    <span class="mt-0.5 block text-xs leading-relaxed text-[var(--color-muted)]">Meta&rsquo;s official API. Needs a verified business number, which then leaves the WhatsApp app.</span>
                                </span>
                            </label>
                        </div>

                        <div x-show="driver === 'cloud_api'" x-cloak class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                            <p class="text-xs font-bold text-[var(--color-heading)]">From Meta</p>
                            <p class="mb-3 mt-0.5 text-xs leading-relaxed text-[var(--color-muted)]">
                                developers.facebook.com &rsaquo; your app &rsaquo; <strong>WhatsApp &rsaquo; API Setup</strong> has the first two.
                            </p>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Phone Number ID</label>
                                    <input type="text" name="phone_number_id" value="" placeholder="109876543210987" class="h-10 w-full rounded-lg border-gray-200 bg-white text-sm">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">WhatsApp Business Account ID</label>
                                    <input type="text" name="business_account_id" value="" placeholder="102233445566778" class="h-10 w-full rounded-lg border-gray-200 bg-white text-sm">
                                    <p class="mt-1 text-xs text-gray-400">Without it there are no message templates to send.</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Permanent Access Token</label>
                                    <input type="password" name="access_token" autocomplete="new-password" placeholder="Paste the token from Meta" class="h-10 w-full rounded-lg border-gray-200 bg-white text-sm">
                                    <p class="mt-1 text-xs leading-relaxed text-gray-400">
                                        business.facebook.com &rsaquo; Business Settings &rsaquo; <strong>Users &rsaquo; System Users</strong> &rsaquo; Generate token, with
                                        <code class="rounded bg-white px-1">whatsapp_business_messaging</code> and
                                        <code class="rounded bg-white px-1">whatsapp_business_management</code>, expiry <strong>Never</strong>.
                                        The one on API Setup lasts 24 hours &mdash; do not use it.
                                    </p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">App Secret</label>
                                    <input type="text" name="app_secret" value="" class="h-10 w-full rounded-lg border-gray-200 bg-white text-sm">
                                    <p class="mt-1 text-xs text-gray-400">App Settings &rsaquo; Basic &rsaquo; App secret. Proves a webhook really came from Meta.</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">API version</label>
                                    <input type="text" name="api_version" value="v21.0" class="h-10 w-full rounded-lg border-gray-200 bg-white text-sm">
                                </div>
                            </div>
                        </div>

                        <p class="mb-1.5 text-xs font-semibold text-[var(--color-muted)]">Who can use this number</p>
                        <div class="mb-4 flex flex-wrap gap-2">
                            @foreach ($panelUsers as $u2)
                                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-gray-200 px-2.5 py-1 text-xs">
                                    <input type="checkbox" name="members[]" value="{{ $u2->id }}" class="rounded border-gray-300 text-emerald-500 focus:ring-emerald-400"> {{ $u2->name }}
                                </label>
                            @endforeach
                        </div>

                        <div class="flex items-center gap-2">
                            <button class="rounded-lg bg-emerald-500 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-600">Add number</button>
                            <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                            <span x-show="driver === 'cloud_api'" x-cloak class="text-xs text-[var(--color-muted)]">Next: verify it, then point Meta&rsquo;s webhook at it.</span>
                        </div>
                    </div>
                </form>
            </div>
            @endif

            {{-- Webhook --}}
            @if ($canWebhook)
            <div class="mt-6 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Webhook</h2>
                <p class="mb-3 text-xs text-[var(--color-muted)]">
                    In Meta &rsaquo; WhatsApp &rsaquo; Configuration, set this Callback URL and subscribe to the
                    <strong>messages</strong> field. Every Cloud API number has its <em>own</em> verify token —
                    find it on that number under WhatsApp Numbers &rsaquo; Edit. Several numbers can share this
                    one URL; Meta names which number each event belongs to.
                </p>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Callback URL</label>
                        <div class="flex items-center gap-2" x-data="{ c: false }">
                            <input type="text" readonly value="{{ $webhookUrl }}" class="h-10 flex-1 rounded-lg border-gray-200 bg-gray-50 text-xs">
                            <button type="button" @click="navigator.clipboard.writeText('{{ $webhookUrl }}'); c = true; setTimeout(() => c = false, 1500)" class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-200" x-text="c ? 'Copied' : 'Copy'"></button>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
        @endif

        {{-- Labels + quick replies --}}
        @if ($hasRight)
        <div class="space-y-6 {{ $bothCols ? '' : 'max-w-xl' }}">
            @if ($canLabels)
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
                            <form method="POST" action="{{ route('admin.whatsapp-settings.labels.destroy', $lbl) }}" onsubmit="return confirm('Remove label?')">@csrf @method('DELETE')<button class="opacity-60 hover:opacity-80">×</button></form>
                        </span>
                    @endforeach
                </div>
            </div>
            @endif

            @if ($canQuick)
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm" x-data="{ acc: {{ $quickAccounts->first()->id ?? 'null' }}, ids: @js($quickReplies->pluck('account_id')) }">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Quick Replies</h2>
                    @if ($quickAccounts->isNotEmpty())
                        <select x-model.number="acc" class="h-8 rounded-lg border-gray-200 text-xs">
                            @foreach ($quickAccounts as $acc)<option value="{{ $acc->id }}">{{ $acc->name }}{{ $acc->display_number ? ' · +'.$acc->display_number : '' }}</option>@endforeach
                        </select>
                    @endif
                </div>
                <p class="mb-4 mt-1 text-xs text-gray-400">Each number has its own quick replies — pick a number above to manage its set. You only see the numbers you have access to.</p>

                @if ($quickAccounts->isEmpty())
                    <p class="rounded-lg border border-dashed border-gray-100 px-3 py-4 text-center text-xs text-gray-400">You don't have access to any WhatsApp number yet.</p>
                @else
                    @if ($canQuick)
                        <form method="POST" action="{{ route('admin.whatsapp-settings.quick.store') }}" class="mb-4 space-y-2">
                            @csrf
                            <input type="hidden" name="account_id" :value="acc">
                            <input type="text" name="shortcut" placeholder="Shortcut (e.g. /hi)" class="h-9 w-full rounded-lg border-gray-200 text-sm">
                            <textarea name="body" required rows="2" placeholder="Message…" class="w-full rounded-lg border-gray-200 text-sm"></textarea>
                            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-white">Add Quick Reply</button>
                        </form>
                    @else
                        <p class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-700">You can view quick replies, but you don't have permission to add, edit or delete them.</p>
                    @endif
                    <ul class="space-y-2">
                        @foreach ($quickReplies as $qr)
                            <li x-show="acc === {{ $qr->account_id ?? 'null' }}" x-data="{ edit: false }" class="rounded-lg border border-gray-50 px-3 py-2">
                                <div x-show="!edit" class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        @if ($qr->shortcut)<span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-500">{{ $qr->shortcut }}</span>@endif
                                        <p class="mt-0.5 text-xs text-[var(--color-muted)]">{{ \Illuminate\Support\Str::limit($qr->body, 80) }}</p>
                                    </div>
                                    @if ($canQuick)
                                        <div class="flex shrink-0 items-center gap-1.5">
                                            <button type="button" @click="edit = true" class="text-gray-300 hover:text-[var(--color-primary)]" title="Edit">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                            </button>
                                            <form method="POST" action="{{ route('admin.whatsapp-settings.quick.destroy', $qr) }}" onsubmit="return confirm('Delete this quick reply?')">@csrf @method('DELETE')<button class="text-gray-300 hover:text-red-500" title="Delete">×</button></form>
                                        </div>
                                    @endif
                                </div>
                                @if ($canQuick)
                                    <form x-show="edit" x-cloak method="POST" action="{{ route('admin.whatsapp-settings.quick.update', $qr) }}" class="space-y-2">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="account_id" value="{{ $qr->account_id }}">
                                        <input type="text" name="shortcut" value="{{ $qr->shortcut }}" placeholder="Shortcut (e.g. /hi)" class="h-8 w-full rounded-lg border-gray-200 text-xs">
                                        <textarea name="body" required rows="2" class="w-full rounded-lg border-gray-200 text-xs">{{ $qr->body }}</textarea>
                                        <div class="flex gap-2">
                                            <button class="rounded-lg bg-[var(--color-primary)] px-3 py-1 text-xs font-semibold text-white">Save</button>
                                            <button type="button" @click="edit = false" class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-500">Cancel</button>
                                        </div>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                        <li x-show="ids.indexOf(acc) === -1" class="rounded-lg border border-dashed border-gray-100 px-3 py-4 text-center text-xs text-gray-400">No quick replies for this number yet.</li>
                    </ul>
                @endif
            </div>
            @endif
        </div>
        @endif
    </div>

    @if (! $canConnection && ! $canNumbers && ! $canWebhook && ! $canLabels && ! $canQuick)
        <p class="rounded-xl border border-dashed border-gray-200 bg-white p-10 text-center text-sm text-gray-400">You don't have permission to manage any WhatsApp Config section yet. Ask an admin to grant the sections you need.</p>
    @endif
@endsection
