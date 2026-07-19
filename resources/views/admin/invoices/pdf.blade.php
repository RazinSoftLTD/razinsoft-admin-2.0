<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 28px 42px; }
  * { font-family: DejaVu Sans, sans-serif; }
  body { color: #1f2937; font-size: 12px; margin: 0; }
  .wrap { padding: 0; }
  .brand { font-size: 22px; font-weight: bold; color: #4f46e5; }
  .muted { color: #6b7280; }
  .right { text-align: right; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 18px; }
  /* Light-ash header + shaded amount column: crisp on screen, cheap and clean to print. */
  table.items th { text-align: left; background: #f3f4f6; color: #374151; padding: 8px 10px; font-size: 11px; border: 1px solid #d1d5db; }
  table.items td { padding: 8px 10px; border: 1px solid #e5e7eb; }
  table.items td.amount, table.items th.amount { background: #f9fafb; }
  .totals { width: 280px; margin-left: auto; margin-top: 14px; }
  .totals td { padding: 5px 8px; }
  .due { background: #f3f4f6; color: #111827; font-weight: bold; font-size: 13px; }
  .badge { padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; }
  .b-paid { background: #ecfdf5; color: #047857; }
  .b-part { background: #fffbeb; color: #b45309; }
  .b-due { background: #fef2f2; color: #b91c1c; }
</style>
</head>
@php
    // DejaVu Sans (the PDF font) can't render ৳ ₹ ﷼ etc., so map known codes to safe symbols.
    $pdfSafe = ['USD' => '$', 'BDT' => 'Tk', 'EUR' => '€', 'GBP' => '£', 'INR' => 'Rs', 'AUD' => 'A$', 'CAD' => 'C$', 'AED' => 'AED ', 'SGD' => 'S$', 'MYR' => 'RM', 'SAR' => 'SAR ', 'JPY' => '¥'];
    $cur = $pdfSafe[$invoice->currency] ?? null;
    if ($cur === null) {
        // Custom currency: use its symbol if it's ASCII-printable, otherwise the code.
        $sym = \App\Models\Currency::symbolMap()[$invoice->currency] ?? $invoice->currency;
        $cur = preg_match('/^[\x20-\x7E]+$/', $sym) ? $sym : $invoice->currency.' ';
    }
    $due = $invoice->amountDue();
    $badge = $due <= 0 ? 'b-paid' : ($invoice->amount_paid > 0 ? 'b-part' : 'b-due');
    $badgeText = $due <= 0 ? 'PAID' : ($invoice->amount_paid > 0 ? 'PARTIALLY PAID' : 'UNPAID');
@endphp
<body>
<div class="wrap">
  {{-- Big right-aligned title --}}
  <div class="right" style="font-size:26px;font-weight:bold;color:#374151">Invoice</div>

  {{-- Logo (left) + invoice meta table (right) --}}
  <table style="width:100%;margin-top:8px"><tr>
    <td style="border:none;vertical-align:top;width:50%">
      @php
          $iconPath = public_path('images/razinsoft-icon-print.png');
          $logoPath = public_path('images/razinsoft-logo-print.png');
          $logoSrc = is_file($iconPath)
              ? 'data:image/png;base64,'.base64_encode(file_get_contents($iconPath))
              : (is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null);
      @endphp
      @if ($logoSrc)
        <img src="{{ $logoSrc }}" alt="RazinSoft" style="height:64px;width:auto">
      @else
        <div class="brand">RazinSoft</div>
      @endif
    </td>
    <td style="border:none;vertical-align:top" class="right">
      <table style="border-collapse:collapse;margin-left:auto">
        <tr>
          <td style="border:1px solid #d1d5db;background:#f3f4f6;padding:7px 12px;font-weight:bold;color:#374151">Invoice Number</td>
          <td style="border:1px solid #d1d5db;padding:7px 12px;font-weight:bold">{{ $invoice->invoice_number }}</td>
        </tr>
        <tr>
          <td style="border:1px solid #d1d5db;background:#f3f4f6;padding:7px 12px;font-weight:bold;color:#374151">Invoice Date</td>
          <td style="border:1px solid #d1d5db;padding:7px 12px;font-weight:bold">{{ $invoice->invoice_date->format('d F, Y') }}</td>
        </tr>
        @if ($invoice->due_date)
        <tr>
          <td style="border:1px solid #d1d5db;background:#f3f4f6;padding:7px 12px;font-weight:bold;color:#374151">Due Date</td>
          <td style="border:1px solid #d1d5db;padding:7px 12px;font-weight:bold">{{ $invoice->due_date->format('d F, Y') }}</td>
        </tr>
        @endif
      </table>
    </td>
  </tr></table>

  {{-- Billed From (left) / Billed To (right) + status --}}
  <table style="width:100%;margin-top:22px"><tr>
    <td style="border:none;vertical-align:top;width:50%">
      <div class="muted" style="font-size:12px">Billed From</div>
      <div style="font-weight:bold;margin-top:2px">RazinSoft</div>
      <div>info@razinsoft.com</div>
      <div>+8801711257498</div>
      <div>RMR Center 1/1 (A&amp;B) Shyamoli Ring Road, Dhaka - 1207.</div>
      <div>Bangladesh</div>
    </td>
    <td style="border:none;vertical-align:top" class="right">
      <div class="muted" style="font-size:12px">Billed To</div>
      <div style="font-weight:bold;margin-top:2px">{{ $invoice->bill_to_name ?: '—' }}</div>
      @if ($invoice->bill_to_company)<div>{{ $invoice->bill_to_company }}</div>@endif
      @if ($invoice->bill_to_email)<div>{{ $invoice->bill_to_email }}</div>@endif
      @if ($invoice->bill_to_phone)<div>{{ $invoice->bill_to_phone }}</div>@endif
      @if ($invoice->bill_to_address)<div>{{ $invoice->bill_to_address }}</div>@endif
      <div style="margin-top:14px">
        {{-- Paid & Partially Paid → green; only Unpaid stays red --}}
        <span style="display:inline-block;border:1px solid {{ $invoice->amount_paid > 0 || $due <= 0 ? '#059669' : '#b91c1c' }};color:{{ $invoice->amount_paid > 0 || $due <= 0 ? '#059669' : '#b91c1c' }};border-radius:6px;padding:8px 22px;font-size:15px;font-weight:bold">{{ $due <= 0 ? 'Paid' : ($invoice->amount_paid > 0 ? 'Partially Paid' : 'Unpaid') }}</span>
      </div>
    </td>
  </tr></table>

  <table class="items">
    <thead><tr>
      <th>Description</th><th style="text-align:center">Quantity</th><th class="right">Unit Price</th><th class="right">Tax</th><th class="right amount">Amount ({{ $invoice->currency }})</th>
    </tr></thead>
    <tbody>
      @foreach ($invoice->items as $item)
        <tr>
          <td><strong>{{ $item->description }}</strong></td>
          <td style="text-align:center">{{ rtrim(rtrim(number_format($item->qty, 2), '0'), '.') }}<br><span class="muted" style="font-size:10px">{{ $item->unit ?: 'Items' }}</span></td>
          <td class="right">{{ number_format($item->unit_price, 2) }}</td>
          <td class="right">@if ($item->tax_percent > 0){{ rtrim(rtrim(number_format($item->tax_percent, 2), '0'), '.') }}%@endif</td>
          <td class="right amount"><strong>{{ number_format($item->amount, 2) }}</strong></td>
        </tr>
      @endforeach
      {{-- Sub-descriptions: full-width detail rows under the items, like the sample layout --}}
      @foreach ($invoice->items as $item)
        @if ($item->sub_description)
          <tr><td colspan="5" style="font-size:11px;line-height:1.7">{!! $item->formattedSubDescription() !!}</td></tr>
        @endif
      @endforeach
    </tbody>
  </table>

  <table class="totals">
    <tr><td class="muted">Sub Total</td><td class="right">{{ $cur }}{{ number_format($invoice->subtotal, 2) }}</td></tr>
    @if ($invoice->discount_total > 0)
      <tr><td class="muted">Discount{{ $invoice->discount_type === 'percent' && $invoice->discount_value > 0 ? ': '.rtrim(rtrim(number_format($invoice->discount_value, 2), '0'), '.').'%' : '' }}</td><td class="right">-{{ $cur }}{{ number_format($invoice->discount_total, 2) }}</td></tr>
    @endif
    @if ($invoice->tax_total > 0)
      <tr><td class="muted">Tax</td><td class="right">{{ $cur }}{{ number_format($invoice->tax_total, 2) }}</td></tr>
    @endif
    <tr><td style="border-top:1px solid #0f172a"><strong>Total</strong></td><td class="right" style="border-top:1px solid #0f172a"><strong>{{ $cur }}{{ number_format($invoice->total, 2) }}</strong></td></tr>
    @if ($invoice->amount_paid > 0)<tr><td style="color:#059669;font-weight:bold">Paid</td><td class="right" style="color:#059669;font-weight:bold">-{{ $cur }}{{ number_format($invoice->amount_paid, 2) }}</td></tr>@endif
    <tr class="due"><td>Total Due</td><td class="right">{{ $cur }}{{ number_format($due, 2) }} {{ $invoice->currency }}</td></tr>
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
        <div style="margin-top:3px">{!! $invoice->notes ? $invoice->formattedNotes() : '—' !!}</div>
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
