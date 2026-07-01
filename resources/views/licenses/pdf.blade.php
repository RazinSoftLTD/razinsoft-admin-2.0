<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
  * { font-family: DejaVu Sans, sans-serif; }
  @page { margin: 0; }
  body { margin: 0; color: #1f2937; font-size: 12px; }
  .page { padding: 40px; }
  .frame { border: 2px solid #2563eb; border-radius: 6px; padding: 0; }
  .frame-inner { border: 1px solid #c7d2fe; margin: 6px; padding: 32px 36px; }
  .top { width: 100%; }
  .brand { font-size: 22px; font-weight: bold; color: #2563eb; }
  .muted { color: #6b7280; }
  .ribbon { background: #2563eb; color: #fff; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: bold; }
  h1 { font-size: 26px; color: #1e293b; margin: 24px 0 2px; letter-spacing: 0.5px; }
  .sub { color: #6b7280; font-size: 12px; }
  .key { margin: 22px 0; text-align: center; }
  .key .label { font-size: 10px; letter-spacing: 1px; color: #6b7280; text-transform: uppercase; }
  .key .val { display: inline-block; margin-top: 6px; font-size: 20px; font-weight: bold; letter-spacing: 2px; color: #1e293b; background: #eff6ff; border: 1px dashed #93c5fd; border-radius: 8px; padding: 10px 22px; }
  table.meta { width: 100%; border-collapse: collapse; margin-top: 12px; }
  table.meta td { padding: 7px 8px; border-bottom: 1px solid #eef2f7; font-size: 12px; }
  table.meta td.k { color: #6b7280; width: 38%; }
  table.meta td.v { color: #1e293b; font-weight: bold; }
  .terms-h { margin-top: 22px; font-size: 11px; font-weight: bold; letter-spacing: 1px; color: #2563eb; text-transform: uppercase; }
  .terms { margin-top: 8px; }
  .terms li { margin: 4px 0; color: #374151; }
  .badge-active { color: #047857; font-weight: bold; }
  .foot { margin-top: 26px; border-top: 1px solid #eef2f7; padding-top: 14px; font-size: 10px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
<div class="page">
  <div class="frame">
    <div class="frame-inner">
      <table class="top"><tr>
        <td style="text-align:left;">
          <div class="brand">RazinSoft</div>
          <div class="muted" style="font-size:11px;">info@razinsoft.com &middot; razinsoft.com</div>
        </td>
        <td style="text-align:right;"><span class="ribbon">OFFICIAL LICENSE</span></td>
      </tr></table>

      <h1>Software License Certificate</h1>
      <p class="sub">This certifies the holder below is granted a license to use the product per the purchased plan.</p>

      <div class="key">
        <div class="label">License Key</div>
        <div class="val">{{ $license->license_key }}</div>
      </div>

      <table class="meta">
        <tr><td class="k">Product</td><td class="v">{{ $item->product_name }}</td></tr>
        <tr><td class="k">Plan</td><td class="v">{{ $item->plan_name ?? 'Standard License' }}</td></tr>
        <tr><td class="k">Licensed to</td><td class="v">{{ $order->user->name }} &lt;{{ $order->user->email }}&gt;</td></tr>
        <tr><td class="k">Order number</td><td class="v">{{ $order->order_number }}</td></tr>
        <tr><td class="k">Issued on</td><td class="v">{{ ($license->issued_at ?? now())->format('F d, Y') }}</td></tr>
        <tr><td class="k">Status</td><td class="v"><span class="badge-active">ACTIVE</span></td></tr>
      </table>

      <p class="terms-h">License Terms</p>
      <ul class="terms">
        @forelse ($perks as $perk)
          <li>{{ $perk }}</li>
        @empty
          <li>Standard license terms apply.</li>
        @endforelse
      </ul>

      <div class="foot">
        This certificate authorises use of the above product under the purchased plan. Source code and updates are available
        from your RazinSoft account dashboard while this license remains active. Keep your license key confidential.
      </div>
    </div>
  </div>
</div>
</body>
</html>
