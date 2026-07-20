@props([
    'name',
    'value' => null,
    'placeholder' => 'Write something…',
    'minHeight' => 150,
])

{{-- Small rich-text editor: a contenteditable surface plus a toolbar, no build step needed.
     The HTML is mirrored into a hidden input and sanitised again on the server. --}}
<div x-data="richEditor(@js(old($name, $value)))" class="rounded-lg border border-gray-200 focus-within:border-[var(--color-primary)]">
    <div class="flex flex-wrap items-center gap-0.5 border-b border-gray-100 px-2 py-1.5">
        @php
            $tools = [
                ['bold', 'Bold', 'M7 4h6a4 4 0 0 1 0 8H7zM7 12h7a4 4 0 0 1 0 8H7z'],
                ['italic', 'Italic', 'M14 4h-4M14 20h-4M15 4 9 20'],
                ['underline', 'Underline', 'M7 4v7a5 5 0 0 0 10 0V4M5 21h14'],
                ['strikeThrough', 'Strikethrough', 'M4 12h16M8 7a4 4 0 0 1 8 0M8 17a4 4 0 0 0 8 0'],
                ['insertUnorderedList', 'Bulleted list', 'M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01'],
                ['insertOrderedList', 'Numbered list', 'M10 6h10M10 12h10M10 18h10M4 6h2m-2 6h2m-2 6h2'],
                ['blockquote', 'Quote', 'M7 7h4v6H7zM13 7h4v6h-4zM7 13c0 2 1 3 3 4M13 13c0 2 1 3 3 4'],
                ['removeFormat', 'Clear formatting', 'M4 7h16M9 20l6-16M6 20h6'],
            ];
        @endphp

        @foreach ($tools as [$cmd, $label, $icon])
            <button type="button" title="{{ $label }}" @click="run('{{ $cmd }}')"
                    class="grid h-8 w-8 place-items-center rounded text-gray-500 transition hover:bg-gray-100 hover:text-[var(--color-heading)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
            </button>
        @endforeach

        <span class="mx-1 h-5 w-px bg-gray-200"></span>

        <button type="button" title="Add link" @click="addLink()"
                class="grid h-8 w-8 place-items-center rounded text-gray-500 transition hover:bg-gray-100 hover:text-[var(--color-heading)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
        </button>

        <span class="mx-1 h-5 w-px bg-gray-200"></span>

        <button type="button" title="Undo" @click="run('undo')" class="grid h-8 w-8 place-items-center rounded text-gray-500 transition hover:bg-gray-100 hover:text-[var(--color-heading)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10h10a5 5 0 0 1 0 10h-4M4 10l4-4M4 10l4 4"/></svg>
        </button>
        <button type="button" title="Redo" @click="run('redo')" class="grid h-8 w-8 place-items-center rounded text-gray-500 transition hover:bg-gray-100 hover:text-[var(--color-heading)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 10H10a5 5 0 0 0 0 10h4M20 10l-4-4M20 10l-4 4"/></svg>
        </button>
    </div>

    {{-- The editing surface. Pasting is forced to plain text so foreign markup never gets in. --}}
    <div x-ref="surface" contenteditable="true" data-placeholder="{{ $placeholder }}"
         @input="sync()" @blur="sync()"
         @paste.prevent="pastePlain($event)"
         class="rich-surface px-3 py-2 text-sm text-[var(--color-heading)] focus:outline-none"
         style="min-height: {{ $minHeight }}px"></div>

    <input type="hidden" name="{{ $name }}" x-ref="input" :value="html">
</div>

@once
    <style>
        .rich-surface:empty::before { content: attr(data-placeholder); color: #9ca3af; }
        .rich-surface ul { list-style: disc; padding-left: 1.25rem; }
        .rich-surface ol { list-style: decimal; padding-left: 1.25rem; }
        .rich-surface a { color: var(--color-primary); text-decoration: underline; }
        .rich-surface blockquote { border-left: 3px solid #e5e7eb; padding-left: .75rem; color: #6b7280; }
        .rich-surface p { margin: 0 0 .35rem; }
    </style>
    <script>
        function richEditor(initial) {
            return {
                html: initial || '',
                init() {
                    this.$refs.surface.innerHTML = this.html;
                },
                sync() {
                    const v = this.$refs.surface.innerHTML.trim();
                    this.html = (v === '<br>' || v === '<p><br></p>') ? '' : v;
                },
                run(cmd) {
                    this.$refs.surface.focus();
                    if (cmd === 'blockquote') {
                        document.execCommand('formatBlock', false, 'blockquote');
                    } else {
                        document.execCommand(cmd, false, null);
                    }
                    this.sync();
                },
                addLink() {
                    const url = prompt('Link URL (https://…)');
                    if (!url) return;
                    if (!/^(https?:\/\/|mailto:|\/)/i.test(url)) { alert('Only http(s), mailto or relative links are allowed.'); return; }
                    this.$refs.surface.focus();
                    document.execCommand('createLink', false, url);
                    this.sync();
                },
                pastePlain(e) {
                    const text = (e.clipboardData || window.clipboardData).getData('text/plain');
                    document.execCommand('insertText', false, text);
                    this.sync();
                },
            };
        }
    </script>
@endonce
