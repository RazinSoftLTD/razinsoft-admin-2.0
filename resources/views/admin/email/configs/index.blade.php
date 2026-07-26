@extends('admin.layouts.app')
@section('title', 'Email Configuration')

@php
    $providers = \App\Models\EmailConfig::PROVIDERS;
    $healthTone = ['ok' => 'bg-emerald-50 text-emerald-700', 'failing' => 'bg-red-50 text-red-600', 'unknown' => 'bg-gray-100 text-gray-500'];
@endphp

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Email Management</h1>
        <p class="text-sm text-[var(--color-muted)]">The SMTP accounts this system sends through, and how mail is templated, queued and tracked.</p>
    </div>

    @include('admin.email._nav')

    <div x-data="emailConfigs()">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-[var(--color-heading)]">SMTP Accounts</h2>
                <p class="text-xs text-[var(--color-muted)]">The default sends everything. The others take over automatically when it fails or hits its limit.</p>
            </div>
            <button type="button" @click="openAdd()" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                Add SMTP Account
            </button>
        </div>

        @forelse ($configs as $config)
            <section class="mb-3 rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 px-5 py-4">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="truncate text-sm font-bold text-[var(--color-heading)]">{{ $config->name }}</span>
                            @if ($config->is_default)
                                <span class="rounded-full bg-[var(--color-primary)] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Default</span>
                            @endif
                            @unless ($config->is_active)
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-gray-500">Inactive</span>
                            @endunless
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $healthTone[$config->health] ?? 'bg-gray-100 text-gray-500' }}">
                                {{ \App\Models\EmailConfig::HEALTH[$config->health] ?? $config->health }}
                            </span>
                        </p>
                        <p class="mt-0.5 truncate text-xs text-[var(--color-muted)]">
                            {{ $config->providerLabel() }} · {{ $config->host }}:{{ $config->port }} · from {{ $config->from_email }}
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                        <form method="POST" action="{{ route('admin.email.configs.test', $config) }}">
                            @csrf
                            <button class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-heading)] hover:bg-gray-50">Test connection</button>
                        </form>
                        <button type="button" @click="openSendTest({{ $config->id }})" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-heading)] hover:bg-gray-50">Send test</button>
                        @unless ($config->is_default)
                            <form method="POST" action="{{ route('admin.email.configs.default', $config) }}">
                                @csrf
                                <button class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-heading)] hover:bg-gray-50">Make default</button>
                            </form>
                        @endunless
                        <button type="button" @click="openEdit({{ Illuminate\Support\Js::from([
                            'id' => $config->id, 'name' => $config->name, 'provider' => $config->provider,
                            'host' => $config->host, 'port' => $config->port, 'username' => $config->username,
                            'encryption' => $config->encryption ?: 'none', 'from_name' => $config->from_name,
                            'from_email' => $config->from_email, 'reply_to' => $config->reply_to,
                            'return_path' => $config->return_path, 'priority' => $config->priority,
                            'hourly_limit' => $config->hourly_limit, 'daily_limit' => $config->daily_limit,
                            'is_active' => (bool) $config->is_active, 'is_default' => (bool) $config->is_default,
                        ]) }})" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-heading)] hover:bg-gray-50">Edit</button>
                        <form method="POST" action="{{ route('admin.email.configs.destroy', $config) }}" onsubmit="return confirm('Remove “{{ $config->name }}”?')">
                            @csrf @method('DELETE')
                            <button class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Remove">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="grid gap-4 px-5 py-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['Sent today', $config->sent_today],
                        ['Failed today', $config->failed_today],
                        ['Hourly limit', $config->hourly_limit ? $config->sentInLastHour().' / '.$config->hourly_limit : 'No limit'],
                        ['Daily limit', $config->daily_limit ? $config->sent_today.' / '.$config->daily_limit : 'No limit'],
                    ] as [$label, $value])
                        <div>
                            <p class="text-[11px] uppercase tracking-wide text-gray-400">{{ $label }}</p>
                            <p class="text-sm font-bold text-[var(--color-heading)]">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($config->health === 'failing' && $config->last_error)
                    <p class="border-t border-gray-100 px-5 py-3 text-xs text-red-600">
                        Last error ({{ $config->last_checked_at?->diffForHumans() }}): {{ \Illuminate\Support\Str::limit($config->last_error, 300) }}
                    </p>
                @endif
            </section>
        @empty
            <div class="rounded-xl border border-dashed border-gray-200 py-12 text-center">
                <span class="mx-auto mb-3 grid h-11 w-11 place-items-center rounded-full bg-gray-50 text-gray-300">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
                </span>
                <p class="text-sm font-semibold text-[var(--color-heading)]">No SMTP account yet</p>
                <p class="mt-1 text-xs text-gray-400">Add one and this system can start sending mail.</p>
            </div>
        @endforelse

        {{-- Add / edit --}}
        <div x-show="form.open" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/40 p-4" @click.self="form.open = false">
            <form method="POST" :action="form.id ? '{{ url('admin/email/configs') }}/' + form.id : '{{ route('admin.email.configs.store') }}'"
                  class="mx-auto mt-6 w-full max-w-2xl rounded-xl bg-white shadow-xl" style="max-width:42rem">
                @csrf
                <template x-if="form.id"><input type="hidden" name="_method" value="PUT"></template>

                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-sm font-bold text-[var(--color-heading)]" x-text="form.id ? 'Edit SMTP account' : 'Add SMTP account'"></h3>
                    <button type="button" @click="form.open = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <div class="grid gap-4 p-6 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Provider</label>
                        <select name="provider" x-model="form.provider" @change="applyPreset()" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            @foreach ($providers as $key => $p)
                                <option value="{{ $key }}">{{ $p['label'] }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-[var(--color-muted)]" x-text="providers[form.provider]?.note"></p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Configuration name <span class="text-red-500">*</span></label>
                        <input name="name" x-model="form.name" required maxlength="120" placeholder="e.g. Gmail — support@" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">SMTP host <span class="text-red-500">*</span></label>
                        <input name="host" x-model="form.host" required maxlength="190" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Port <span class="text-red-500">*</span></label>
                        <input name="port" x-model.number="form.port" type="number" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Username</label>
                        <input name="username" x-model="form.username" autocomplete="off" maxlength="190" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </div>
                    <div x-data="{ show: false }">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Password</label>
                        <div class="relative">
                            <input name="password" :type="show ? 'text' : 'password'" autocomplete="new-password"
                                   :placeholder="form.id ? 'Leave blank to keep the saved one' : 'SMTP or app password'"
                                   class="h-11 w-full rounded-lg border border-gray-200 px-3 pr-10 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <button type="button" @click="show = !show" class="absolute right-1 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-gray-400 hover:bg-gray-100">
                                <svg x-show="!show" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-7 9.75-7 9.75 7 9.75 7-3.5 7-9.75 7S2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="2.75"/></svg>
                                <svg x-show="show" x-cloak class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a2.75 2.75 0 0 0 3.8 3.8M9.9 5.2A9.6 9.6 0 0 1 12 5c6.25 0 9.75 7 9.75 7a17 17 0 0 1-3.1 4M6.5 6.7A17 17 0 0 0 2.25 12S5.75 19 12 19c1.2 0 2.3-.26 3.3-.68"/></svg>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Encryption</label>
                        <select name="encryption" x-model="form.encryption" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            @foreach (\App\Models\EmailConfig::ENCRYPTIONS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Priority</label>
                        <input name="priority" x-model.number="form.priority" type="number" min="1" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        <p class="mt-1 text-xs text-gray-400">Lower runs first when falling back.</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">From name</label>
                        <input name="from_name" x-model="form.from_name" maxlength="120" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">From email <span class="text-red-500">*</span></label>
                        <input name="from_email" x-model="form.from_email" type="email" required maxlength="190" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Reply-To</label>
                        <input name="reply_to" x-model="form.reply_to" type="email" maxlength="190" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Return-Path</label>
                        <input name="return_path" x-model="form.return_path" type="email" maxlength="190" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        <p class="mt-1 text-xs text-gray-400">Where bounces are returned.</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Hourly limit</label>
                        <input name="hourly_limit" x-model.number="form.hourly_limit" type="number" min="1" placeholder="No limit" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Daily limit</label>
                        <input name="daily_limit" x-model.number="form.daily_limit" type="number" min="1" placeholder="No limit" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        <p class="mt-1 text-xs text-gray-400">Queue waits rather than exceeding the provider's cap.</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-[var(--color-muted)]">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]"> Active
                    </label>
                    <label class="flex items-center gap-2 text-sm text-[var(--color-muted)]">
                        <input type="checkbox" name="is_default" value="1" x-model="form.is_default" class="h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]"> Use as default sender
                    </label>
                </div>

                <div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4">
                    <button type="button" @click="form.open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                    <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]" x-text="form.id ? 'Save changes' : 'Add account'"></button>
                </div>
            </form>
        </div>

        {{-- Send test --}}
        <div x-show="test.open" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" @click.self="test.open = false">
            <form method="POST" :action="'{{ url('admin/email/configs') }}/' + test.id + '/send-test'" class="w-full rounded-xl bg-white p-6 shadow-xl" style="max-width:26rem">
                @csrf
                <h3 class="text-sm font-bold text-[var(--color-heading)]">Send a test email</h3>
                <p class="mt-1 text-xs text-[var(--color-muted)]">It is queued like any other message, so this also proves the worker is running.</p>
                <input name="to" type="email" required value="{{ auth()->user()->email }}" class="mt-4 h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="test.open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                    <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Send test</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function emailConfigs() {
            return {
                providers: @js($providers),
                blank: {
                    open: false, id: null, provider: 'custom', name: '', host: '', port: 587,
                    username: '', encryption: 'tls', from_name: '', from_email: '', reply_to: '',
                    return_path: '', priority: 10, hourly_limit: '', daily_limit: '',
                    is_active: true, is_default: false,
                },
                form: {},
                test: { open: false, id: null },
                init() { this.form = { ...this.blank }; },
                openAdd() { this.form = { ...this.blank, open: true }; },
                openEdit(config) { this.form = { ...this.blank, ...config, open: true }; },
                openSendTest(id) { this.test = { open: true, id }; },
                // Picking a provider fills in the details it always uses; everything stays editable.
                applyPreset() {
                    const p = this.providers[this.form.provider];
                    if (!p || this.form.provider === 'custom') return;
                    this.form.host = p.host;
                    this.form.port = p.port;
                    this.form.encryption = p.encryption;
                },
            };
        }
    </script>
@endsection
