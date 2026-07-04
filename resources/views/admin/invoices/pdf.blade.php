<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
  * { font-family: DejaVu Sans, sans-serif; }
  body { color: #1f2937; font-size: 12px; margin: 0; }
  .wrap { padding: 32px 36px; }
  .brand { font-size: 22px; font-weight: bold; color: #4f46e5; }
  .muted { color: #6b7280; }
  .right { text-align: right; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 18px; }
  table.items th { text-align: left; background: #0f172a; color: #fff; padding: 8px 10px; font-size: 11px; }
  table.items td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
  .totals { width: 260px; margin-left: auto; margin-top: 14px; }
  .totals td { padding: 4px 8px; }
  .due { background: #eef2ff; color: #4f46e5; font-weight: bold; font-size: 13px; }
  .badge { padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; }
  .b-paid { background: #ecfdf5; color: #047857; }
  .b-part { background: #fffbeb; color: #b45309; }
  .b-due { background: #fef2f2; color: #b91c1c; }
</style>
</head>
@php
    // ASCII-safe symbols — DejaVu Sans (the PDF font) can't render ৳ ₹ ﷼ etc.
    $cur = ['USD' => '$', 'BDT' => 'Tk', 'EUR' => '€', 'GBP' => '£', 'INR' => 'Rs', 'AUD' => 'A$', 'CAD' => 'C$', 'AED' => 'AED ', 'SGD' => 'S$', 'MYR' => 'RM', 'SAR' => 'SAR ', 'JPY' => '¥'][$invoice->currency] ?? ($invoice->currency.' ');
    $due = $invoice->amountDue();
    $badge = $due <= 0 ? 'b-paid' : ($invoice->amount_paid > 0 ? 'b-part' : 'b-due');
    $badgeText = $due <= 0 ? 'PAID' : ($invoice->amount_paid > 0 ? 'PARTIALLY PAID' : 'UNPAID');
@endphp
<body>
<div class="wrap">
  <table style="width:100%"><tr>
    <td style="border:none">
      @php
          $logoPath = public_path('images/razinsoft-logo-print.png');
          $logoSrc = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
      @endphp
      @if ($logoSrc)
        <img src="{{ $logoSrc }}" alt="RazinSoft" style="height:34px;width:auto">
      @else
        <div class="brand">RazinSoft</div>
      @endif
      <div class="muted" style="margin-top:6px">RazinSoft Ltd.<br>support@razinsoft.com &middot; razinsoft.com</div>
    </td>
    <td style="border:none" class="right">
      <div style="font-size:18px;font-weight:bold">INVOICE</div>
      <div style="font-size:14px;font-weight:bold">{{ $invoice->invoice_number }}</div>
      <div class="muted">Issue Date: {{ $invoice->invoice_date->format('d M Y') }}</div>
      @if ($invoice->due_date)<div class="muted">Due Date: {{ $invoice->due_date->format('d M Y') }}</div>@endif
      <div style="margin-top:6px"><span class="badge {{ $badge }}">{{ $badgeText }}</span></div>
    </td>
  </tr></table>

  <div style="margin-top:20px">
    <div class="muted" style="font-size:11px;font-weight:bold">BILL TO</div>
    <div style="font-weight:bold">{{ $invoice->bill_to_name ?: '—' }}</div>
    @if ($invoice->bill_to_company)<div class="muted">{{ $invoice->bill_to_company }}</div>@endif
    @if ($invoice->bill_to_address)<div class="muted">{{ $invoice->bill_to_address }}</div>@endif
    @if ($invoice->bill_to_email)<div class="muted">{{ $invoice->bill_to_email }}</div>@endif
  </div>

  <table class="items">
    <thead><tr>
      <th>Item</th><th class="right">Qty</th><th class="right">Rate</th><th class="right">Tax</th><th class="right">Amount</th>
    </tr></thead>
    <tbody>
      @foreach ($invoice->items as $item)
        <tr>
          <td><strong>{{ $item->description }}</strong>@if ($item->sub_description)<br><span class="muted" style="font-size:11px">{{ $item->sub_description }}</span>@endif</td>
          <td class="right">{{ rtrim(rtrim(number_format($item->qty, 2), '0'), '.') }}</td>
          <td class="right">{{ $cur }}{{ number_format($item->unit_price, 2) }}</td>
          <td class="right">{{ rtrim(rtrim(number_format($item->tax_percent, 2), '0'), '.') }}%</td>
          <td class="right">{{ $cur }}{{ number_format($item->amount, 2) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <table class="totals">
    <tr><td class="muted">Subtotal</td><td class="right">{{ $cur }}{{ number_format($invoice->subtotal, 2) }}</td></tr>
    <tr><td class="muted">Discount</td><td class="right">-{{ $cur }}{{ number_format($invoice->discount_total, 2) }}</td></tr>
    <tr><td class="muted">Tax</td><td class="right">{{ $cur }}{{ number_format($invoice->tax_total, 2) }}</td></tr>
    <tr><td style="border-top:1px solid #0f172a"><strong>Total</strong></td><td class="right" style="border-top:1px solid #0f172a"><strong>{{ $cur }}{{ number_format($invoice->total, 2) }}</strong></td></tr>
    @if ($invoice->amount_paid > 0)<tr><td class="muted">Paid</td><td class="right">-{{ $cur }}{{ number_format($invoice->amount_paid, 2) }}</td></tr>@endif
    <tr class="due"><td>Amount Due</td><td class="right">{{ $cur }}{{ number_format($due, 2) }} {{ $invoice->currency }}</td></tr>
  </table>

  @if ($invoice->payments->count())
    <div style="margin-top:22px">
      <div class="muted" style="font-size:11px;font-weight:bold">PAYMENT HISTORY</div>
      <table class="items" style="margin-top:6px">
        <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="right">Amount</th></tr></thead>
        <tbody>
          @foreach ($invoice->payments as $p)
            <tr>
              <td>{{ $p->paid_at->format('d M Y') }}</td>
              <td>{{ $p->method ?? '—' }}</td>
              <td>{{ $p->reference ?? '—' }}</td>
              <td class="right">{{ $cur }}{{ number_format($p->amount, 2) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @if ($invoice->notes || $invoice->terms)
  <table style="width:100%;margin-top:22px">
    <tr>
      <td style="border:none;width:50%;vertical-align:top;padding-right:12px">
        <div class="muted" style="font-size:10px;font-weight:bold;text-transform:uppercase;letter-spacing:.5px">Notes</div>
        <div style="margin-top:3px">{{ $invoice->notes ?: '—' }}</div>
      </td>
      <td style="border:none;width:50%;vertical-align:top;padding-left:12px">
        <div class="muted" style="font-size:10px;font-weight:bold;text-transform:uppercase;letter-spacing:.5px">Terms</div>
        <div style="margin-top:3px">{{ $invoice->terms ?: '—' }}</div>
      </td>
    </tr>
  </table>
  @endif
</div>
</body>
</html>
