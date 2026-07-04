@php $cur = ['USD' => '$', 'BDT' => '৳', 'EUR' => '€', 'GBP' => '£'][$invoice->currency] ?? ''; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pay Invoice {{ $invoice->invoice_number }} — RazinSoft</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 py-10">
    <div class="mx-auto max-w-lg px-4">
        <div class="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xl font-extrabold text-indigo-600">RazinSoft</span>
                <span class="rounded bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-600">INVOICE</span>
            </div>

            @if (session('status'))<div class="mt-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('status') }}</div>@endif
            @error('pay')<div class="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{{ $message }}</div>@enderror

            <div class="mt-6">
                <p class="text-2xl font-bold text-gray-900">{{ $invoice->invoice_number }}</p>
                <p class="text-sm text-gray-500">Billed to {{ $invoice->bill_to_name }}@if ($invoice->bill_to_company) · {{ $invoice->bill_to_company }}@endif</p>
            </div>

            <div class="mt-6 space-y-2 border-t border-gray-100 pt-4 text-sm">
                @foreach ($invoice->items as $item)
                    <div class="flex justify-between"><span class="text-gray-600">{{ $item->description }}</span><span class="text-gray-900">{{ $cur }}{{ number_format($item->amount, 2) }}</span></div>
                @endforeach
            </div>

            <div class="mt-4 space-y-1 border-t border-gray-100 pt-4 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">Total</span><span>{{ $cur }}{{ number_format($invoice->total, 2) }}</span></div>
                @if ($invoice->amount_paid > 0)<div class="flex justify-between text-emerald-600"><span>Paid</span><span>-{{ $cur }}{{ number_format($invoice->amount_paid, 2) }}</span></div>@endif
                <div class="flex justify-between text-lg font-bold text-gray-900"><span>Amount Due</span><span>{{ $cur }}{{ number_format($invoice->amountDue(), 2) }}</span></div>
            </div>

            @if ($invoice->amountDue() <= 0)
                <div class="mt-6 rounded-xl bg-emerald-50 py-4 text-center font-semibold text-emerald-700">✓ This invoice is fully paid. Thank you!</div>
            @else
                @if (! is_null($invoice->requested_amount))
                    <div class="mt-6 rounded-xl bg-indigo-50 p-4 text-center">
                        <p class="text-sm text-indigo-700">Amount due now</p>
                        <p class="text-2xl font-extrabold text-indigo-700">{{ $cur }}{{ number_format($invoice->payableAmount(), 2) }}</p>
                    </div>
                @endif
                <form method="POST" action="{{ route('pay.invoice.checkout', $invoice->public_token) }}" class="mt-6">
                    @csrf
                    <button class="w-full rounded-xl bg-indigo-600 py-3.5 text-center font-bold text-white hover:bg-indigo-700">
                        Pay {{ $cur }}{{ number_format($invoice->payableAmount(), 2) }} {{ $invoice->currency }}
                    </button>
                </form>
                <p class="mt-3 text-center text-xs text-gray-400">Secured by Stripe</p>
            @endif
        </div>
    </div>
</body>
</html>
