@extends('admin.layouts.app')
@section('title', 'CodeCanyon Config')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">CodeCanyon Config</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">Settings &rsaquo; CodeCanyon Config — connect the official Envato API.</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-2">
        {{-- Token --}}
        <section class="rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
                <div>
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Envato API Connection</h2>
                    <p class="text-xs text-[var(--color-muted)]">Personal token — every Envato request needs one.</p>
                </div>
                @if ($settings->is_connected)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>Connected
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-500">
                        <span class="h-2 w-2 rounded-full bg-gray-400"></span>Not connected
                    </span>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.codecanyon-settings.save') }}" class="p-6" x-data="{ show: false, sync: {{ $settings->auto_sync ? 'true' : 'false' }} }">
                @csrf @method('PUT')

                <label class="mb-1 block text-xs font-semibold text-[var(--color-heading)]">Personal token</label>
                <div class="flex items-center gap-2">
                    <input :type="show ? 'text' : 'password'" name="personal_token" autocomplete="off"
                           placeholder="{{ $settings->isConfigured() ? '•••••••••••••••• (saved — leave blank to keep)' : 'Paste your Envato personal token' }}"
                           class="h-10 min-w-0 flex-1 rounded-lg border-gray-200 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                    <button type="button" @click="show = !show" class="grid h-10 w-10 shrink-0 place-items-center rounded-lg border border-gray-200 text-gray-400 transition hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                    </button>
                </div>
                <p class="mt-1.5 text-xs text-[var(--color-muted)]">
                    Create one at <a href="https://build.envato.com/create-token/" target="_blank" rel="noopener" class="font-semibold text-[var(--color-primary)] hover:underline">build.envato.com/create-token</a>.
                    Only the <strong>“View and search Envato sites”</strong> permission is needed. Stored encrypted.
                </p>

                <label class="mt-4 flex cursor-pointer items-start justify-between gap-4 rounded-lg border border-gray-200 p-4">
                    <span>
                        <span class="block text-sm font-semibold text-[var(--color-heading)]">Daily auto-sync</span>
                        <span class="mt-0.5 block text-xs text-[var(--color-muted)]">Runs at 04:00 and records the daily numbers — this is what builds the sales trend.</span>
                    </span>
                    <span class="relative mt-0.5 inline-flex shrink-0">
                        <input type="checkbox" name="auto_sync" value="1" x-model="sync" class="peer sr-only">
                        <span class="h-6 w-11 rounded-full bg-gray-200 transition peer-checked:bg-[var(--color-primary)]"></span>
                        <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition" :class="sync ? 'translate-x-5' : ''"></span>
                    </span>
                </label>

                <button class="mt-4 rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save &amp; verify</button>
            </form>
        </section>

        {{-- Status + what the API can and cannot give us --}}
        <section class="rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Status</h2>
            </div>
            <div class="space-y-2 p-6 text-sm">
                <div class="flex justify-between gap-3"><span class="text-[var(--color-muted)]">Token belongs to</span><span class="font-semibold text-[var(--color-heading)]">{{ $settings->verified_as ?? '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-[var(--color-muted)]">Verified</span><span class="font-semibold text-[var(--color-heading)]">{{ $settings->verified_at?->diffForHumans() ?? '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-[var(--color-muted)]">Last sync</span><span class="font-semibold text-[var(--color-heading)]">{{ $settings->last_synced_at?->diffForHumans() ?? 'never' }}</span></div>
                @if ($settings->last_error)
                    <p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">{{ $settings->last_error }}</p>
                @endif

                <div class="mt-4 rounded-lg bg-gray-50 p-4 text-xs leading-relaxed text-[var(--color-muted)]">
                    <p class="mb-1 font-bold text-[var(--color-heading)]">What the official API provides</p>
                    <p>Sales count, rating &amp; rating count, price, publish date, last update, category, tags, trending flag, author profile &amp; badges, buyer comments.</p>
                    <p class="mb-1 mt-3 font-bold text-[var(--color-heading)]">What it does not</p>
                    <p>Real earnings of other authors, historical sales, refunds, traffic or conversion data. Revenue shown in this module is
                        <strong>estimated</strong> (sales × current price), and sales trends come from our own daily snapshots — not from Envato.</p>
                </div>
            </div>
        </section>
    </div>
@endsection
