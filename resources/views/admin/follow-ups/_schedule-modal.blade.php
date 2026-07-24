{{-- Add Follow-up modal (create). Opened via window event `open-schedule` with { action, leadName }.
     Expects $fuUsers (assignable users) from the including view. --}}
<div x-data="{
        open: false, action: '', leadName: '',
        type: 'call', priority: 'medium', assigned: '', note: '', date: '', time: '10:00',
        _p(n) { return String(n).padStart(2, '0'); },
        _ymd(d) { return `${d.getFullYear()}-${this._p(d.getMonth() + 1)}-${this._p(d.getDate())}`; },
        get dt() { return this.date ? `${this.date}T${this.time || '00:00'}` : ''; },
        offsetYmd(days) { const d = new Date(); d.setDate(d.getDate() + days); return this._ymd(d); },
        setDate(days) { this.date = this.offsetYmd(days); },
        isDay(days) { return this.date === this.offsetYmd(days); },
        get preview() {
            if (!this.date) return '';
            const d = new Date(`${this.date}T${this.time || '00:00'}`);
            if (isNaN(d)) return '';
            const dow = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][d.getDay()];
            const mon = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][d.getMonth()];
            let h = d.getHours(); const m = this._p(d.getMinutes()); const ap = h < 12 ? 'AM' : 'PM'; h = h % 12 || 12;
            return `${dow}, ${d.getDate()} ${mon} ${d.getFullYear()} · ${h}:${m} ${ap}`;
        },
        reset() { this.type = 'call'; this.priority = 'medium'; this.assigned = ''; this.note = ''; this.date = ''; this.time = '10:00'; }
     }"
     @open-schedule.window="open = true; action = $event.detail.action; leadName = $event.detail.leadName || ''; reset()"
     @keydown.escape.window="open = false" x-cloak>

    <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm" @click="open = false"></div>

    <div x-show="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-16" @click.self="open = false">
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5">

            <div class="relative flex items-start gap-3 bg-gradient-to-br from-[var(--color-primary)] to-[var(--color-primary-hover)] px-5 py-4 text-white">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white/20 ring-1 ring-white/30">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-bold">Add Follow-up</h3>
                    <p class="mt-0.5 truncate text-xs text-white/80" x-text="leadName"></p>
                </div>
                <button type="button" @click="open = false" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-white/80 transition hover:bg-white/20 hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <form method="POST" :action="action" data-turbo="false" class="px-5 py-5">
                @csrf
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-400">Type</label>
                            <select name="type" x-model="type" class="h-11 w-full rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-[var(--color-primary)] focus:bg-white focus:ring-2 focus:ring-[var(--color-primary)]/20">
                                @foreach (\App\Models\LeadFollowUp::TYPES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-400">Priority</label>
                            <select name="priority" x-model="priority" class="h-11 w-full rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-[var(--color-primary)] focus:bg-white focus:ring-2 focus:ring-[var(--color-primary)]/20">
                                @foreach (\App\Models\LeadFollowUp::PRIORITIES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-400">Assigned Sales Person</label>
                        <select name="user_id" x-model="assigned" class="h-11 w-full rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-[var(--color-primary)] focus:bg-white focus:ring-2 focus:ring-[var(--color-primary)]/20">
                            <option value="">Lead's owner (default)</option>
                            @foreach (($fuUsers ?? collect()) as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-400">When <span class="text-red-500">*</span></label>
                        <div class="mb-2 flex flex-wrap gap-1.5">
                            @foreach (['Today' => 0, 'Tomorrow' => 1, 'In 3 days' => 3, 'Next week' => 7] as $label => $days)
                                <button type="button" @click="setDate({{ $days }})" :class="isDay({{ $days }}) ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white' : 'border-gray-200 bg-white text-[var(--color-muted)] hover:border-[var(--color-primary)]'" class="rounded-lg border px-2.5 py-1 text-xs font-semibold transition">{{ $label }}</button>
                            @endforeach
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" x-model="date" required class="h-11 w-full rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-[var(--color-primary)] focus:bg-white focus:ring-2 focus:ring-[var(--color-primary)]/20">
                            <input type="time" x-model="time" class="h-11 w-full rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-[var(--color-primary)] focus:bg-white focus:ring-2 focus:ring-[var(--color-primary)]/20">
                        </div>
                        <div x-show="preview" x-cloak class="mt-3 flex items-center gap-2 rounded-xl bg-[var(--color-primary-soft)] px-3 py-2.5 text-sm font-semibold text-[var(--color-primary)]">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                            <span x-text="preview"></span>
                        </div>
                        <input type="hidden" name="scheduled_at" :value="dt">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-400">Note</label>
                        <textarea name="note" x-model="note" rows="2" maxlength="2000" placeholder="What is this follow-up about?" class="w-full rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-[var(--color-primary)] focus:bg-white focus:ring-2 focus:ring-[var(--color-primary)]/20"></textarea>
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-2">
                    <button type="button" @click="open = false" class="flex-1 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] transition hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="flex-1 rounded-xl bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[var(--color-primary-hover)]">Save Follow-up</button>
                </div>
            </form>
        </div>
    </div>
</div>
