{{--
  Shared by Transfers and Currency Conversion — the same paired out/in movement. When the two
  accounts hold different currencies the controller files it as a conversion automatically.
  Props: $accounts, $event (window event that opens it), $title, $conversion (bool)
--}}
<div x-data="{
        open: false,
        from: '',
        to: '',
        amount: '',
        rate: '',
        converted: '',
        accounts: @js($accounts->map(fn ($a) => ['id' => (string) $a->id, 'currency' => $a->currency, 'name' => $a->name])->values()),
        cur(id) { return this.accounts.find(a => a.id === String(id))?.currency ?? ''; },
        get crossCurrency() { return this.from && this.to && this.cur(this.from) !== this.cur(this.to); },
        // Keep rate and converted amount consistent, whichever the user types.
        syncFromRate() { if (this.amount && this.rate) this.converted = (parseFloat(this.amount) * parseFloat(this.rate)).toFixed(2); },
        syncFromConverted() { if (this.amount > 0 && this.converted) this.rate = (parseFloat(this.converted) / parseFloat(this.amount)).toFixed(6); },
     }"
     @{{ $event }}.window="open = true" @keydown.escape.window="open = false">
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="open = false"></div>
    <div x-show="open" x-cloak x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-20" @click.self="open = false">
        <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <h3 class="text-base font-bold text-[var(--color-heading)]">{{ $title }}</h3>
                <button type="button" @click="open = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('admin.finance.transfers.store') }}" class="space-y-4 p-5">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">From <span class="text-red-500">*</span></label>
                        <select name="account_id" x-model="from" required class="h-11 w-full rounded-lg border-gray-200 text-sm">
                            <option value="">Select account</option>
                            @foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->currency }})</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">To <span class="text-red-500">*</span></label>
                        <select name="counter_account_id" x-model="to" required class="h-11 w-full rounded-lg border-gray-200 text-sm">
                            <option value="">Select account</option>
                            @foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->currency }})</option>@endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Amount sent <span class="text-red-500">*</span></label>
                        <input name="amount" x-model="amount" @input="syncFromRate()" type="number" step="0.01" min="0.01" required class="h-11 w-full rounded-lg border-gray-200 text-sm">
                        <p class="mt-1 text-xs text-[var(--color-muted)]" x-show="from" x-cloak><span x-text="cur(from)"></span> leaves this account</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Date <span class="text-red-500">*</span></label>
                        <input name="occurred_on" type="date" required value="{{ today()->format('Y-m-d') }}" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                    </div>
                </div>

                {{-- Only meaningful when the currencies differ. --}}
                <div x-show="crossCurrency" x-cloak class="grid grid-cols-2 gap-3 rounded-lg bg-gray-50 p-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Exchange rate</label>
                        <input name="exchange_rate" x-model="rate" @input="syncFromRate()" type="number" step="0.000001" min="0" class="h-11 w-full rounded-lg border-gray-200 text-sm" placeholder="e.g. 121.50">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Amount received</label>
                        <input name="converted_amount" x-model="converted" @input="syncFromConverted()" type="number" step="0.01" min="0" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                        <p class="mt-1 text-xs text-[var(--color-muted)]"><span x-text="cur(to)"></span> lands in the destination</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Transfer fee</label>
                        <input name="fee" type="number" step="0.01" min="0" value="0" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Bank charge</label>
                        <input name="bank_charge" type="number" step="0.01" min="0" value="0" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                    </div>
                </div>
                <p class="text-xs text-[var(--color-muted)]">Fees are taken out of the sending account on top of the amount sent.</p>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Reference</label>
                    <input name="reference" maxlength="120" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Notes</label>
                    <textarea name="notes" rows="2" maxlength="1000" class="w-full rounded-lg border-gray-200 text-sm"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                    <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Record</button>
                </div>
            </form>
        </div>
    </div>
</div>
