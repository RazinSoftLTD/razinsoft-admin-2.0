@php $cur = ['USD' => '$', 'BDT' => '৳', 'EUR' => '€', 'GBP' => '£'][$invoice->currency] ?? ''; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment received — {{ $invoice->invoice_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 py-10">
    <div class="mx-auto max-w-lg px-4">
        <div class="rounded-2xl border border-gray-100 bg-white p-8 text-center shadow-sm">
            <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 13 4 4L19 7"/></svg>
            </span>
            <h1 class="mt-5 text-xl font-bold text-gray-900">Payment received</h1>
            <p class="mt-2 text-sm text-gray-500">Thank you! Your payment for invoice <strong>{{ $invoice->invoice_number }}</strong> has been recorded.</p>
            <div class="mt-6 space-y-1 rounded-xl bg-gray-50 p-4 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">Total paid</span><span class="font-semibold text-emerald-600">{{ $cur }}{{ number_format($invoice->amount_paid, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Remaining due</span><span class="font-semibold text-gray-900">{{ $cur }}{{ number_format($invoice->amountDue(), 2) }}</span></div>
            </div>
            <a href="{{ route('pay.invoice.show', $invoice->public_token) }}" class="mt-6 inline-block text-sm font-semibold text-indigo-600 hover:underline">Back to invoice</a>
        </div>
    </div>
</body>
</html>
