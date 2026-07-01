<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
  * { font-family: DejaVu Sans, sans-serif; }
  body { color: #1f2937; font-size: 12px; margin: 0; }
  .wrap { padding: 32px 36px; }
  .row { width: 100%; }
  .brand { font-size: 22px; font-weight: bold; color: #2563eb; }
  .muted { color: #6b7280; }
  h2 { font-size: 16px; margin: 0 0 4px; }
  table { width: 100%; border-collapse: collapse; margin-top: 16px; }
  th { text-align: left; background: #0f172a; color: #fff; padding: 8px 10px; font-size: 11px; }
  td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
  .right { text-align: right; }
  .totals td { border: none; padding: 4px 10px; }
  .total-row td { font-size: 14px; font-weight: bold; border-top: 2px solid #0f172a; padding-top: 8px; }
  .badge { background: #ecfdf5; color: #047857; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; }
  .badge-unpaid { background: #fef2f2; color: #b91c1c; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; }
  .pill { background: #eff6ff; color: #1d4ed8; padding: 2px 6px; border-radius: 8px; font-size: 10px; }
</style>
</head>
<body>
<div class="wrap">
  <table class="row"><tr>
    <td style="border:none;">
      <div class="brand">RazinSoft</div>
      <div class="muted">info@razinsoft.com &middot; razinsoft.com</div>
    </td>
    <td style="border:none; text-align:right;">
      <h2>INVOICE</h2>
      <div class="muted">{{ $invoice->invoice_number }}</div>
      <div class="muted">{{ $invoice->issued_at?->format('M d, Y') }}</div>
      <div style="margin-top:6px;">
        @if($order->isPaid())<span class="badge">PAID</span>@else<span class="badge-unpaid">UNPAID</span>@endif
      </div>
    </td>
  </tr></table>

  <table class="row" style="margin-top:20px;"><tr>
    <td style="border:none; width:50%;">
      <div class="muted">Billed To</div>
      <strong>{{ $billing['first_name'] ?? '' }} {{ $billing['last_name'] ?? $order->user->name }}</strong><br>
      {{ $billing['email'] ?? $order->user->email }}<br>
      @if(!empty($billing['address'])){{ $billing['address'] }}, @endif{{ $billing['city'] ?? '' }} {{ $billing['country'] ?? '' }}
    </td>
    <td style="border:none; text-align:right;">
      <div class="muted">Order</div>
      <strong>{{ $order->order_number }}</strong><br>
      @if($order->isPaid())
        <span class="muted">Paid {{ $order->paid_at?->format('M d, Y') }}</span>
      @else
        <span class="muted" style="color:#b91c1c;">Payment pending</span>
      @endif
    </td>
  </tr></table>

  <table>
    <thead><tr><th>Product</th><th>Plan</th><th class="right">Qty</th><th class="right">Unit</th><th class="right">Amount</th></tr></thead>
    <tbody>
      @foreach($order->items as $it)
      <tr>
        <td>{{ $it->product_name }}</td>
        <td><span class="pill">{{ $it->plan_name ?? 'License' }}</span></td>
        <td class="right">{{ $it->quantity }}</td>
        <td class="right">${{ number_format($it->unit_price, 2) }}</td>
        <td class="right">${{ number_format($it->line_total, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <table class="totals" style="margin-top:8px; width:40%; float:right;">
    <tr><td>Subtotal</td><td class="right">${{ number_format($order->subtotal, 2) }}</td></tr>
    @if($order->discount > 0)
    <tr><td>Discount @if($order->coupon_code)({{ $order->coupon_code }})@endif</td><td class="right">-${{ number_format($order->discount, 2) }}</td></tr>
    @endif
    <tr><td>Tax</td><td class="right">$0.00</td></tr>
    <tr class="total-row"><td>Total</td><td class="right">${{ number_format($order->total, 2) }} {{ $order->currency }}</td></tr>
  </table>

  <div style="clear:both;"></div>
  <p class="muted" style="margin-top:40px; font-size:11px;">
    Thank you for your purchase. This invoice confirms a one-time license purchase. Source code and license files are available in your account dashboard. 30-day money-back guarantee applies.
  </p>
</div>
</body>
</html>
