{{-- WhatsApp message box with its formatting marks and a live preview.

     WhatsApp has no rich-text field: bold is literally *asterisks* around the words, and those
     characters travel in the link. So the buttons wrap the selection in the right marks, and the
     preview renders them the way WhatsApp will — otherwise you are writing punctuation and hoping.

     Included by both the create form and the edit modal, so `value` is whatever is being edited. --}}

<div x-data="waEditor(@js($value ?? ''))" class="rounded-lg border border-gray-200">
    <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 px-2 py-1.5">
        <template x-for="mark in marks" :key="mark.label">
            <button type="button" @click="wrap(mark)" :title="mark.title"
                    class="rounded px-2 py-1 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-100 hover:text-[var(--color-heading)]"
                    x-html="mark.label"></button>
        </template>
        <span class="ml-auto px-2 text-[11px] text-gray-400">Select text, then pick a style</span>
    </div>

    <textarea x-ref="box" name="message" rows="3" x-model="text"
              placeholder="Hi, I saw your ad and want to know more"
              class="w-full resize-y rounded-b-lg px-3 py-2 text-sm focus:outline-none"></textarea>

    <div class="border-t border-gray-100 bg-gray-50 px-3 py-2" x-show="text.trim()" x-cloak>
        <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">How it will look in WhatsApp</p>
        <p class="whitespace-pre-wrap text-sm text-[var(--color-heading)]" x-html="preview"></p>
    </div>
</div>

{{-- Inline, not @push: this layout has no scripts stack. @once keeps it to a single copy even
     though the partial is included by both the create form and every edit modal. --}}
@once
    <script>
            function waEditor(initial) {
                return {
                    text: initial || '',
                    marks: [
                        { label: '<b>B</b>', title: 'Bold — *text*', wrap: '*' },
                        { label: '<i>I</i>', title: 'Italic — _text_', wrap: '_' },
                        { label: '<s>S</s>', title: 'Strikethrough — ~text~', wrap: '~' },
                        { label: '<code>M</code>', title: 'Monospace — ```text```', wrap: '```' },
                    ],

                    // Wrap the selection, or drop the marks in and put the caret between them.
                    wrap(mark) {
                        const box = this.$refs.box;
                        const start = box.selectionStart, end = box.selectionEnd;
                        const chosen = this.text.slice(start, end);
                        const m = mark.wrap;

                        this.text = this.text.slice(0, start) + m + chosen + m + this.text.slice(end);

                        this.$nextTick(() => {
                            box.focus();
                            const caret = chosen ? end + (m.length * 2) : start + m.length;
                            box.setSelectionRange(chosen ? start : caret, caret);
                        });
                    },

                    // Same marks WhatsApp honours. Escaped first: the preview must never run markup
                    // that someone typed into the message.
                    get preview() {
                        const safe = this.text
                            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

                        return safe
                            .replace(/```([\s\S]+?)```/g, '<code class="rounded bg-gray-200 px-1">$1</code>')
                            .replace(/(^|\s)\*(\S[^*]*?)\*(?=\s|$|[.,!?])/g, '$1<b>$2</b>')
                            .replace(/(^|\s)_(\S[^_]*?)_(?=\s|$|[.,!?])/g, '$1<i>$2</i>')
                            .replace(/(^|\s)~(\S[^~]*?)~(?=\s|$|[.,!?])/g, '$1<s>$2</s>');
                    },
                };
            }
    </script>
@endonce
