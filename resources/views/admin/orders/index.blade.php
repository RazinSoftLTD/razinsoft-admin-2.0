@extends('admin.layouts.app')
@section('title', 'Orders')

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        {{-- Unpaid = checkout started but the payment never completed (failed, cancelled or walked away). --}}
        <div class="flex flex-wrap gap-1">
            @foreach ([
                '' => ['All orders', $counts['all']],
                'unpaid' => ['Payment not completed', $counts['unpaid']],
                'paid' => ['Paid', $counts['paid']],
            ] as $key => [$label, $count])
                @php $on = (string) $filter === (string) $key; @endphp
                <a href="{{ route('admin.orders.index', $key ? ['filter' => $key] : []) }}"
                   class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold transition {{ $on ? 'bg-[var(--color-primary-soft)] text-[var(--color-primary)]' : 'text-[var(--color-muted)] hover:bg-gray-50' }}">
                    {{ $label }}
                    <span class="rounded-full {{ $key === 'unpaid' && $count > 0 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500' }} px-2 py-0.5 text-xs font-bold">{{ number_format($count) }}</span>
                </a>
            @endforeach
        </div>
        <a href="{{ route('admin.orders.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Manual Order
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Order</th>
                        <th class="px-5 py-3 font-semibold">Customer</th>
                        <th class="px-5 py-3 font-semibold">Gateway</th>
                        <th class="px-5 py-3 font-semibold">Total</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Date</th>
                        <th class="px-5 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($orders as $o)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-mono font-semibold text-[var(--color-heading)]">{{ $o->order_number }}</td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">
                                {{ $o->user?->name ?? '—' }}
                                {{-- Chasing an unpaid order starts with the email, so show it here. --}}
                                @if ($filter === 'unpaid' && $o->user?->email)
                                    <a href="mailto:{{ $o->user->email }}" class="block text-xs text-[var(--color-primary)] hover:underline">{{ $o->user->email }}</a>
                                @endif
                            </td>
                            <td class="px-5 py-3 capitalize text-[var(--color-muted)]">{{ $o->payment_gateway ?? '—' }}</td>
                            <td class="px-5 py-3 font-semibold">${{ number_format($o->total, 2) }}</td>
                            <td class="px-5 py-3"><x-admin.status :status="$o->status" /></td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">{{ $o->created_at->format('M d, Y') }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('admin.orders.show', $o) }}" class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:bg-[var(--color-primary-soft)]">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">No orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
@endsection
