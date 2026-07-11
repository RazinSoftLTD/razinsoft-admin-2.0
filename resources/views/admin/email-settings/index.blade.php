@extends('admin.layouts.app')
@section('title', 'Email / SMTP')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Email / SMTP Settings</h1>
        <p class="text-sm text-[var(--color-muted)]">Configure how the system sends email, and edit the templates it uses.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- SMTP config --}}
        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.email-settings.update') }}" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                @csrf
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">SMTP Configuration</h2>
                    <label class="flex items-center gap-2 text-sm font-medium text-[var(--color-heading)]">
                        <input type="checkbox" name="is_enabled" value="1" @checked($settings->is_enabled) class="h-4 w-4 rounded border-gray-300 text-[var(--color-primary)]">
                        Enabled
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Mailer</label>
                        <select name="mailer" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                            @foreach (['smtp' => 'SMTP', 'log' => 'Log (dev)', 'sendmail' => 'Sendmail'] as $v => $l)
                                <option value="{{ $v }}" @selected($settings->mailer === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Encryption</label>
                        <select name="encryption" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                            @foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $v => $l)
                                <option value="{{ $v }}" @selected(($settings->encryption ?: 'none') === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Host</label>
                        <input name="host" value="{{ old('host', $settings->host) }}" placeholder="smtp.gmail.com" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Port</label>
                        <input name="port" type="number" value="{{ old('port', $settings->port) }}" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Username</label>
                        <input name="username" value="{{ old('username', $settings->username) }}" autocomplete="off" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Password</label>
                        <input name="password" type="password" autocomplete="new-password" placeholder="{{ $settings->password ? '•••••••• (leave blank to keep)' : 'App password / SMTP password' }}" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">From address</label>
                        <input name="from_address" value="{{ old('from_address', $settings->from_address) }}" placeholder="hello@razinsoft.com" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">From name</label>
                        <input name="from_name" value="{{ old('from_name', $settings->from_name) }}" placeholder="RazinSoft" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                    </div>
                </div>

                @if ($errors->any())
                    <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                @endif

                <button class="mt-5 rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save settings</button>
            </form>

            {{-- Test email --}}
            <form method="POST" action="{{ route('admin.email-settings.test') }}" class="mt-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                @csrf
                <h2 class="mb-2 text-sm font-bold text-[var(--color-heading)]">Send a test email</h2>
                <div class="flex flex-wrap gap-2">
                    <input name="test_email" type="email" required value="{{ auth()->user()->email }}" class="h-11 flex-1 rounded-lg border border-gray-200 px-3 text-sm">
                    <button class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Send test</button>
                </div>
                <p class="mt-2 text-xs text-[var(--color-muted)]">Save your settings first, then send a test to confirm delivery.</p>
            </form>
        </div>

        {{-- Templates --}}
        <div>
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-bold text-[var(--color-heading)]">Email Templates</h2>
                <div class="space-y-2">
                    @foreach ($templates as $t)
                        <a href="{{ route('admin.email-settings.templates.edit', $t) }}" class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 px-3 py-2.5 hover:bg-gray-50">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-[var(--color-heading)]">{{ $t->name }}</span>
                                <span class="block truncate text-xs text-[var(--color-muted)]">{{ $t->subject }}</span>
                            </span>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $t->is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-gray-100 text-gray-400' }}">{{ $t->is_active ? 'Active' : 'Off' }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
