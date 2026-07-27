@extends('admin.layouts.app')
@section('title', 'Meta Conversions API')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Meta Conversions API</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">
            Sends conversions to Meta from this server as well as from the browser pixel. Ad blockers and
            tracking prevention drop a large share of browser events; these arrive regardless, and carry the
            customer's real email and phone, which is what Meta matches on.
        </p>
    </div>

    @if (session('status'))<div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>@endif

    <div class="max-w-3xl">
        <form method="POST" action="{{ route('admin.meta-capi.update') }}" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm"
              x-data="{ show: false, on: {{ $settings->is_enabled ? 'true' : 'false' }} }">
            @csrf

            <label class="mb-5 flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                   :class="on ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200'">
                <input type="checkbox" name="is_enabled" value="1" x-model="on" class="mt-0.5 rounded border-gray-300 text-emerald-500 focus:ring-emerald-400">
                <span>
                    <span class="block text-sm font-bold text-[var(--color-heading)]">Send events to Meta</span>
                    <span class="mt-0.5 block text-xs leading-relaxed text-[var(--color-muted)]">
                        Off means nothing leaves this server, whatever else is filled in below.
                    </span>
                </span>
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Pixel ID (Dataset ID)</label>
                    <input type="text" name="pixel_id" value="{{ old('pixel_id', $settings->pixel_id) }}" placeholder="1234567890123456" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                    <p class="mt-1 text-xs text-gray-400">Events Manager &rsaquo; your dataset &rsaquo; Settings.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">API version</label>
                    <input type="text" name="api_version" value="{{ old('api_version', $settings->api_version ?: 'v21.0') }}" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Access Token</label>
                    <div class="flex items-center gap-2">
                        <input :type="show ? 'text' : 'password'" name="access_token" autocomplete="new-password"
                               value="{{ $settings->access_token }}" placeholder="Generate one in Events Manager"
                               class="h-10 min-w-0 flex-1 rounded-lg border-gray-200 text-sm">
                        <button type="button" @click="show = !show" title="Show / hide"
                                class="grid h-10 w-10 shrink-0 place-items-center rounded-lg border border-gray-200 text-gray-400 transition hover:bg-gray-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-400">Events Manager &rsaquo; dataset &rsaquo; Settings &rsaquo; Conversions API &rsaquo; <strong>Generate access token</strong>.</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-[var(--color-muted)]">Test Event Code <span class="font-normal text-gray-400">— optional</span></label>
                    <input type="text" name="test_event_code" value="{{ old('test_event_code', $settings->test_event_code) }}" placeholder="TEST12345" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                    <p class="mt-1 text-xs leading-relaxed text-gray-400">
                        From Events Manager &rsaquo; <strong>Test Events</strong>. While this is filled in, every event is flagged as
                        test data and stays out of real reporting &mdash; <strong>clear it once you are done testing</strong>.
                    </p>
                </div>
            </div>

            <p class="mb-2 mt-5 text-xs font-semibold text-[var(--color-muted)]">Which events to send</p>
            <div class="mb-5 grid gap-2 sm:grid-cols-2">
                @foreach (\App\Models\MetaCapiSetting::EVENTS as $key => $description)
                    <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-gray-200 p-3 text-xs hover:bg-gray-50">
                        <input type="checkbox" name="events[]" value="{{ $key }}" @checked(in_array($key, $settings->events ?? [], true)) class="mt-0.5 rounded border-gray-300 text-emerald-500 focus:ring-emerald-400">
                        <span>
                            <span class="block font-bold text-[var(--color-heading)]">{{ $key }}</span>
                            <span class="text-[var(--color-muted)]">{{ $description }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            @if ($settings->last_sent_at)
                <div class="mb-5 rounded-lg border p-3 text-xs {{ $settings->last_status === 'ok' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                    Last event {{ $settings->last_status === 'ok' ? 'accepted' : 'rejected' }} {{ $settings->last_sent_at->diffForHumans() }}.
                    @if ($settings->last_error)<span class="block">{{ $settings->last_error }}</span>@endif
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-2">
                <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save</button>
                <button type="submit" formaction="{{ route('admin.meta-capi.test') }}" class="rounded-lg border border-emerald-200 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Send test event</button>
            </div>
        </form>

        <div class="mt-6 rounded-xl border border-blue-200 bg-blue-50 p-5 text-sm">
            <p class="font-bold text-[var(--color-heading)]">One thing to set on the browser side</p>
            <p class="mt-1 text-xs leading-relaxed text-[var(--color-muted)]">
                Your pixel already fires through GTM. If it sends the same conversion the server sends, Meta will
                count it twice unless both carry the same <code class="rounded bg-white px-1">event_id</code>. The ids used here are
                predictable on purpose &mdash; set the GTM tag's Event ID to match:
            </p>
            <table class="mt-3 w-full text-xs">
                <tr class="text-left text-[var(--color-muted)]"><th class="pb-1 font-semibold">Event</th><th class="pb-1 font-semibold">Event ID</th></tr>
                <tr><td style="padding:2px 0">Purchase</td><td style="padding:2px 0"><code class="rounded bg-white px-1">order-&lt;order number&gt;</code></td></tr>
                <tr><td style="padding:2px 0">Lead (contact)</td><td style="padding:2px 0"><code class="rounded bg-white px-1">contact-&lt;id&gt;</code></td></tr>
                <tr><td style="padding:2px 0">Lead (meeting)</td><td style="padding:2px 0"><code class="rounded bg-white px-1">meeting-&lt;id&gt;</code></td></tr>
                <tr><td style="padding:2px 0">CompleteRegistration</td><td style="padding:2px 0"><code class="rounded bg-white px-1">signup-&lt;user id&gt;</code></td></tr>
            </table>
        </div>
    </div>
@endsection
