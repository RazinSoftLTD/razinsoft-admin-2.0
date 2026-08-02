<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
  /* DomPDF has no flexbox and no grid, so every column here is a table cell. Widths are set
     explicitly for the same reason: it will not work them out from content the way a browser does. */
  @page { margin: 26px 34px; }
  * { font-family: DejaVu Sans, sans-serif; }
  body { color: #1f2937; font-size: 11px; margin: 0; }

  .navy { color: #16305c; }
  .blue { color: #2b5aa0; }
  .muted { color: #6b7280; }
  .right { text-align: right; }
  .center { text-align: center; }
  .label { font-size: 9px; font-weight: bold; color: #2b5aa0; text-transform: uppercase; letter-spacing: .6px; }

  table.plain { width: 100%; border-collapse: collapse; }
  table.plain > tr > td, table.plain td { border: none; vertical-align: top; }

  /* Items */
  /* Fixed layout: the declared widths hold whatever the cells contain, so the column lines land
     in the same place on every row — and in the same place as the on-screen invoice, which uses
     the same widths. Auto layout let long content nudge them. */
  table.items { width: 100%; border-collapse: collapse; margin-top: 16px; table-layout: fixed; }
  table.items th {
    background: #2e5288; color: #fff; padding: 10px 8px;
    font-size: 9.5px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px;
    border: 1px solid #2e5288;
  }
  table.items td { padding: 8px 8px; border: 1px solid #e4e8ef; vertical-align: middle; }
  /* No radius here, deliberately: DomPDF rounds the fill but not the border, which left a white
     notch in each corner and a stub of border hanging past the head. Square corners render clean;
     the on-screen invoice keeps its rounded ones, where the browser clips properly. */
  table.items td.desc { vertical-align: top; }
  .item-title { font-weight: bold; color: #16305c; font-size: 11px; }
  .item-detail { margin-top: 5px; font-size: 9px; line-height: 1.5; color: #4b5563; }

  /* Sub-description set as a numbered two-column table when the list is long. */
  table.cols { width: 100%; border-collapse: collapse; margin-top: 6px; }
  table.cols td { border: none; padding: 0 10px 0 0; vertical-align: top; width: 50%; }
  table.numbered { width: 100%; border-collapse: collapse; }
  table.numbered td { border: none; padding: 1px 0; font-size: 8.5px; line-height: 1.35; color: #4b5563; }
  table.numbered td.n { width: 20px; color: #6b7280; vertical-align: top; }

  /* Totals */
  /* Splitting a totals block across pages leaves a figure stranded from its label. */
  table.totals { width: 300px; margin-left: auto; margin-top: 14px; border-collapse: collapse; page-break-inside: avoid; }
  table.totals td { padding: 7px 12px; border: none; }
  table.totals tr.line td { border-top: 1px solid #e4e8ef; }
  table.totals tr.due td { background: #eef2f8; color: #16305c; font-weight: bold; font-size: 13px; padding: 10px 12px; }

  /* Footer columns */
  table.foot { width: 100%; border-collapse: collapse; margin-top: 24px; page-break-inside: avoid; }
  table.foot td { border: none; vertical-align: top; font-size: 9.5px; line-height: 1.6; padding-right: 16px; }
  table.bank { border-collapse: collapse; margin-top: 4px; }
  table.bank td { border: none; padding: 1px 10px 1px 0; font-size: 9.5px; }

  .thanks { margin-top: 22px; border-top: 1px solid #e4e8ef; padding-top: 10px; text-align: center; color: #2b5aa0; font-weight: bold; font-size: 11px; }
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
    $paidOff = $due <= 0;
    $statusText = $paidOff ? 'PAID' : ($invoice->amount_paid > 0 ? 'PARTIALLY PAID' : 'UNPAID');
    $statusColour = $paidOff || $invoice->amount_paid > 0 ? '#047857' : '#dc2626';

    // The company's own details. Hardcoded here as the billing address always has been — there is
    // no settings row for them yet, so this is the one place to change them.
    $us = [
        'name' => 'RazinSoft',
        'email' => 'info@razinsoft.com',
        'phone' => '+8801711257498',
        'address' => ['RMR Center 1/1 (A&B) Shyamoli', 'Ring Road, Dhaka - 1207.', 'Bangladesh'],
    ];
    // Blank entries are dropped, so a detail we do not have never prints as an empty row.
    $bankName = 'Razinsoft Limited';
    $bank = array_filter([
        'A/C:' => '1111070003744',
        'Bank:' => 'Eastern Bank PLC',
        'Branch:' => 'Shyamoli',
        'Routing #:' => '095264301',
        'Address:' => 'Shyamoli Ring Road',
    ], fn ($v) => filled($v));

    // Off for an invoice paid by card — an account number there is just clutter. The columns that
    // remain share the width out between them rather than leaving a gap where this one was.
    $showBank = $invoice->show_bank_details && ($bank || filled($bankName));
    $footCols = $showBank ? 3 : 2;
    $footWidth = round(100 / $footCols, 2).'%';

    $iconPath = public_path('images/razinsoft-icon-print.png');
    $logoPath = public_path('images/razinsoft-logo-print.png');
    $logoSrc = is_file($iconPath)
        ? 'data:image/png;base64,'.base64_encode(file_get_contents($iconPath))
        : (is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null);

    $num = fn ($v) => rtrim(rtrim(number_format($v, 2), '0'), '.');
@endphp
<body>

{{-- ============ HEADER: who we are (left) / what this is (right) ============ --}}
<table class="plain">
  <tr>
    <td style="width:56%">
      <table class="plain">
        <tr>
          @if ($logoSrc)
            <td style="width:58px"><img src="{{ $logoSrc }}" alt="{{ $us['name'] }}" style="height:52px;width:auto"></td>
          @endif
          <td style="vertical-align:middle;padding-left:{{ $logoSrc ? '10px' : '0' }}">
            <div style="font-size:21px;font-weight:bold;" class="navy">{{ $us['name'] }}</div>
          </td>
        </tr>
      </table>

      <table class="plain" style="margin-top:10px">
        <tr>
          <td style="width:16px;color:#2b5aa0">✉</td>
          <td style="font-size:10px">{{ $us['email'] }}</td>
        </tr>
        <tr>
          <td style="width:16px;color:#2b5aa0;padding-top:4px">☎</td>
          <td style="font-size:10px;padding-top:4px">{{ $us['phone'] }}</td>
        </tr>
        <tr>
          <td style="width:16px;color:#2b5aa0;padding-top:4px">⌂</td>
          <td style="font-size:10px;padding-top:4px;line-height:1.45">{!! implode('<br>', array_map('e', $us['address'])) !!}</td>
        </tr>
      </table>
    </td>

    <td style="width:44%" class="right">
      <div style="font-size:30px;font-weight:bold;letter-spacing:1px" class="navy">INVOICE</div>
      <div style="height:3px;background:#2b5aa0;width:120px;margin-left:auto;margin-top:3px"></div>

      <table style="border-collapse:collapse;margin-left:auto;margin-top:16px">
        <tr>
          <td style="border:1px solid #e4e8ef;padding:8px 14px;font-weight:bold;color:#16305c;font-size:10px">Invoice Number</td>
          <td style="border:1px solid #e4e8ef;padding:8px 14px;font-weight:bold;font-size:10px" class="blue">{{ $invoice->invoice_number }}</td>
        </tr>
        <tr>
          <td style="border:1px solid #e4e8ef;padding:8px 14px;font-weight:bold;color:#16305c;font-size:10px">Invoice Date</td>
          <td style="border:1px solid #e4e8ef;padding:8px 14px;font-size:10px">{{ $invoice->invoice_date->format('d F, Y') }}</td>
        </tr>
        @if ($invoice->due_date)
        <tr>
          <td style="border:1px solid #e4e8ef;padding:8px 14px;font-weight:bold;color:#16305c;font-size:10px">Due Date</td>
          <td style="border:1px solid #e4e8ef;padding:8px 14px;font-weight:bold;font-size:10px" class="blue">{{ $invoice->due_date->format('d F, Y') }}</td>
        </tr>
        @endif
      </table>
    </td>
  </tr>
</table>

{{-- ============ BILLED TO ============
     One centred block, not a from/to pair: the company's own details already head the page, so
     repeating them here as BILLED FROM said the same thing twice. Due date and payment status sit
     under the client, which is where the eye goes to answer "who owes this, and is it settled?" --}}
<table class="plain" style="margin-top:24px">
  <tr>
    <td style="text-align:center">
      <div class="label">Billed To</div>
      <div style="font-weight:bold;font-size:13px;color:#16305c;margin-top:5px">{{ $invoice->bill_to_name ?: '—' }}</div>
      <div style="margin-top:2px;line-height:1.6">
        @if ($invoice->bill_to_company){{ $invoice->bill_to_company }}<br>@endif
        @if ($invoice->bill_to_email){{ $invoice->bill_to_email }}<br>@endif
        @if ($invoice->bill_to_phone){{ $invoice->bill_to_phone }}<br>@endif
        @if ($invoice->bill_to_address){{ $invoice->bill_to_address }}@endif
      </div>
      @if ($invoice->due_date)
        <div style="margin-top:8px;font-size:10px" class="muted">Due Date: <span style="font-weight:bold" class="blue">{{ $invoice->due_date->format('d F, Y') }}</span></div>
      @endif
      <div style="margin-top:10px">
        <span style="display:inline-block;border:1px solid {{ $statusColour }};color:{{ $statusColour }};border-radius:6px;padding:8px 22px;font-size:13px;font-weight:bold;letter-spacing:.5px">{{ $statusText }}</span>
      </div>
    </td>
  </tr>
</table>

{{-- ============ ITEMS ============ --}}
<table class="items">
  <thead>
    <tr>
      <th style="width:43%;text-align:left">Description</th>
      <th style="width:7%">Qty</th>
      <th style="width:15%">Unit</th>
      <th style="width:12%">Unit Price<br>({{ $invoice->currency }})</th>
      <th style="width:10%">Tax<br>({{ $invoice->currency }})</th>
      <th style="width:13%">Amount<br>({{ $invoice->currency }})</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($invoice->items as $item)
      @php
          // A long feature list reads better in two numbered columns than as one column running
          // down the page; a short one stays as bullets, where numbers would only add noise.
          $listItems = $item->subDescriptionItems();
          $twoColumn = count($listItems) > 8;
          $half = $twoColumn ? (int) ceil(count($listItems) / 2) : 0;
      @endphp
      <tr>
        <td class="desc">
          <div class="item-title">{{ $item->description }}</div>

          @if ($twoColumn)
            <table class="cols">
              <tr>
                @foreach ([array_slice($listItems, 0, $half), array_slice($listItems, $half)] as $colIndex => $column)
                  <td>
                    <table class="numbered">
                      @foreach ($column as $i => $line)
                        <tr>
                          <td class="n">{{ $colIndex * $half + $i + 1 }}.</td>
                          <td>{!! $line !!}</td>
                        </tr>
                      @endforeach
                    </table>
                  </td>
                @endforeach
              </tr>
            </table>
          @elseif ($item->sub_description)
            <div class="item-detail">{!! $item->formattedSubDescription() !!}</div>
          @endif
        </td>
        <td class="center">{{ $num($item->qty) }}</td>
        <td class="center">{{ $item->unit ?: '—' }}</td>
        <td class="center">{{ number_format($item->unit_price, 2) }}</td>
        <td class="center">{{ $item->tax_percent > 0 ? $num($item->tax_percent).'%' : '—' }}</td>
        <td class="center" style="font-weight:bold;color:#16305c">{{ number_format($item->amount, 2) }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

{{-- ============ TOTALS ============ --}}
<table class="totals">
  <tr>
    <td class="muted">Sub Total</td>
    <td class="right">{{ $cur }}{{ number_format($invoice->subtotal, 2) }}</td>
  </tr>
  @if ($invoice->discount_total > 0)
    <tr>
      <td class="muted">Discount{{ $invoice->discount_type === 'percent' && $invoice->discount_value > 0 ? ' ('.$num($invoice->discount_value).'%)' : '' }}</td>
      <td class="right" style="color:#dc2626">-{{ $cur }}{{ number_format($invoice->discount_total, 2) }}</td>
    </tr>
  @endif
  @if ($invoice->tax_total > 0)
    <tr>
      <td class="muted">Tax</td>
      <td class="right">{{ $cur }}{{ number_format($invoice->tax_total, 2) }}</td>
    </tr>
  @endif
  <tr class="line">
    <td style="font-weight:bold;color:#16305c">Total</td>
    <td class="right" style="font-weight:bold;color:#16305c">{{ $cur }}{{ number_format($invoice->total, 2) }}</td>
  </tr>
  @if ($invoice->amount_paid > 0)
    <tr>
      <td style="color:#047857;font-weight:bold">Paid</td>
      <td class="right" style="color:#047857;font-weight:bold">-{{ $cur }}{{ number_format($invoice->amount_paid, 2) }}</td>
    </tr>
  @endif
  <tr class="due">
    <td style="text-transform:uppercase;letter-spacing:.5px">Total Due</td>
    <td class="right">{{ $cur }}{{ number_format($due, 2) }} {{ $invoice->currency }}</td>
  </tr>
</table>

{{-- ============ PAYMENT HISTORY ============ --}}
@if ($invoice->payments->count())
  <div style="margin-top:22px">
    <div class="label">Payment History</div>
    <table class="items" style="margin-top:6px">
      <thead>
        <tr>
          <th style="text-align:left">Date</th><th style="text-align:left">Method</th>
          <th style="text-align:left">Reference</th><th>Amount</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($invoice->payments as $p)
          <tr>
            <td>{{ $p->paid_at->format('d M Y') }}</td>
            <td>{{ $p->method ?? '—' }}</td>
            <td>{{ $p->reference ?? '—' }}</td>
            <td class="center">{{ $cur }}{{ number_format($p->amount, 2) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

{{-- ============ NOTES / BANK / TERMS ============ --}}
<table class="foot">
  <tr>
    <td style="width:{{ $footWidth }}">
      <div class="label">Notes</div>
      <div style="margin-top:5px">{!! $invoice->notes ? $invoice->formattedNotes() : '—' !!}</div>
    </td>
    @if ($showBank)
      <td style="width:{{ $footWidth }}">
        <div class="label">Bank Info</div>
        @if (filled($bankName))
          <div style="font-weight:bold;color:#16305c;margin-top:5px">{{ $bankName }}</div>
        @endif
        <table class="bank">
          @foreach ($bank as $key => $value)
            <tr>
              <td class="muted">{{ $key }}</td>
              <td>{{ $value }}</td>
            </tr>
          @endforeach
        </table>
      </td>
    @endif
    <td style="width:{{ $footWidth }};padding-right:0">
      <div class="label">Terms</div>
      <div style="margin-top:5px">{!! $invoice->terms ? $invoice->formattedTerms() : '—' !!}</div>
    </td>
  </tr>
</table>

<div class="thanks">Thank you for your business!</div>

</body>
</html>
