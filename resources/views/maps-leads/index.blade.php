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
        .rsm-dash .live { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--muted); cursor:pointer; }
        .rsm-dash .rsm-pip { width:7px; height:7px; border-radius:50%; background:#cbd5e1; }
        .rsm-dash .rsm-pip.on { background:var(--green); animation:rsm-blink 1.8s ease-in-out infinite; }
        .rsm-dash .rsm-pip.busy { background:#f59e0b; }
        @keyframes rsm-blink { 0%,100% { opacity:1; } 50% { opacity:.3; } }
        @media (prefers-reduced-motion: reduce) { .rsm-dash .rsm-pip.on { animation:none; } }
        .rsm-dash .newbar { display:flex; align-items:center; gap:12px; margin-bottom:14px; padding:9px 12px;
                            background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; font-size:13px; }
    </style>
@endpush

@section('content')
    <div class="rsm-dash">
        <div class="toolbar">
            <a class="sub" href="{{ route('admin.maps-leads.runs') }}">Import history</a>

            {{-- Live updates. On by default so a running collection is visible
                 without touching anything; the choice is remembered per browser. --}}
            <label class="live" title="Check for newly collected leads every few seconds">
                <input type="checkbox" id="rsm-live" checked>
                <span class="rsm-pip" id="rsm-pip"></span>
                <span id="rsm-live-label">Live</span>
            </label>

            <span style="flex:1"></span>
            <a class="btn" href="{{ route('admin.maps-leads.export.csv', request()->query()) }}">Export CSV</a>
        </div>

        {{-- Shown instead of yanking the page out from under the reader. --}}
        <div class="newbar" id="rsm-newbar" hidden>
            <span id="rsm-newbar-text"></span>
            <button class="btn btn--primary" type="button" id="rsm-newbar-load">Show them</button>
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
                <select name="category">
                    <option value="">All</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
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
                    <th>Category</th>
                    <th>Contact</th>
                    <th>Location</th>
                    <th class="nowrap">Rating</th>
                    <th class="nowrap">Created</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($leads as $lead)
                    <tr>
                        <td>
                            <div class="name">{{ $lead->name }}</div>
                            <a class="sub" href="{{ $lead->maps_url }}" target="_blank" rel="noopener noreferrer">Open in Maps</a>
                        </td>
                        <td>
                            @if ($lead->category)
                                <span class="tag">{{ $lead->category }}</span>
                            @else
                                <span class="muted">-</span>
                            @endif
                            @if ($lead->search_category && $lead->search_category !== $lead->category)
                                {{-- What was searched for, when Maps filed the business under something else. --}}
                                <div class="sub">searched: {{ $lead->search_category }}</div>
                            @endif
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
                        <td class="nowrap">
                            {{-- Exact date on hover; the relative line is what is usually wanted. --}}
                            <div title="{{ $lead->created_at?->format('d M Y, g:i a') }}">
                                {{ $lead->created_at?->format('d M Y') ?? '-' }}
                            </div>
                            <div class="sub">{{ $lead->created_at?->diffForHumans() }}</div>
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
                    <tr><td colspan="8" class="muted" style="padding:22px;text-align:center">No leads match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:14px">{{ $leads->links() }}</div>
    </div>

    <script>
        /*
         * Live updates for the lead list.
         *
         * Polls a count-only endpoint rather than re-rendering the table, and
         * never reloads under the reader: when new leads appear it offers a
         * button instead. The one exception is the top of page 1 with nothing
         * selected, where a silent refresh is what someone watching a run
         * actually wants.
         *
         * Polling backs off when the tab is hidden and stops on repeated
         * failure, so a forgotten tab cannot sit there hammering the server.
         */
        (function () {
            const pip = document.getElementById('rsm-pip');
            const toggle = document.getElementById('rsm-live');
            const label = document.getElementById('rsm-live-label');
            const bar = document.getElementById('rsm-newbar');
            const barText = document.getElementById('rsm-newbar-text');
            const barLoad = document.getElementById('rsm-newbar-load');

            const endpoint = @json(route('admin.maps-leads.live')) + window.location.search;
            const onFirstPage = !new URLSearchParams(window.location.search).get('page');

            let known = { total: {{ $leads->total() }}, latest: {{ $leads->first()->id ?? 'null' }} };
            let failures = 0;
            let timer = null;

            const remember = (on) => { try { localStorage.setItem('rsm-live', on ? '1' : '0'); } catch (e) {} };
            const recalled = () => { try { return localStorage.getItem('rsm-live') !== '0'; } catch (e) { return true; } };

            function announce(total) {
                const n = total - known.total;
                barText.textContent = n > 0
                    ? `${n} new lead${n === 1 ? '' : 's'} collected.`
                    : 'The list has changed.';
                bar.hidden = false;
            }

            async function poll() {
                if (!toggle.checked) return;

                try {
                    const res = await fetch(endpoint, { headers: { Accept: 'application/json' } });
                    if (!res.ok) throw new Error(res.status);
                    const data = await res.json();
                    failures = 0;

                    pip.className = 'rsm-pip ' + (data.collecting ? 'busy' : 'on');
                    label.textContent = data.collecting ? 'Collecting' : 'Live';

                    const changed = data.latest !== known.latest || data.total !== known.total;

                    if (changed) {
                        // Safe to refresh silently only if nothing would be lost.
                        const undisturbed = onFirstPage
                            && window.scrollY < 80
                            && !document.querySelector('.rsm-dash select:focus, .rsm-dash input:focus');

                        if (undisturbed) {
                            window.location.reload();
                            return;
                        }
                        announce(data.total);
                    }
                } catch (e) {
                    // Server restarted, network blip, or the session expired.
                    if (++failures >= 5) {
                        toggle.checked = false;
                        label.textContent = 'Live off';
                        pip.className = 'rsm-pip';
                        return;
                    }
                } finally {
                    schedule();
                }
            }

            function schedule() {
                clearTimeout(timer);
                if (!toggle.checked) return;
                // Hidden tabs check rarely; a visible one keeps up with a run.
                timer = setTimeout(poll, document.hidden ? 60000 : 8000);
            }

            toggle.checked = recalled();
            toggle.addEventListener('change', () => {
                remember(toggle.checked);
                pip.className = 'rsm-pip' + (toggle.checked ? ' on' : '');
                label.textContent = toggle.checked ? 'Live' : 'Live off';
                if (toggle.checked) poll(); else clearTimeout(timer);
            });

            barLoad.addEventListener('click', () => window.location.reload());
            document.addEventListener('visibilitychange', schedule);

            pip.className = 'rsm-pip' + (toggle.checked ? ' on' : '');
            schedule();
        })();
    </script>
@endsection
