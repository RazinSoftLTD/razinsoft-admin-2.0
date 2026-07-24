{{-- Mark Done modal (+ optional Schedule Next). Opened via window event `open-done` with { action, leadName, followUpTitle }. --}}
<div x-data="{
        open: false, action: '', leadName: '', followUpTitle: '',
        note: '', scheduleNext: false,
        type: 'call', priority: 'medium', fuNote: '', date: '', time: '10:00',
        _p(n) { return String(n).padStart(2, '0'); },
        _ymd(d) { return `${d.getFullYear()}-${this._p(d.getMonth() + 1)}-${this._p(d.getDate())}`; },
        get dt() { return this.date ? `${this.date}T${this.time || '00:00'}` : ''; },
        offsetYmd(days) { const d = new Date(); d.setDate(d.getDate() + days); return this._ymd(d); },
        setDate(days) { this.date = this.offsetYmd(days); },
        isDay(days) { return this.date === this.offsetYmd(days); },
        reset() { this.note = ''; this.scheduleNext = false; this.type = 'call'; this.priority = 'medium'; this.fuNote = ''; this.date = ''; this.time = '10:00'; }
     }"
     @open-done.window="open = true; action = $event.detail.action; leadName = $event.detail.leadName || ''; followUpTitle = $event.detail.followUpTitle || ''; reset()"
     @keydown.escape.window="open = false" x-cloak>

    <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm" @click="open = false"></div>

    <div x-show="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-16" @click.self="open = false">
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5">

            <div class="relative flex items-start gap-3 bg-gradient-to-br from-emerald-500 to-emerald-600 px-5 py-4 text-white">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white/20 ring-1 ring-white/30">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-bold">Mark Follow-up Done</h3>
                    <p class="mt-0.5 truncate text-xs text-white/80"><span x-text="leadName"></span><span x-show="followUpTitle"> · <span x-text="followUpTitle"></span></span></p>
                </div>
                <button type="button" @click="open = false" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-white/80 transition hover:bg-white/20 hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <form method="POST" :action="action" data-turbo="false" class="px-5 py-5">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-400">Completion Notes <span class="text-red-500">*</span></label>
                        <textarea name="completion_note" x-model="note" rows="3" required maxlength="2000" placeholder="What was the outcome of this follow-up?"
                                  class="w-full rounded-xl border-gray-200 bg-gray-50 text-sm transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/20"></textarea>
                    </div>

                    {{-- Auto-captured completion meta --}}
                    <div class="grid grid-cols-3 gap-2 rounded-xl bg-gray-50 p-3 text-center">
                        <div><p class="text-[10px] font-bold uppercase text-gray-400">Date</p><p class="mt-0.5 text-xs font-semibold text-[var(--color-heading)]">{{ now()->format('d M Y') }}</p></div>
                        <div><p class="text-[10px] font-bold uppercase text-gray-400">Time</p><p class="mt-0.5 text-xs font-semibold text-[var(--color-heading)]">{{ now()->format('h:i A') }}</p></div>
                        <div><p class="text-[10px] font-bold uppercase text-gray-400">By</p><p class="mt-0.5 truncate text-xs font-semibold text-[var(--color-heading)]">{{ auth()->user()->name }}</p></div>
                    </div>

                    {{-- Schedule next toggle --}}
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 p-3">
                        <input type="checkbox" name="schedule_next" value="1" x-model="scheduleNext" class="h-4 w-4 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                        <span>
                            <span class="block text-sm font-semibold text-[var(--color-heading)]">Schedule next follow-up</span>
                            <span class="block text-xs text-[var(--color-muted)]">This one stays Done; a new Pending follow-up is created.</span>
                        </span>
                    </label>

                    {{-- Next follow-up fields --}}
                    <div x-show="scheduleNext" x-cloak class="space-y-4 rounded-xl border border-dashed border-[var(--color-primary)]/40 bg-[var(--color-primary-soft)]/30 p-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-400">Type</label>
                                <select name="type" x-model="type" class="h-11 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20">
                                    @foreach (\App\Models\LeadFollowUp::TYPES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-400">Priority</label>
                                <select name="priority" x-model="priority" class="h-11 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20">
                                    @foreach (\App\Models\LeadFollowUp::PRIORITIES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-400">When <span class="text-red-500">*</span></label>
                            <div class="mb-2 flex flex-wrap gap-1.5">
                                @foreach (['Tomorrow' => 1, 'In 3 days' => 3, 'Next week' => 7, 'In 2 weeks' => 14] as $label => $days)
                                    <button type="button" @click="setDate({{ $days }})" :class="isDay({{ $days }}) ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white' : 'border-gray-200 bg-white text-[var(--color-muted)] hover:border-[var(--color-primary)]'" class="rounded-lg border px-2.5 py-1 text-xs font-semibold transition">{{ $label }}</button>
                                @endforeach
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="date" x-model="date" :required="scheduleNext" class="h-11 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20">
                                <input type="time" x-model="time" class="h-11 w-full rounded-xl border-gray-200 bg-white text-sm focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20">
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-400">Note</label>
                            <textarea name="note" x-model="fuNote" rows="2" maxlength="2000" placeholder="What is the next follow-up about?" class="w-full rounded-xl border-gray-200 bg-white text-sm focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20"></textarea>
                        </div>
                        <input type="hidden" name="scheduled_at" :value="dt">
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-2">
                    <button type="button" @click="open = false" class="flex-1 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] transition hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="flex-1 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700" x-text="scheduleNext ? 'Save & Schedule Next' : 'Mark Done'"></button>
                </div>
            </form>
        </div>
    </div>
</div>
