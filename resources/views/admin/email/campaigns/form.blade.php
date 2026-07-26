@extends('admin.layouts.app')
@section('title', $campaign->exists ? 'Edit — '.$campaign->name : 'New Email')

@section('content')
    <div x-data="campaignForm()">
        <div class="mb-5">
            <nav class="mb-1.5 flex items-center gap-2 text-sm text-[var(--color-muted)]">
                <a href="{{ route('admin.email.campaigns') }}" class="hover:text-[var(--color-heading)]">Manual Email</a>
                <svg class="h-3.5 w-3.5 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
                <span class="text-[var(--color-heading)]">{{ $campaign->exists ? 'Edit' : 'New' }}</span>
            </nav>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">{{ $campaign->exists ? $campaign->name : 'New email' }}</h1>
        </div>

        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ $campaign->exists ? route('admin.email.campaigns.update', $campaign) : route('admin.email.campaigns.store') }}"
              class="grid gap-5 xl:grid-cols-3">
            @csrf
            @if ($campaign->exists) @method('PUT') @endif

            {{-- The message --}}
            <div class="space-y-5 xl:col-span-2">
                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">Message</h2>
                    </div>
                    <div class="space-y-4 p-5">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Internal name <span class="text-red-500">*</span></label>
                            <input name="name" value="{{ old('name', $campaign->name) }}" required maxlength="150" placeholder="e.g. July product announcement" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <p class="mt-1 text-xs text-gray-400">Only shown in this panel, never to recipients.</p>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Subject <span class="text-red-500">*</span></label>
                            <input name="subject" value="{{ old('subject', $campaign->subject) }}" required maxlength="190" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <p class="mt-1 text-xs text-gray-400">Say plainly what it is. All-caps and exclamation marks push mail towards spam.</p>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Message <span class="text-red-500">*</span></label>
                            @php
                                $brace = '{'.'{';
                                $endBrace = '}'.'}';
                                $starterBody = "<p>Hi {$brace}customer_name{$endBrace},</p>\n<p>Write your message here.</p>";
                            @endphp
                            <textarea name="body_html" rows="16" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 font-mono text-xs leading-relaxed focus:border-[var(--color-primary)] focus:outline-none">{{ old('body_html', $campaign->body_html ?: $starterBody) }}</textarea>
                            <p class="mt-1 text-xs text-gray-400">
                                HTML with inline styles. <code class="rounded bg-gray-100 px-1 py-0.5">{{ $brace }}customer_name{{ $endBrace }}</code> is filled per recipient.
                                A plain-text version is generated automatically.
                            </p>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Who, and when --}}
            <div class="space-y-5">
                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">Who it goes to</h2>
                    </div>
                    <div class="space-y-4 p-5">
                        <div>
                            <select name="audience_type" x-model="type" @change="values = []; refreshCount()" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                @foreach ($audienceTypes as $key => $label)
                                    <option value="{{ $key }}" @selected(old('audience_type', $campaign->audience['type'] ?? 'all') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- The choices for whichever filter is picked. --}}
                        <div x-show="type !== 'all' && type !== 'selected'" x-cloak>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Choose values</label>
                            <div class="max-h-56 space-y-1.5 overflow-auto rounded-lg border border-gray-200 p-3">
                                <template x-for="opt in currentOptions" :key="opt.value">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="audience_values[]" :value="opt.value" x-model="values" @change="refreshCount()"
                                               class="h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]">
                                        <span class="truncate text-[var(--color-heading)]" x-text="opt.label"></span>
                                    </label>
                                </template>
                                <p x-show="!currentOptions.length" class="text-xs text-gray-400">Nothing to choose from yet.</p>
                            </div>
                        </div>

                        <div x-show="type === 'selected'" x-cloak>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Client IDs</label>
                            <input type="text" @change="values = $event.target.value.split(',').map(v => v.trim()).filter(Boolean); refreshCount()"
                                   placeholder="12, 44, 91" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <template x-for="v in values" :key="v"><input type="hidden" name="audience_values[]" :value="v"></template>
                        </div>

                        <div class="rounded-lg bg-gray-50 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wide text-gray-400">Will reach</p>
                            <p class="text-lg font-extrabold text-[var(--color-heading)]">
                                <span x-text="counting ? '…' : count"></span> <span class="text-sm font-medium text-[var(--color-muted)]">recipient(s)</span>
                            </p>
                            <p class="mt-0.5 text-xs text-gray-400">Suppressed addresses are already excluded.</p>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-3.5">
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">Settings</h2>
                    </div>
                    <div class="space-y-4 p-5">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Wrap in a template</label>
                            <select name="email_template_id" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                <option value="">Send the message as written</option>
                                @foreach ($templates as $t)
                                    <option value="{{ $t->id }}" @selected(old('email_template_id', $campaign->email_template_id) == $t->id)>{{ $t->name }} ({{ $t->category }})</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-400">Newsletter and Marketing Campaign carry the shared header and footer.</p>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Send through</label>
                            <select name="email_config_id" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                <option value="">Default SMTP account</option>
                                @foreach ($configs as $id => $name)
                                    <option value="{{ $id }}" @selected(old('email_config_id', $campaign->email_config_id) == $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Schedule for</label>
                            <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $campaign->scheduled_at?->format('Y-m-d\TH:i')) }}"
                                   class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <p class="mt-1 text-xs text-gray-400">Leave blank to send straight away.</p>
                        </div>
                    </div>
                </section>

                <div class="flex flex-wrap gap-2">
                    <button name="action" value="draft" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Save draft</button>
                    <button name="action" value="schedule" class="rounded-lg border border-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-[var(--color-primary)] hover:bg-[var(--color-primary-soft)]">Schedule</button>
                    <button name="action" value="send"
                            onclick="return confirm('Send this to everyone the filter matches? This cannot be undone.')"
                            class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Send now</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function campaignForm() {
            return {
                type: @js(old('audience_type', $campaign->audience['type'] ?? 'all')),
                values: @js(old('audience_values', $campaign->audience['values'] ?? [])),
                options: @js($audienceOptions),
                count: {{ $totalClients }},
                counting: false,

                get currentOptions() {
                    const raw = this.options[this.type] || [];
                    // Each filter's option list has a different shape; normalise to {value,label}.
                    return raw.map((o, i) => typeof o === 'object'
                        ? { value: o.id ?? o.value ?? i, label: o.label ?? o.name ?? String(o) }
                        : { value: o, label: o });
                },

                // Ask the server how many people the filter reaches, so the number shown before
                // Send is the number that will really be mailed.
                async refreshCount() {
                    this.counting = true;
                    const params = new URLSearchParams({ type: this.type });
                    this.values.forEach(v => params.append('values[]', v));
                    try {
                        const r = await fetch('{{ route('admin.email.campaigns.audience-count') }}?' + params, { credentials: 'same-origin' });
                        this.count = (await r.json()).count;
                    } catch { /* leave the last known number rather than showing a wrong one */ }
                    this.counting = false;
                },
            };
        }
    </script>
@endsection
