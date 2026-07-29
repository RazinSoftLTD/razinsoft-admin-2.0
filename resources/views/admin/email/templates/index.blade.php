@extends('admin.layouts.app')
@section('title', 'Email Templates')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Email Manager</h1>
        <p class="text-sm text-[var(--color-muted)]">How mail is templated, queued, sent and tracked. SMTP accounts live under Email Config.</p>
    </div>

    @include('admin.email._nav')

    <div x-data="{ addOpen: false }">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Templates <span class="font-normal text-gray-400">({{ $total }})</span></h2>
                <p class="text-xs text-[var(--color-muted)]">Every message the system sends. Turning one off stops that email without touching any code.</p>
            </div>
            <button type="button" @click="addOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                New Template
            </button>
        </div>

        @foreach ($grouped as $category => $templates)
            <section class="mb-4 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <header class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <h3 class="text-sm font-bold text-[var(--color-heading)]">{{ $category }}</h3>
                    <span class="text-xs text-gray-400">{{ $templates->count() }} template(s)</span>
                </header>

                <div class="divide-y divide-gray-100">
                    @foreach ($templates as $template)
                        <div class="flex flex-wrap items-center gap-3 px-5 py-3.5 hover:bg-gray-50">
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('admin.email.templates.edit', $template) }}" class="truncate text-sm font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $template->name }}</a>
                                    @if ($template->is_system)
                                        <span class="rounded-full bg-[var(--color-primary-soft)] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[var(--color-primary)]">Built-in</span>
                                    @endif
                                </p>
                                <p class="mt-0.5 truncate text-xs text-[var(--color-muted)]">{{ $template->subject }}</p>
                            </div>

                            <code class="hidden shrink-0 rounded bg-gray-100 px-2 py-0.5 text-[11px] text-gray-500 sm:block">{{ $template->key }}</code>

                            <div class="flex shrink-0 items-center gap-2">
                                <x-admin.email-template-toggle :template="$template" />
                                <a href="{{ route('admin.email.templates.preview', $template) }}" target="_blank" rel="noopener"
                                   class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-heading)] hover:bg-gray-50">Preview</a>
                                <a href="{{ route('admin.email.templates.edit', $template) }}"
                                   class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-heading)] hover:bg-gray-50">Edit</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        {{-- New template --}}
        <div x-show="addOpen" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" @click.self="addOpen = false">
            <form method="POST" action="{{ route('admin.email.templates.store') }}" class="w-full rounded-xl bg-white p-6 shadow-xl" style="max-width:30rem">
                @csrf
                <h3 class="text-sm font-bold text-[var(--color-heading)]">New template</h3>
                <p class="mt-1 text-xs text-[var(--color-muted)]">You get a starting layout to edit; the key is how code refers to it later.</p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Name <span class="text-red-500">*</span></label>
                        <input name="name" required maxlength="120" placeholder="e.g. Quote Sent" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Key <span class="text-red-500">*</span></label>
                        <input name="key" required maxlength="60" placeholder="quote_sent" pattern="[a-z0-9_]+" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        <p class="mt-1 text-xs text-gray-400">Lowercase letters, numbers and underscores.</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Category</label>
                        <select name="category" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            @foreach (\App\Models\EmailTemplate::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected($k === 'Custom')>{{ $v }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Subject <span class="text-red-500">*</span></label>
                        <input name="subject" required maxlength="190" placeholder="Your quote from @{{company_name}}" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="addOpen = false" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                    <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Create</button>
                </div>
            </form>
        </div>
    </div>
@endsection
