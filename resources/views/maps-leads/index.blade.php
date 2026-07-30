{{--
  Maps lead management dashboard.

  Extends the admin layout so it renders inside the normal admin chrome. The
  styling is kept in a `.rsm-dash` scoped block rather than rewritten against
  this app's Tailwind tokens, so nothing here can leak into sibling screens.

  Note: these are the Google Maps collector's own maps_leads rows. The CRM
  `leads` feature (admin/leads) is unrelated and untouched.
--}}
@extends('admin.layouts.app')
@section('title', 'Maps Leads')

@push('head')
    <style>
        .rsm-dash { --line:#e2e8f0; --muted:#64748b; --green:#16a06b; }
        .rsm-dash .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:18px; }
        .rsm-dash .card { padding:12px 14px; background:#fff; border:1px solid var(--line); border-radius:10px; }
        .rsm-dash .card b { display:block; font-size:22px; }
        .rsm-dash .card span { color:var(--muted); font-size:12px; text-transform:uppercase; letter-spacing:.4px; }
        .rsm-dash form.filters { display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end; margin-bottom:16px;
                                 padding:14px; background:#fff; border:1px solid var(--line); border-radius:10px; }
        .rsm-dash form.filters label { display:flex; flex-direction:column; gap:3px; font-size:11px; color:var(--muted);
                                       text-transform:uppercase; letter-spacing:.4px; }
        .rsm-dash input, .rsm-dash select { padding:6px 8px; border:1px solid var(--line); border-radius:7px; font:inherit; background:#fff; }
        .rsm-dash .btn { padding:7px 13px; border:1px solid var(--line); border-radius:7px; background:#fff;
                         font:inherit; font-weight:600; cursor:pointer; text-decoration:none; color:inherit; }
        .rsm-dash .btn--primary { background:var(--green); border-color:var(--green); color:#fff; }
        .rsm-dash .wrap { overflow-x:auto; background:#fff; border:1px solid var(--line); border-radius:10px; }
        .rsm-dash table { width:100%; border-collapse:collapse; font-size:13px; }
        .rsm-dash th, .rsm-dash td { padding:9px 11px; text-align:left; border-bottom:1px solid var(--line); vertical-align:top; }
        .rsm-dash th { background:#f1f5f9; font-size:11px; text-transform:uppercase; letter-spacing:.4px; color:var(--muted); white-space:nowrap; }
        .rsm-dash tr:last-child td { border-bottom:0; }
        .rsm-dash .name { font-weight:600; }
        .rsm-dash .sub { color:var(--muted); font-size:12px; }
        .rsm-dash .tag { display:inline-block; padding:1px 7px; border-radius:999px; background:#eef2f7; font-size:11px; }
        .rsm-dash .tag--new { background:#dbeafe; } .rsm-dash .tag--contacted { background:#fef3c7; }
        .rsm-dash .tag--qualified { background:#dcfce7; } .rsm-dash .tag--won { background:#bbf7d0; }
        .rsm-dash .tag--lost { background:#fee2e2; }
        .rsm-dash .flash { margin-bottom:14px; padding:9px 12px; background:#dcfce7; border-left:3px solid var(--green); border-radius:6px; }
        .rsm-dash .muted { color:var(--muted); }
        .rsm-dash .nowrap { white-space:nowrap; }
        .rsm-dash .toolbar { display:flex; align-items:center; gap:14px; margin-bottom:16px; }
    </style>
@endpush

@section('content')
    <div class="rsm-dash">
        <div class="toolbar">
            <a class="sub" href="{{ route('admin.maps-leads.runs') }}">Import history</a>
            <span style="flex:1"></span>
            <a class="btn" href="{{ route('admin.maps-leads.export.csv', request()->query()) }}">Export CSV</a>
        </div>

        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif

        <div class="cards">
            <div class="card"><b>{{ number_format($summary['total']) }}</b><span>Total leads</span></div>
            <div class="card"><b>{{ number_format($summary['with_phone']) }}</b><span>With phone</span></div>
            <div class="card"><b>{{ number_format($summary['with_website']) }}</b><span>With website</span></div>
            <div class="card"><b>{{ number_format($summary['runs']) }}</b><span>Import runs</span></div>
        </div>

        <form class="filters" method="get">
            <label>Search
                <input type="search" name="q" value="{{ $search }}" placeholder="name, phone, address">
            </label>
            <label>Country
                <select name="country">
                    <option value="">All</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country }}" @selected(($filters['country'] ?? '') === $country)>{{ $country }}</option>
                    @endforeach
                </select>
            </label>
            <label>City
                <select name="city">
                    <option value="">All</option>
                    @foreach ($cities as $city)
                        <option value="{{ $city }}" @selected(($filters['city'] ?? '') === $city)>{{ $city }}</option>
                    @endforeach
                </select>
            </label>
            <label>Category
                <input type="text" name="category" value="{{ $filters['category'] ?? '' }}">
            </label>
            <label>Status
                <select name="status">
                    <option value="">All</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>
            <label>Min rating
                <input type="number" name="min_rating" step="0.1" min="0" max="5" value="{{ $filters['min_rating'] ?? '' }}">
            </label>
            <label>Has phone
                <select name="has_phone">
                    <option value="">Any</option>
                    <option value="1" @selected(($filters['has_phone'] ?? '') === '1')>Yes</option>
                    <option value="0" @selected(($filters['has_phone'] ?? '') === '0')>No</option>
                </select>
            </label>
            <button class="btn btn--primary" type="submit">Filter</button>
            <a class="btn" href="{{ route('admin.maps-leads.dashboard') }}">Reset</a>
        </form>

        <div class="wrap">
            <table>
                <thead>
                <tr>
                    <th>Business</th>
                    <th>Contact</th>
                    <th>Location</th>
                    <th class="nowrap">Rating</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($leads as $lead)
                    <tr>
                        <td>
                            <div class="name">{{ $lead->name }}</div>
                            <div class="sub">{{ $lead->category }}</div>
                            <a class="sub" href="{{ $lead->maps_url }}" target="_blank" rel="noopener noreferrer">Open in Maps</a>
                        </td>
                        <td>
                            @if ($lead->phone)
                                <div><a href="tel:{{ $lead->phone }}">{{ $lead->phone }}</a></div>
                            @else
                                <div class="muted">no phone shown</div>
                            @endif
                            @if ($lead->website)
                                <a class="sub" href="{{ $lead->website }}" target="_blank" rel="noopener noreferrer">
                                    {{ parse_url($lead->website, PHP_URL_HOST) }}
                                </a>
                            @endif
                        </td>
                        <td>
                            <div>{{ $lead->address }}</div>
                            <div class="sub">{{ collect([$lead->search_city, $lead->search_country])->filter()->join(', ') }}</div>
                        </td>
                        <td class="nowrap">
                            @if ($lead->rating)
                                {{ number_format($lead->rating, 1) }}
                                <span class="sub">({{ number_format((int) $lead->review_count) }})</span>
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                        <td><span class="tag tag--{{ $lead->status }}">{{ ucfirst($lead->status) }}</span></td>
                        <td>
                            <form method="post" action="{{ route('admin.maps-leads.update', $lead) }}" style="display:flex;gap:5px">
                                @csrf @method('PATCH')
                                <select name="status">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}" @selected($lead->status === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                                <button class="btn" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted" style="padding:22px;text-align:center">No leads match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:14px">{{ $leads->links() }}</div>
    </div>
@endsection
