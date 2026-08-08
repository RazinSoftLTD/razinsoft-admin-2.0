@extends('admin.layouts.app')
@section('title', 'Razin AI')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">Razin AI</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">
                Automatic WhatsApp replies — FAQ first, OpenAI when the FAQ has no answer, a human when the AI raises its hand.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold {{ $keyConfigured ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                <span class="h-1.5 w-1.5 rounded-full {{ $keyConfigured ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
                {{ $keyConfigured ? 'OpenAI key connected' : 'No OpenAI key — FAQ replies only' }}
            </span>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold text-gray-500">{{ $repliesToday }} AI replies today</span>
        </div>
    </div>

    {{-- No "saved" banner here: the layout already shows session('status'), and two of them
         stacked read as two different messages. --}}
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- ══ Behaviour ══ --}}
        <form method="POST" action="{{ route('admin.razin-ai.update') }}" class="space-y-6">
            @csrf
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">When may it speak</h2>

                <div class="space-y-2">
                    @foreach (['off' => ['Off', 'The assistant never replies.'], 'always' => ['Always', 'Replies whenever no agent has answered first.'], 'outside_hours' => ['Outside office hours only', 'The team answers during the day; the assistant covers the night and holidays.']] as $value => [$label, $desc])
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition {{ ($settings['mode'] ?? 'off') === $value ? 'border-[var(--color-primary)] bg-[var(--color-primary-soft)]' : 'border-gray-200 hover:bg-gray-50' }}">
                            <input type="radio" name="mode" value="{{ $value }}" @checked(($settings['mode'] ?? 'off') === $value) class="mt-0.5 accent-[var(--color-primary)]">
                            <span>
                                <span class="block text-sm font-bold text-[var(--color-heading)]">{{ $label }}</span>
                                <span class="block text-xs text-[var(--color-muted)]">{{ $desc }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-5">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Who it replies to</label>
                    <div class="space-y-2">
                        @foreach (['new_only' => ['New customers only', 'Chats where the team has never spoken and no client account is linked. Existing relationships stay with the people who built them.'], 'everyone' => ['Everyone', 'Every chat on the enabled numbers, old and new.']] as $value => [$label, $desc])
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition {{ ($settings['audience'] ?? 'new_only') === $value ? 'border-[var(--color-primary)] bg-[var(--color-primary-soft)]' : 'border-gray-200 hover:bg-gray-50' }}">
                                <input type="radio" name="audience" value="{{ $value }}" @checked(($settings['audience'] ?? 'new_only') === $value) class="mt-0.5 accent-[var(--color-primary)]">
                                <span>
                                    <span class="block text-sm font-bold text-[var(--color-heading)]">{{ $label }}</span>
                                    <span class="block text-xs text-[var(--color-muted)]">{{ $desc }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Office opens</label>
                        <input type="time" name="office_start" value="{{ $settings['office_start'] }}" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Office closes</label>
                        <input type="time" name="office_end" value="{{ $settings['office_end'] }}" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Timezone</label>
                        <input type="text" name="timezone" value="{{ $settings['timezone'] }}" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                    </div>
                </div>

                <div class="mt-4">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Office days</label>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $day => $label)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="office_days[]" value="{{ $day }}" @checked(in_array($day, $settings['office_days'] ?? [], true)) class="peer hidden">
                                <span class="inline-block rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-500 transition peer-checked:border-[var(--color-primary)] peer-checked:bg-[var(--color-primary-soft)] peer-checked:text-[var(--color-primary)]">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Which numbers answer</h2>
                <div class="space-y-2">
                    @forelse ($accounts as $acc)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 p-3 transition hover:bg-gray-50">
                            <input type="checkbox" name="account_ids[]" value="{{ $acc->id }}" @checked($acc->ai_reply_enabled) class="accent-[var(--color-primary)]">
                            <span class="grid h-8 w-8 place-items-center rounded-full text-white" style="background: {{ $acc->color }}">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Z"/></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-[var(--color-heading)]">{{ $acc->name }}</span>
                                <span class="block text-xs text-gray-400">{{ $acc->display_number ? '+'.ltrim($acc->display_number, '+') : 'not connected' }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="py-4 text-center text-sm text-gray-400">No WhatsApp numbers connected yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Voice &amp; limits</h2>

                <label class="mb-1.5 block text-xs font-semibold text-gray-500">System prompt — who the assistant is and how it behaves</label>
                <textarea name="system_prompt" rows="5" class="w-full rounded-lg border-gray-200 text-sm">{{ $settings['system_prompt'] }}</textarea>

                <label class="mb-1.5 mt-4 block text-xs font-semibold text-gray-500">Handover message — sent once when the assistant passes to the team</label>
                <textarea name="handover_message" rows="2" class="w-full rounded-lg border-gray-200 text-sm">{{ $settings['handover_message'] ?? 'Thanks for reaching out! A member of our team will take it from here and reply to you shortly.' }}</textarea>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">OpenAI model</label>
                        <input type="text" name="model" value="{{ $settings['model'] }}" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                        <p class="mt-1 text-[11px] text-gray-400">A string, so switching models is an edit here — no deploy.</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Max AI replies per chat per day</label>
                        <input type="number" name="max_replies_per_chat_per_day" min="1" max="200" value="{{ $settings['max_replies_per_chat_per_day'] }}" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                        <p class="mt-1 text-[11px] text-gray-400">Past this, the chat belongs to a person.</p>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Test numbers</label>
                    <input type="text" name="test_numbers" value="{{ $settings['test_numbers'] ?? '' }}" placeholder="01316885500, 8801XXXXXXXXX"
                           class="h-10 w-full rounded-lg border-gray-200 text-sm">
                    <p class="mt-1 text-[11px] text-gray-400">
                        Your own lines, comma separated. Razin AI always answers these — even with the mode off, inside office hours,
                        or after your team has replied — so you can try it whenever you like. Everything else (blocked contacts, groups,
                        the daily cap) still applies.
                    </p>
                </div>
            </div>

            <button class="rounded-lg bg-[var(--color-primary)] px-6 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save settings</button>
        </form>

        {{-- ══ FAQ shelf ══ --}}
        <div class="space-y-6 xl:col-span-2">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">FAQ shelf — answered before OpenAI</h2>
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-bold text-gray-500">{{ $faqs->count() }} {{ Str::plural('entry', $faqs->count()) }}</span>
                </div>

                <form method="POST" action="{{ route('admin.razin-ai.faqs.store') }}" class="mb-6 rounded-xl bg-gray-50 p-5">
                    @csrf
                    <p class="mb-4 flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-gray-500">
                        <svg class="h-4 w-4 text-[var(--color-primary)]" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        Add a new FAQ
                    </p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-500">Category</label>
                            <input type="text" name="category" list="faq-categories" placeholder="Ready eCommerce" class="h-10 w-full rounded-lg border-gray-200 bg-white text-sm">
                            <datalist id="faq-categories">
                                @foreach ($faqs->pluck('category')->filter()->unique() as $cat)<option value="{{ $cat }}">@endforeach
                            </datalist>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-500">Keywords <span class="font-normal text-gray-400">— comma-separated, any one fires</span></label>
                            <input type="text" name="keywords" required placeholder="price, দাম, pricing" class="h-10 w-full rounded-lg border-gray-200 bg-white text-sm">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Reply <span class="font-normal text-gray-400">— sent exactly as written</span></label>
                        <textarea name="reply" rows="3" required placeholder="Our products start at $39 — see razinsoft.com/products for every plan." class="w-full rounded-lg border-gray-200 bg-white text-sm"></textarea>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-3">
                        <p class="text-[11px] text-gray-400">A keyword hit answers instantly from here — no OpenAI call, no cost.</p>
                        <button class="shrink-0 rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-xs font-bold text-white hover:bg-[var(--color-primary-hover)]">Add FAQ</button>
                    </div>
                </form>

                <div class="space-y-5">
                    @forelse ($faqs->groupBy(fn ($f) => $f->category ?: 'General') as $category => $group)
                        <div>
                            <p class="mb-2 flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                {{ $category }}
                                <span class="rounded-full bg-gray-100 px-1.5 text-[10px] font-bold text-gray-400" style="text-transform:none;letter-spacing:normal">{{ $group->count() }}</span>
                            </p>
                            <div class="space-y-3">
                                @foreach ($group as $faq)
                                    <div x-data="{ editing: false }" class="rounded-lg border p-4 {{ $faq->is_active ? 'border-gray-200' : 'border-gray-100 opacity-60' }}">
                                        <div x-show="!editing">
                                            <div class="flex flex-wrap items-start justify-between gap-2">
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach (explode(',', $faq->keywords) as $kw)
                                                        <span class="rounded-full bg-[var(--color-primary-soft)] px-2 py-0.5 text-[11px] font-bold text-[var(--color-primary)]">{{ trim($kw) }}</span>
                                                    @endforeach
                                                </div>
                                                <div class="flex items-center gap-1">
                                                    <span class="mr-1 text-[11px] text-gray-400" title="How many times this FAQ answered">{{ number_format($faq->hit_count) }} hits</span>
                                                    <button type="button" @click="editing = true" class="rounded-lg px-2 py-1 text-[11px] font-bold text-gray-500 hover:bg-gray-100" title="Edit">Edit</button>
                                                    <form method="POST" action="{{ route('admin.razin-ai.faqs.toggle', $faq) }}">@csrf @method('PATCH')
                                                        <button class="rounded-lg px-2 py-1 text-[11px] font-bold {{ $faq->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">{{ $faq->is_active ? 'Active' : 'Paused' }}</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.razin-ai.faqs.destroy', $faq) }}" onsubmit="return confirm('Remove this FAQ?')">@csrf @method('DELETE')
                                                        <button class="rounded-lg p-1.5 text-gray-300 hover:bg-red-50 hover:text-red-600" title="Delete">
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            <p class="mt-2 whitespace-pre-line text-sm text-[var(--color-muted)]">{{ Str::limit($faq->reply, 350) }}</p>
                                        </div>

                                        {{-- In-place edit: same three fields the add form has. --}}
                                        <form x-show="editing" x-cloak method="POST" action="{{ route('admin.razin-ai.faqs.update', $faq) }}" class="space-y-3">
                                            @csrf @method('PUT')
                                            <input type="text" name="category" list="faq-categories" value="{{ $faq->category }}" placeholder="Category" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                                            <input type="text" name="keywords" required value="{{ $faq->keywords }}" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                                            <textarea name="reply" rows="6" required class="w-full rounded-lg border-gray-200 text-sm">{{ $faq->reply }}</textarea>
                                            <div class="flex gap-2">
                                                <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save</button>
                                                <button type="button" @click="editing = false" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-500 hover:bg-gray-50">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-gray-400">No FAQs yet — every reply goes to OpenAI until you add some.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Recent auto-replies</h2>
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-bold text-gray-500">last {{ $recentReplies->count() }}</span>
                </div>

                @if ($recentReplies->isEmpty())
                    <p class="py-6 text-center text-sm text-gray-400">Nothing yet — replies appear here the moment the assistant sends one.</p>
                @else
                    @php
                        $srcChip = [
                            'faq' => ['FAQ', 'bg-emerald-50 text-emerald-700'],
                            'openai' => ['AI', 'bg-violet-50 text-violet-600'],
                            'handover' => ['Handover', 'bg-amber-50 text-amber-700'],
                        ];
                    @endphp
                    <div class="divide-y divide-gray-50">
                        @foreach ($recentReplies as $r)
                            @php [$srcLabel, $srcTone] = $srcChip[$r->ai_source] ?? ['AI', 'bg-violet-50 text-violet-600']; @endphp
                            <div class="flex items-start gap-3 py-2.5">
                                <span class="mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold {{ $srcTone }}">{{ $srcLabel }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                                        @if ($r->chat)
                                            <a href="{{ route('admin.whatsapp-activity.thread', [$r->chat->account_id, $r->chat->id]) }}" class="truncate text-sm font-semibold text-[var(--color-heading)] hover:underline">{{ $r->chat->displayName() }}</a>
                                        @else
                                            <span class="text-sm font-semibold text-gray-400">—</span>
                                        @endif
                                        <span class="shrink-0 text-[11px] text-gray-400" title="{{ $r->created_at->format('d M Y, h:i A') }}">{{ $r->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="truncate text-xs text-[var(--color-muted)]">{{ Str::limit($r->body, 110) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>
@endsection
