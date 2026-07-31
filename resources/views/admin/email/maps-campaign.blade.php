{{--
  One-click outreach to Maps leads, grouped by product.

  Each row is a product line with its own letter. Ticking several and pressing
  send builds one campaign per product, so a pharmacy and a restaurant get the
  message written for them without anyone choosing per segment.
--}}
@extends('admin.layouts.app')
@section('title', 'Send to Maps Leads')

@push('head')
    <style>
        .mc { --line:#e2e8f0; --muted:#64748b; --green:#16a06b; }
        .mc .box { padding:16px 18px; background:#fff; border:1px solid var(--line); border-radius:12px; }
        .mc table { width:100%; border-collapse:collapse; font-size:13px; }
        .mc th, .mc td { padding:10px 12px; text-align:left; border-bottom:1px solid var(--line); vertical-align:top; }
        .mc th { background:#f8fafc; font-size:11px; text-transform:uppercase;
                 letter-spacing:.4px; color:var(--muted); }
        .mc tr:last-child td { border-bottom:0; }
        .mc .ready { font-size:17px; font-weight:700; font-variant-numeric:tabular-nums; }
        .mc .cats { color:var(--muted); font-size:12px; }
        .mc .warn-txt { color:#b45309; }
        .mc input[type=checkbox] { accent-color:var(--green); }
        .mc .bar { display:flex; align-items:center; gap:14px; margin-top:16px; }
        .mc .btn { padding:10px 20px; border:0; border-radius:8px; background:var(--green);
                   color:#fff; font:inherit; font-weight:600; cursor:pointer; }
        .mc .btn:disabled { opacity:.45; cursor:not-allowed; }
        .mc .note { margin:0 0 16px; padding:11px 14px; border-radius:9px; font-size:13px; }
        .mc .note--ok { background:#dcfce7; border-left:3px solid var(--green); }
        .mc .note--bad { background:#fef2f2; border-left:3px solid #dc2626; }
        .mc .note--info { background:#eff6ff; border:1px solid #bfdbfe; }
        .mc .nowrap { white-space:nowrap; }
        .mc .empty { padding:34px 20px; text-align:center; color:var(--muted); }
    </style>
@endpush

@section('content')
    {{-- picked holds product names, which is what the form posts; counts is a
         lookup so the button can show the total without a round trip. --}}
    <div class="mc" x-data="{
        picked: [],
        counts: @js($segments->pluck('ready', 'product')),
        get total() {
            return this.picked.reduce((sum, p) => sum + (this.counts[p] || 0), 0);
        }
    }">
        @if (session('status'))<p class="note note--ok">{{ session('status') }}</p>@endif
        @if (session('error'))<p class="note note--bad">{{ session('error') }}</p>@endif

        <p class="note note--info">
            Each product line is mailed with its own letter. Only leads with a shared
            inbox address (info@, contact@) that have never been contacted are counted —
            named individuals and anyone who opted out are excluded.
        </p>

        <form method="POST" action="{{ route('admin.email.maps-campaign.send') }}">
            @csrf

            <div class="box">
                @if ($segments->isEmpty())
                    <div class="empty">
                        <p style="margin:0 0 6px;font-weight:600">Nothing is ready to send.</p>
                        <p style="margin:0;font-size:13px">
                            Leads need a website, and an email address found on it. Collect some
                            with the extension, then turn on <b>Look up email addresses</b> under
                            <a href="{{ route('admin.email.automation') }}">Automation</a>.
                        </p>
                    </div>
                @else
                    <table>
                        <thead>
                        <tr>
                            <th style="width:34px"></th>
                            <th>Product</th>
                            <th>Categories it covers</th>
                            <th class="nowrap">Ready</th>
                            <th>Letter it sends</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($segments as $row)
                            @php $sendable = $row['ready'] > 0 && $row['template_id']; @endphp
                            <tr>
                                <td>
                                    <input type="checkbox" name="products[]"
                                           value="{{ $row['product'] }}" x-model="picked"
                                           @disabled(! $sendable)>
                                </td>
                                <td><b>{{ $row['product'] }}</b></td>
                                <td class="cats">{{ implode(', ', $row['categories']) }}</td>
                                <td class="ready">{{ number_format($row['ready']) }}</td>
                                <td class="{{ $row['template_id'] ? '' : 'warn-txt' }}">
                                    {{ $row['template_name'] }}
                                    @if ($row['template_id'])
                                        <div class="cats">{{ $row['subject'] }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            @if ($segments->isNotEmpty())
                <div class="bar">
                    <button class="btn" type="submit" :disabled="picked.length === 0">
                        Send to <span x-text="total"></span> lead<span x-show="total !== 1">s</span>
                    </button>
                    <span class="cats" x-show="picked.length">
                        <span x-text="picked.length"></span> campaign<span x-show="picked.length !== 1">s</span>,
                        one per product
                    </span>
                </div>
            @endif
        </form>

        @if ($recent->isNotEmpty())
            <div class="box" style="margin-top:20px">
                <h2 style="margin:0 0 10px;font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted)">
                    Recently sent
                </h2>
                <table>
                    <tbody>
                    @foreach ($recent as $campaign)
                        <tr>
                            <td>
                                <a href="{{ route('admin.email.campaigns.show', $campaign) }}">{{ $campaign->name }}</a>
                            </td>
                            <td class="cats nowrap">{{ $campaign->status }}</td>
                            <td class="cats nowrap">{{ number_format($campaign->total_recipients) }} recipients</td>
                            <td class="cats nowrap">{{ $campaign->created_at?->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
