@extends('admin.layouts.app')
@section('title', 'Order ' . $order->order_number)

@section('content')
    <a href="{{ route('admin.orders.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to orders
    </a>

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="space-y-6">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-bold text-[var(--color-heading)]">Items</h2>
                    <x-admin.status :status="$order->status" />
                </div>
                <table class="mt-4 w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-gray-400">
                        <tr><th class="py-2 font-semibold">Product</th><th class="py-2 font-semibold">Plan</th><th class="py-2 text-right font-semibold">Qty</th><th class="py-2 text-right font-semibold">Total</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($order->items as $it)
                            <tr>
                                <td class="py-3 font-semibold text-[var(--color-heading)]">{{ $it->product_name }}</td>
                                <td class="py-3 text-[var(--color-muted)]">
                                    {{ $it->plan_name ?? 'License' }}
                                    @if ($it->license)
                                        <span class="ml-1 font-mono text-xs text-gray-400">{{ $it->license->license_key }}</span>
                                        @if ($it->license->file_path)
                                            <a href="{{ route('admin.orders.license.download', [$order, $it->license]) }}" class="ml-1 inline-flex items-center gap-1 align-middle text-xs font-semibold text-[var(--color-primary)] hover:underline" title="Download license certificate">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg>License
                                            </a>
                                        @endif
                                    @endif
                                </td>
                                <td class="py-3 text-right">{{ $it->quantity }}</td>
                                <td class="py-3 text-right font-semibold">${{ number_format($it->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="space-y-6">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="font-bold text-[var(--color-heading)]">Summary</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-[var(--color-muted)]">Subtotal</dt><dd>${{ number_format($order->subtotal, 2) }}</dd></div>
                    @if ($order->discount > 0)<div class="flex justify-between text-emerald-600"><dt>Discount {{ $order->coupon_code ? "($order->coupon_code)" : '' }}</dt><dd>−${{ number_format($order->discount, 2) }}</dd></div>@endif
                    <div class="flex justify-between border-t border-gray-100 pt-2 font-bold"><dt>Total</dt><dd>${{ number_format($order->total, 2) }} {{ $order->currency }}</dd></div>
                </dl>
                <div class="mt-4 space-y-1 border-t border-gray-100 pt-4 text-sm">
                    <p class="text-[var(--color-muted)]">Customer: <span class="font-semibold text-[var(--color-heading)]">{{ $order->user?->name }}</span></p>
                    <p class="text-[var(--color-muted)]">Gateway: <span class="capitalize">{{ $order->payment_gateway ?? '—' }}</span></p>
                    <p class="text-[var(--color-muted)]">Placed: {{ $order->created_at->format('M d, Y H:i') }}</p>
                    @if ($order->invoice)<p class="text-[var(--color-muted)]">Invoice: <span class="font-mono text-xs">{{ $order->invoice->invoice_number }}</span></p>@endif
                </div>

                {{-- Downloads: invoice PDF + license certificates --}}
                <div class="mt-4 space-y-2 border-t border-gray-100 pt-4">
                    <a href="{{ route('admin.orders.invoice.download', $order) }}" class="flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg>
                        Download Invoice PDF
                    </a>
                    @foreach ($order->items as $it)
                        @if ($it->license && $it->license->file_path)
                            <a href="{{ route('admin.orders.license.download', [$order, $it->license]) }}" class="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg>
                                License — {{ \Illuminate\Support\Str::limit($it->product_name, 20) }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </aside>
    </div>
@endsection
