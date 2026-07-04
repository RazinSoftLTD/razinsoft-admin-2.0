@php $cur = ['USD' => '$', 'BDT' => 'Tk', 'EUR' => '€', 'GBP' => '£'][$invoice->currency] ?? ''; @endphp
<div style="font-family: Arial, sans-serif; color:#1f2937; max-width:560px; margin:0 auto;">
    <h2 style="color:#4f46e5; margin-bottom:4px;">RazinSoft</h2>
    <p>Hi {{ $invoice->bill_to_name ?: 'there' }},</p>
    <p>Please find attached invoice <strong>{{ $invoice->invoice_number }}</strong>.</p>
    <table style="width:100%; border-collapse:collapse; margin:16px 0;">
        <tr><td style="padding:4px 0; color:#6b7280;">Total</td><td style="padding:4px 0; text-align:right;">{{ $cur }}{{ number_format($invoice->total, 2) }}</td></tr>
        <tr><td style="padding:4px 0; color:#6b7280;">Paid</td><td style="padding:4px 0; text-align:right;">{{ $cur }}{{ number_format($invoice->amount_paid, 2) }}</td></tr>
        <tr><td style="padding:8px 0; font-weight:bold; border-top:1px solid #e5e7eb;">Amount Due</td><td style="padding:8px 0; text-align:right; font-weight:bold; border-top:1px solid #e5e7eb;">{{ $cur }}{{ number_format($invoice->amountDue(), 2) }}</td></tr>
    </table>
    <p style="text-align:center; margin:24px 0;">
        <a href="{{ $payUrl }}" style="background:#4f46e5; color:#fff; padding:12px 28px; border-radius:8px; text-decoration:none; font-weight:bold;">Pay Invoice Online</a>
    </p>
    <p style="color:#6b7280; font-size:13px;">Thank you for your business.<br>RazinSoft · support@razinsoft.com</p>
</div>
