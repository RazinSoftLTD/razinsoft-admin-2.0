<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-xl border border-gray-100 bg-white shadow-sm">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3.5">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Payslips</h2>
            <span class="text-xs text-gray-400">{{ $payrolls->count() }} month(s)</span>
        </header>
        <div class="overflow-x-auto">
        {{-- The four amounts sit in one Breakdown cell: eight columns did not fit beside the form. --}}
        <table class="w-full text-left text-sm" style="min-width:520px">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-5 py-3 font-semibold">Month</th>
                    <th class="px-5 py-3 font-semibold">Breakdown</th>
                    <th class="px-5 py-3 text-right font-semibold">Net Pay</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($payrolls as $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-semibold text-[var(--color-heading)]">{{ $p->period?->format('M Y') }}</td>
                        <td class="px-5 py-3">
                            <span class="flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                <span class="text-[var(--color-muted)]">Basic <span class="font-semibold text-[var(--color-heading)]">{{ number_format((float) $p->basic, 0) }}</span></span>
                                <span class="text-[var(--color-muted)]">Allow. <span class="font-semibold text-[var(--color-heading)]">{{ number_format((float) $p->allowance, 0) }}</span></span>
                                @if ((float) $p->bonus)<span class="font-semibold text-emerald-600">+{{ number_format((float) $p->bonus, 0) }} bonus</span>@endif
                                @if ((float) $p->deduction)<span class="font-semibold text-red-600">−{{ number_format((float) $p->deduction, 0) }} deduct.</span>@endif
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right font-bold text-[var(--color-heading)]">{{ $p->symbol() }}{{ number_format((float) $p->net_pay, 2) }}</td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $p->status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ \App\Models\EmployeePayroll::STATUSES[$p->status] ?? $p->status }}</span>
                            @if ($p->paid_on)<span class="ml-1 text-[10px] text-gray-400">{{ $p->paid_on->format('d M') }}</span>@endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if ($canEdit)
                                <form method="POST" action="{{ route('admin.staff.payroll.destroy', [$staff, $p]) }}" onsubmit="return confirm('Remove this payslip?')">
                                    @csrf @method('DELETE')
                                    <button class="rounded p-1.5 text-red-400 hover:bg-red-50 hover:text-red-600" title="Remove">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    @include('admin.staff.tabs._empty', ['cols' => 5, 'icon' => 'M12 3v18M7 7h7a3 3 0 0 1 0 6H7h8', 'title' => 'No payroll records', 'hint' => 'Add a payslip on the right to start the history.'])
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    @if ($canEdit)
        <form method="POST" action="{{ route('admin.staff.payroll.store', $staff) }}" class="space-y-3 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            @csrf
            <div class="flex items-center gap-2.5 border-b border-gray-100 pb-3">
                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                </span>
                <h3 class="text-sm font-bold text-[var(--color-heading)]">Add / update payslip</h3>
            </div>
            <p class="text-xs text-[var(--color-muted)]">Saving the same month again updates that payslip.</p>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Month <span class="text-red-500">*</span></label>
                <input type="date" name="period" required value="{{ today()->startOfMonth()->format('Y-m-d') }}" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Basic <span class="text-red-500">*</span></label>
                    <input name="basic" type="number" step="0.01" min="0" required class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Allowance</label>
                    <input name="allowance" type="number" step="0.01" min="0" value="0" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Bonus</label>
                    <input name="bonus" type="number" step="0.01" min="0" value="0" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Deduction</label>
                    <input name="deduction" type="number" step="0.01" min="0" value="0" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Currency</label>
                    <select name="currency" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        @foreach ($currencies as $c)<option value="{{ $c }}" @selected($c === 'BDT')>{{ $c }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Status</label>
                    <select name="status" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        @foreach (\App\Models\EmployeePayroll::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Paid on</label>
                <input type="date" name="paid_on" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
            </div>
            <button class="w-full rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save payslip</button>
        </form>
    @endif
</div>
