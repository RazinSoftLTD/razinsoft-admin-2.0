@extends('admin.layouts.app')
@section('title', 'Edit Template')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
    <style>#tpl-editor .ql-editor{min-height:16rem;font-size:.9rem}</style>
@endpush

@section('content')
    <a href="{{ route('admin.email-settings') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Email Settings
    </a>

    <form method="POST" action="{{ route('admin.email-settings.templates.update', $template) }}" class="max-w-3xl" onsubmit="document.getElementById('body-input').value = window.__quill.root.innerHTML">
        @csrf @method('PUT')

        <div class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-base font-bold text-[var(--color-heading)]">Edit email template</h1>
                    <p class="text-xs text-[var(--color-muted)]">Key: <code class="rounded bg-gray-100 px-1.5 py-0.5">{{ $template->key }}</code></p>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium text-[var(--color-heading)]">
                    <input type="checkbox" name="is_active" value="1" @checked($template->is_active) class="h-4 w-4 rounded border-gray-300 text-[var(--color-primary)]">
                    Active
                </label>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Template name</label>
                <input name="name" value="{{ old('name', $template->name) }}" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Subject</label>
                <input name="subject" value="{{ old('subject', $template->subject) }}" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
            </div>

            @if ($template->variables)
                <div class="rounded-lg bg-[var(--color-primary-soft)] px-4 py-3 text-sm text-[var(--color-heading)]">
                    <p class="font-semibold">Available variables</p>
                    <p class="mt-1 flex flex-wrap gap-1.5">
                        @foreach (array_map('trim', explode(',', $template->variables)) as $v)
                            <code class="rounded bg-white/70 px-1.5 py-0.5 text-xs">&#123;&#123;{{ $v }}&#125;&#125;</code>
                        @endforeach
                    </p>
                </div>
            @endif

            <div>
                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Body</label>
                <div id="tpl-editor" class="rounded-lg border border-gray-200"></div>
                <input type="hidden" name="body" id="body-input" value="{{ old('body', $template->body) }}">
            </div>

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
        </div>

        <div class="mt-5 flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save template</button>
            <a href="{{ route('admin.email-settings') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>

    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script>
        (function () {
            const quill = new Quill('#tpl-editor', {
                theme: 'snow',
                modules: { toolbar: [['bold', 'italic', 'underline', 'link'], [{ header: [2, 3, false] }], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']] },
            });
            quill.clipboard.dangerouslyPasteHTML(document.getElementById('body-input').value);
            window.__quill = quill;
        })();
    </script>
@endsection
