@extends('admin.layouts.app')
@section('title', 'Notification Rules')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Email Manager</h1>
        <p class="text-sm text-[var(--color-muted)]">How mail is templated, queued, sent and tracked. SMTP accounts live under Email Config.</p>
    </div>

    @include('admin.email._nav')

    <form method="POST" action="{{ route('admin.email.rules.update') }}">
        @csrf

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-[var(--color-heading)]">What the system emails about</h2>
                <p class="text-xs text-[var(--color-muted)]">Turning an event off stops that email everywhere, without changing any code or the template it uses.</p>
            </div>
            <button class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                Save rules
            </button>
        </div>

        @forelse ($grouped as $group => $rules)
            <section class="mb-4 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <header class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <h3 class="text-sm font-bold text-[var(--color-heading)]">{{ $group }}</h3>
                    <span class="text-xs text-gray-400">{{ $rules->where('is_enabled', true)->count() }} of {{ $rules->count() }} on</span>
                </header>

                <div class="divide-y divide-gray-100">
                    @foreach ($rules as $rule)
                        <div class="flex flex-wrap items-center gap-4 px-5 py-3.5">
                            <label class="flex min-w-0 flex-1 items-start gap-3">
                                <input type="checkbox" name="enabled[{{ $rule->id }}]" value="1" @checked($rule->is_enabled)
                                       class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 accent-[var(--color-primary)]">
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-[var(--color-heading)]">{{ $rule->name }}</span>
                                    <span class="block text-xs text-[var(--color-muted)]">{{ $rule->description }}</span>
                                    <code class="mt-0.5 block text-[11px] text-gray-300">{{ $rule->key }}</code>
                                </span>
                            </label>

                            <div class="w-full shrink-0" style="max-width:16rem">
                                <select name="template[{{ $rule->id }}]" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                    <option value="">No template</option>
                                    @foreach ($templates as $id => $name)
                                        <option value="{{ $id }}" @selected($rule->email_template_id == $id)>{{ $name }}</option>
                                    @endforeach
                                </select>
                                {{-- A rule pointing at a switched-off template would never send, and the
                                     reason would be two screens away. --}}
                                @if ($rule->is_enabled && $rule->template && ! $rule->template->is_active)
                                    <p class="mt-1 text-xs text-amber-700">Its template is turned off, so this still will not send.</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="rounded-xl border border-dashed border-gray-200 py-12 text-center">
                <p class="text-sm font-semibold text-[var(--color-heading)]">No rules yet</p>
                <p class="mt-1 text-xs text-gray-400">Run <code class="rounded bg-gray-100 px-1.5 py-0.5">php artisan email:seed-rules</code> to install the defaults.</p>
            </div>
        @endforelse
    </form>
@endsection
