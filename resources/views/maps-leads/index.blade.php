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
        .rsm-dash .breakdown { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
                               gap:12px; margin-bottom:16px; }
        .rsm-dash .panelbox { padding:12px 14px; background:#fff; border:1px solid var(--line); border-radius:10px; }
        .rsm-dash .panelbox h3 { margin:0 0 8px; font-size:11px; text-transform:uppercase;
                                 letter-spacing:.4px; color:var(--muted); }
        .rsm-dash .panelbox ul { margin:0; padding:0; list-style:none; }
        .rsm-dash .panelbox li { display:flex; align-items:baseline; gap:10px; padding:3px 0; font-size:13px; }
        .rsm-dash .panelbox li a { flex:1; overflow:hidden; white-space:nowrap; text-overflow:ellipsis;
                                   color:inherit; text-decoration:none; }
        .rsm-dash .panelbox li a:hover { color:var(--green); text-decoration:underline; }
        .rsm-dash .panelbox li b { font-variant-numeric:tabular-nums; }
    </style>
@endpush

@section('content')
    @php
        // Drives the count badge on the Filters button, so it is obvious at a
        // glance that a narrowed list is not the whole list.
        $activeFilters = count(array_filter(
            request()->only(['q', 'country', 'city', 'category', 'status', 'min_rating', 'min_reviews', 'has_phone', 'has_website', 'from', 'to']),
            fn ($v) => $v !== null && $v !== '',
        ));
    @endphp

    <div class="rsm-dash" x-data="{ filtersOpen: false }" @keydown.escape.window="filtersOpen = false">
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

            <button type="button" @click="filtersOpen = true"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M6 12h12M9 18h6"/></svg>
                Filters
                @if ($activeFilters)<span class="grid h-5 min-w-5 place-items-center rounded-full bg-[var(--color-primary)] px-1.5 text-[11px] font-bold text-white">{{ $activeFilters }}</span>@endif
            </button>

            @if ($activeFilters)
                <a href="{{ route('admin.maps-leads.dashboard') }}" title="Clear filters" class="sub">Clear</a>
            @endif

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


        {{--
          Where the current selection came from. A run sweeps several cities in
          one go now, so the split by place and by category is the thing the
          plain list cannot show. Every row is a link that adds its own filter.
        --}}
        @if ($byPlace->isNotEmpty())
            <div class="breakdown">
                <section class="panelbox">
                    <h3>By country &amp; city</h3>
                    <ul>
                        @foreach ($byPlace as $row)
                            <li>
                                <a href="{{ request()->fullUrlWithQuery([
                                    'country' => $row['values']['search_country'],
                                    'city' => $row['values']['search_city'],
                                    'page' => null,
                                ]) }}">{{ $row['label'] }}</a>
                                <b>{{ number_format($row['total']) }}</b>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="panelbox">
                    <h3>By category</h3>
                    <ul>
                        @foreach ($byCategory as $row)
                            <li>
                                <a href="{{ request()->fullUrlWithQuery([
                                    'category' => $row['values']['category'],
                                    'page' => null,
                                ]) }}">{{ $row['label'] }}</a>
                                <b>{{ number_format($row['total']) }}</b>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
        @endif

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

        {{--
          Filter drawer. Same shape as the CRM lead list's, so the two behave
          identically: backdrop click, Escape, or the X closes it.

          The fields were an inline bar above the table, which ran out of room
          once there were seven of them and left the labels overlapping.
        --}}
        <div x-show="filtersOpen" x-cloak class="fixed inset-0 z-40">
            <div x-show="filtersOpen" x-transition.opacity @click="filtersOpen = false" class="absolute inset-0 bg-black/30"></div>
            <div x-show="filtersOpen"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                 class="absolute right-0 top-0 flex h-full w-80 max-w-full flex-col bg-white shadow-2xl">

                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Filter Maps Leads</h2>
                    <button type="button" @click="filtersOpen = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-500 hover:bg-gray-100">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <form id="maps-lead-filters" method="GET" class="flex-1 space-y-4 overflow-y-auto p-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Search</label>
                        <input type="search" name="q" value="{{ $search }}" placeholder="name, phone, address"
                               class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Country</label>
                        <select name="country" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All countries</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country }}" @selected(($filters['country'] ?? '') === $country)>{{ $country }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">City</label>
                        <select name="city" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All cities</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city }}" @selected(($filters['city'] ?? '') === $city)>{{ $city }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Category</label>
                        <select name="category" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Status</label>
                        <select name="status" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Quality</label>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">Min rating</label>
                                <input type="number" name="min_rating" step="0.1" min="0" max="5" value="{{ $filters['min_rating'] ?? '' }}"
                                       class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">Min reviews</label>
                                <input type="number" name="min_reviews" min="0" value="{{ $filters['min_reviews'] ?? '' }}"
                                       class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">Has phone</label>
                            <select name="has_phone" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                                <option value="">Any</option>
                                <option value="1" @selected(($filters['has_phone'] ?? '') === '1')>Yes</option>
                                <option value="0" @selected(($filters['has_phone'] ?? '') === '0')>No</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">Has website</label>
                            <select name="has_website" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                                <option value="">Any</option>
                                <option value="1" @selected(($filters['has_website'] ?? '') === '1')>Yes</option>
                                <option value="0" @selected(($filters['has_website'] ?? '') === '0')>No</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Collected between</label>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">From</label>
                                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                                       class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">To</label>
                                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                                       class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm">
                            </div>
                        </div>
                    </div>
                </form>

                <div class="flex gap-3 border-t border-gray-100 p-5">
                    <a href="{{ route('admin.maps-leads.dashboard') }}"
                       class="flex-1 rounded-lg border border-gray-200 px-4 py-2.5 text-center text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Reset</a>
                    <button type="submit" form="maps-lead-filters"
                            class="flex-1 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Apply Filters</button>
                </div>
            </div>
        </div>
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
