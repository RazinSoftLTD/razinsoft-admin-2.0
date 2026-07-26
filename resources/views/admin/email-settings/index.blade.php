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
                    {{-- The saved password is filled in rather than left blank: an empty box made it
                         look as though nothing had been stored. Hidden by default, with a toggle to
                         check it against the mail provider. --}}
                    <div class="sm:col-span-2" x-data="{ show: false }">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Password</label>
                        <div class="relative">
                            <input name="password" :type="show ? 'text' : 'password'"
                                   value="{{ old('password', $settings->password) }}"
                                   autocomplete="new-password"
                                   placeholder="App password / SMTP password"
                                   class="h-11 w-full rounded-lg border border-gray-200 px-3 pr-10 text-sm">
                            <button type="button" @click="show = !show"
                                    :title="show ? 'Hide password' : 'Show password'"
                                    class="absolute right-1 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                <svg x-show="!show" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-7 9.75-7 9.75 7 9.75 7-3.5 7-9.75 7S2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="2.75"/></svg>
                                <svg x-show="show" x-cloak class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a2.75 2.75 0 0 0 3.8 3.8M9.9 5.2A9.6 9.6 0 0 1 12 5c6.25 0 9.75 7 9.75 7a17 17 0 0 1-3.1 4M6.5 6.7A17 17 0 0 0 2.25 12S5.75 19 12 19c1.2 0 2.3-.26 3.3-.68"/></svg>
                            </button>
                        </div>
                        @if ($settings->password)
                            <p class="mt-1 flex items-center gap-1 text-xs text-emerald-600">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg>
                                Saved — stored encrypted.
                            </p>
                        @endif
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
                        {{-- The row still opens the editor; the switch turns the email on or off
                             without leaving the page. --}}
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 px-3 py-2.5 hover:bg-gray-50">
                            <a href="{{ route('admin.email-settings.templates.edit', $t) }}" class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-[var(--color-heading)]">{{ $t->name }}</span>
                                <span class="block truncate text-xs text-[var(--color-muted)]">{{ $t->subject }}</span>
                            </a>
                            <x-admin.email-template-toggle :template="$t" />
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
