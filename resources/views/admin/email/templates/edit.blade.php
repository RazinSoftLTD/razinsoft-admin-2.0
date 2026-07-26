@extends('admin.layouts.app')
@section('title', 'Edit — '.$template->name)

@section('content')
    <div x-data="{ tab: 'html', testOpen: false, previewKey: 0 }">
        {{-- Header --}}
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <nav class="mb-1.5 flex items-center gap-2 text-sm text-[var(--color-muted)]">
                    <a href="{{ route('admin.email.templates') }}" class="hover:text-[var(--color-heading)]">Email Templates</a>
                    <svg class="h-3.5 w-3.5 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
                    <span class="truncate text-[var(--color-heading)]">{{ $template->name }}</span>
                </nav>
                <h1 class="flex flex-wrap items-center gap-2 text-xl font-bold text-[var(--color-heading)]">
                    {{ $template->name }}
                    @if ($template->is_system)
                        <span class="rounded-full bg-[var(--color-primary-soft)] px-2 py-0.5 text-[11px] font-semibold text-[var(--color-primary)]">Built-in</span>
                    @endif
                </h1>
                <code class="text-xs text-gray-400">{{ $template->key }}</code>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" @click="testOpen = true" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Send test</button>
                <button type="submit" form="template-form" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-5 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                    Save template
                </button>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form id="template-form" method="POST" action="{{ route('admin.email.templates.update', $template) }}" class="grid gap-5 xl:grid-cols-3">
            @csrf @method('PUT')

            {{-- Left: the content --}}
            <div class="space-y-5 xl:col-span-2">
                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">Content</h2>
                    </div>
                    <div class="space-y-4 p-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Template name <span class="text-red-500">*</span></label>
                                <input name="name" value="{{ old('name', $template->name) }}" required maxlength="120" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Category</label>
                                <select name="category" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                    @foreach (\App\Models\EmailTemplate::CATEGORIES as $k => $v)
                                        <option value="{{ $k }}" @selected(old('category', $template->category) === $k)>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Subject <span class="text-red-500">*</span></label>
                            <input name="subject" value="{{ old('subject', $template->subject) }}" required maxlength="190" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <p class="mt-1 text-xs text-gray-400">Say what the message is. Avoid all-caps and exclamation marks — both push mail towards spam.</p>
                        </div>

                        {{-- HTML / plain text / preview --}}
                        <div>
                            <div class="mb-2 flex gap-1 border-b border-gray-100">
                                @foreach ([['html', 'HTML body'], ['text', 'Plain text'], ['preview', 'Preview']] as [$key, $label])
                                    <button type="button" @click="tab = '{{ $key }}'; if ('{{ $key }}' === 'preview') previewKey++"
                                            :class="tab === '{{ $key }}' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]'"
                                            class="border-b-2 px-3 py-2 text-sm font-semibold transition">{{ $label }}</button>
                                @endforeach
                            </div>

                            <div x-show="tab === 'html'">
                                <textarea name="body" rows="20" required
                                          class="w-full rounded-lg border border-gray-200 px-3 py-2.5 font-mono text-xs leading-relaxed focus:border-[var(--color-primary)] focus:outline-none">{{ old('body', $template->body) }}</textarea>
                                <p class="mt-1 text-xs text-gray-400">Inline styles only — mail clients strip &lt;style&gt; blocks. Keep images small and always give them alt text.</p>
                            </div>

                            <div x-show="tab === 'text'" x-cloak>
                                <textarea name="body_text" rows="20" placeholder="Leave blank and it is generated from the HTML when you save."
                                          class="w-full rounded-lg border border-gray-200 px-3 py-2.5 font-mono text-xs leading-relaxed focus:border-[var(--color-primary)] focus:outline-none">{{ old('body_text', $template->body_text) }}</textarea>
                                <p class="mt-1 text-xs text-gray-400">Every message goes out with both parts. A missing or nonsense text part is itself a spam signal.</p>
                            </div>

                            <div x-show="tab === 'preview'" x-cloak>
                                <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                                    <p class="border-b border-gray-200 bg-white px-3 py-2 text-xs text-[var(--color-muted)]">Filled with example data. Save first to preview unsaved edits.</p>
                                    <iframe :key="previewKey" :src="'{{ route('admin.email.templates.preview', $template) }}?v=' + previewKey"
                                            class="w-full border-0 bg-white" style="height:520px" title="Template preview"></iframe>
                                </div>
                                <p class="mt-1 text-xs text-gray-400">
                                    <a href="{{ route('admin.email.templates.preview', $template) }}?text=1" target="_blank" rel="noopener" class="font-semibold text-[var(--color-primary)] hover:underline">View the plain-text version</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Right: settings + variables --}}
            <div class="space-y-5">
                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">Settings</h2>
                    </div>
                    <div class="space-y-4 p-5">
                        <label class="flex items-start gap-2.5">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active)) class="mt-0.5 h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]">
                            <span>
                                <span class="block text-sm font-medium text-[var(--color-heading)]">Active</span>
                                <span class="block text-xs text-[var(--color-muted)]">Turn this off and the system stops sending this email entirely.</span>
                            </span>
                        </label>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Description</label>
                            <textarea name="description" rows="2" maxlength="500" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">{{ old('description', $template->description) }}</textarea>
                            <p class="mt-1 text-xs text-gray-400">A note for whoever edits this next.</p>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Declared variables</label>
                            <input name="variables" value="{{ old('variables', $template->variables) }}" maxlength="500" placeholder="customer_name, invoice_number" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <p class="mt-1 text-xs text-gray-400">Comma separated — what the code sends to this template.</p>
                        </div>

                        @unless ($template->is_system)
                            <div class="border-t border-gray-100 pt-4">
                                <button type="button"
                                        @click="if (confirm('Delete this template?')) document.getElementById('delete-template').submit()"
                                        class="text-sm font-semibold text-red-600 hover:underline">Delete template</button>
                            </div>
                        @endunless
                    </div>
                </section>

                {{-- Variables --}}
                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">Variables</h2>
                    </div>
                    <div class="space-y-4 p-5">
                        <p class="text-xs text-[var(--color-muted)]">Write <code class="rounded bg-gray-100 px-1 py-0.5 text-[11px]">@{{name}}</code> in the subject or body. Anything with no value is removed rather than left showing braces.</p>

                        @if (count($variables['used']))
                            <div>
                                <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Used in this template</p>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($variables['used'] as $v)
                                        <code class="rounded bg-[var(--color-primary-soft)] px-1.5 py-0.5 text-[11px] font-semibold text-[var(--color-primary)]">{{ '{'.'{'.$v.'}'.'}' }}</code>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if (count($variables['declared']))
                            <div>
                                <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Sent by the code</p>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($variables['declared'] as $v)
                                        <code class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600">{{ '{'.'{'.$v.'}'.'}' }}</code>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div>
                            <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Always available</p>
                            <dl class="space-y-1.5">
                                @foreach ($variables['global'] as $name => $help)
                                    <div class="flex items-start justify-between gap-3">
                                        <code class="shrink-0 rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600">{{ '{'.'{'.$name.'}'.'}' }}</code>
                                        <span class="text-right text-[11px] text-gray-400">{{ $help }}</span>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    </div>
                </section>
            </div>
        </form>

        @unless ($template->is_system)
            <form id="delete-template" method="POST" action="{{ route('admin.email.templates.destroy', $template) }}" class="hidden">
                @csrf @method('DELETE')
            </form>
        @endunless

        {{-- Send test --}}
        <div x-show="testOpen" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" @click.self="testOpen = false">
            <form method="POST" action="{{ route('admin.email.templates.send-test', $template) }}" class="w-full rounded-xl bg-white p-6 shadow-xl" style="max-width:26rem">
                @csrf
                <h3 class="text-sm font-bold text-[var(--color-heading)]">Send a test copy</h3>
                <p class="mt-1 text-xs text-[var(--color-muted)]">Sent with example data through the default SMTP account, so you can check it in a real inbox.</p>
                <input name="to" type="email" required value="{{ auth()->user()->email }}" class="mt-4 h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="testOpen = false" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                    <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Send test</button>
                </div>
            </form>
        </div>
    </div>
@endsection
