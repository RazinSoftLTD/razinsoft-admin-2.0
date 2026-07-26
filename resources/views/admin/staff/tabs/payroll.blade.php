<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
        <table class="w-full text-left text-sm" style="min-width:720px">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-5 py-3 font-semibold">Month</th>
                    <th class="px-5 py-3 text-right font-semibold">Basic</th>
                    <th class="px-5 py-3 text-right font-semibold">Allow.</th>
                    <th class="px-5 py-3 text-right font-semibold">Bonus</th>
                    <th class="px-5 py-3 text-right font-semibold">Deduct.</th>
                    <th class="px-5 py-3 text-right font-semibold">Net Pay</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($payrolls as $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-semibold text-[var(--color-heading)]">{{ $p->period?->format('M Y') }}</td>
                        <td class="px-5 py-3 text-right text-[var(--color-muted)]">{{ number_format((float) $p->basic, 0) }}</td>
                        <td class="px-5 py-3 text-right text-[var(--color-muted)]">{{ number_format((float) $p->allowance, 0) }}</td>
                        <td class="px-5 py-3 text-right text-emerald-600">{{ number_format((float) $p->bonus, 0) }}</td>
                        <td class="px-5 py-3 text-right text-red-600">{{ number_format((float) $p->deduction, 0) }}</td>
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
                    <tr><td colspan="8" class="px-5 py-12 text-center text-gray-300">No payroll records.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($canEdit)
        <form method="POST" action="{{ route('admin.staff.payroll.store', $staff) }}" class="space-y-3 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            @csrf
            <h3 class="text-sm font-bold text-[var(--color-heading)]">Add / update payslip</h3>
            <p class="text-xs text-[var(--color-muted)]">Saving the same month again updates that payslip.</p>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Month <span class="text-red-500">*</span></label>
                <input type="date" name="period" required value="{{ today()->startOfMonth()->format('Y-m-d') }}" class="h-10 w-full rounded-lg border-gray-200 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Basic <span class="text-red-500">*</span></label>
                    <input name="basic" type="number" step="0.01" min="0" required class="h-10 w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Allowance</label>
                    <input name="allowance" type="number" step="0.01" min="0" value="0" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Bonus</label>
                    <input name="bonus" type="number" step="0.01" min="0" value="0" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Deduction</label>
                    <input name="deduction" type="number" step="0.01" min="0" value="0" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Currency</label>
                    <select name="currency" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                        @foreach ($currencies as $c)<option value="{{ $c }}" @selected($c === 'BDT')>{{ $c }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Status</label>
                    <select name="status" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                        @foreach (\App\Models\EmployeePayroll::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Paid on</label>
                <input type="date" name="paid_on" class="h-10 w-full rounded-lg border-gray-200 text-sm">
            </div>
            <button class="w-full rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save payslip</button>
        </form>
    @endif
</div>
