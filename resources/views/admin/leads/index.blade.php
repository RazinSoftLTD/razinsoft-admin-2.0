@extends('admin.layouts.app')
@section('title', 'All Leads')

@section('content')
    @php $activeFilters = count(array_filter(request()->only(['search', 'status', 'source', 'assigned', 'priority', 'date_range', 'from', 'to', 'country']), fn ($v) => $v !== null && $v !== '')); @endphp
    <div x-data="{ filtersOpen: false }" @keydown.escape.window="filtersOpen = false">
        {{-- Header: title + all actions (Export · Import · Filters · Add Lead) on one line --}}
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <div>
                <h1 class="text-xl font-bold text-[var(--color-heading)]">All Leads</h1>
                <p class="mt-1 text-sm text-[var(--color-muted)]">CRM &rsaquo; Leads &rsaquo; All Leads</p>
            </div>

            {{-- Smart live search — inline between the title and the actions --}}
            <form method="GET" data-search-form class="relative order-last w-full min-w-[12rem] flex-1 lg:order-none lg:mx-2 lg:w-auto">
                @foreach (request()->except('search', 'page') as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3-3"/></svg>
                <input name="search" data-lead-search type="text" value="{{ request('search') }}" autocomplete="off"
                       placeholder="Search leads by name, email, phone, company, ID…"
                       class="h-11 w-full rounded-lg border border-gray-200 bg-white pl-11 pr-20 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                <span data-search-spin class="absolute right-14 top-1/2 hidden h-4 w-4 -translate-y-1/2 animate-spin rounded-full border-2 border-gray-200 border-t-[var(--color-primary)]"></span>
                <button type="button" data-search-clear class="{{ request('search') ? '' : 'hidden' }} absolute right-2.5 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-semibold text-[var(--color-muted)] hover:bg-gray-100">Clear</button>
            </form>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Export dropdown: CSV · Excel · PDF --}}
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                        Export
                        <svg class="h-3.5 w-3.5 transition" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-30 mt-1 w-36 overflow-hidden rounded-lg border border-gray-100 bg-white py-1 shadow-lg">
                        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'page' => null]) }}" class="block px-4 py-2 text-sm font-medium text-[var(--color-heading)] hover:bg-gray-50">CSV</a>
                        <a href="{{ request()->fullUrlWithQuery(['export' => 'excel', 'page' => null]) }}" class="block px-4 py-2 text-sm font-medium text-[var(--color-heading)] hover:bg-gray-50">Excel</a>
                        <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf', 'page' => null]) }}" class="block px-4 py-2 text-sm font-medium text-[var(--color-heading)] hover:bg-gray-50">PDF</a>
                    </div>
                </div>
                {{-- Import dropdown: CSV · Excel --}}
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 15V3m0 0 4 4m-4-4-4 4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                        Import
                        <svg class="h-3.5 w-3.5 transition" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-30 mt-1 w-40 overflow-hidden rounded-lg border border-gray-100 bg-white py-1 shadow-lg">
                        <a href="{{ route('admin.leads.import.form', ['format' => 'csv']) }}" class="block px-4 py-2 text-sm font-medium text-[var(--color-heading)] hover:bg-gray-50">CSV file</a>
                        <a href="{{ route('admin.leads.import.form', ['format' => 'excel']) }}" class="block px-4 py-2 text-sm font-medium text-[var(--color-heading)] hover:bg-gray-50">Excel file</a>
                    </div>
                </div>
                {{-- Filters (opens drawer) --}}
                <button type="button" @click="filtersOpen = true"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M6 12h12M9 18h6"/></svg>
                    Filters
                    @if ($activeFilters)<span class="grid h-5 min-w-5 place-items-center rounded-full bg-[var(--color-primary)] px-1.5 text-[11px] font-bold text-white">{{ $activeFilters }}</span>@endif
                </button>
                @if ($activeFilters)
                    <a href="{{ route('admin.leads.index') }}" title="Clear filters" class="text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">Clear</a>
                @endif
                @if (auth()->user()->allows('leads', 'create'))
                    <a href="{{ route('admin.leads.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Lead
                    </a>
                @endif
            </div>
        </div>

        @if (session('import_skipped') && count(session('import_skipped')))
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <p class="font-semibold">Skipped rows:</p>
                <ul class="mt-1 list-inside list-disc">@foreach (session('import_skipped') as $s)<li>{{ $s }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Drawer --}}
        <div x-show="filtersOpen" x-cloak class="fixed inset-0 z-40">
            <div x-show="filtersOpen" x-transition.opacity @click="filtersOpen = false" class="absolute inset-0 bg-black/30"></div>
            <div x-show="filtersOpen"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                 class="absolute right-0 top-0 flex h-full w-80 max-w-full flex-col bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Filter Leads</h2>
                    <button type="button" @click="filtersOpen = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-500 hover:bg-gray-100">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <form id="lead-filters" method="GET" class="flex-1 space-y-4 overflow-y-auto p-5">
                    {{-- Search lives in the top bar; carry it so applying filters keeps it. --}}
                    <input type="hidden" name="search" value="{{ request('search') }}">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Lead Quality</label>
                        <select name="status" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All Quality</option>
                            @foreach (\App\Models\Lead::STATUSES as $k => $label)
                                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Source</label>
                        <select name="source" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All Sources</option>
                            @foreach (\App\Models\Lead::sourceOptions() as $s)
                                <option value="{{ $s }}" @selected(request('source') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Assigned To</label>
                        <select name="assigned" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All Users</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected(request('assigned') == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Priority</label>
                        <select name="priority" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All Priority</option>
                            @foreach (\App\Models\Lead::PRIORITIES as $k => $label)
                                <option value="{{ $k }}" @selected(request('priority') === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Created-date filter --}}
                    <div class="border-t border-gray-100 pt-4">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Created Date</label>
                        <select name="date_range" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            @foreach (['' => 'All time', 'today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'] as $rk => $rl)
                                <option value="{{ $rk }}" @selected(request('date_range') === $rk)>{{ $rl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">Custom from</label>
                            <input type="date" name="from" value="{{ request('from') }}" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">Custom to</label>
                            <input type="date" name="to" value="{{ request('to') }}" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm">
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Country</label>
                        <select name="country" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All Countries</option>
                            @foreach ($countries as $c)
                                <option value="{{ $c }}" @selected(request('country') === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <div class="flex gap-2 border-t border-gray-100 px-5 py-4">
                    <a href="{{ route('admin.leads.index') }}" class="flex-1 rounded-lg border border-gray-200 px-4 py-2.5 text-center text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Reset</a>
                    <button type="submit" form="lead-filters" class="flex-1 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Apply Filters</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Results — swapped in place on live search (search box keeps focus) --}}
    <div id="leads-results">
        @include('admin.leads._results')
    </div>

    <script>
        (function () {
            const form = document.querySelector('[data-search-form]');
            if (!form || form.dataset.bound) return;
            form.dataset.bound = '1';
            const input = form.querySelector('[data-lead-search]');
            const clearBtn = form.querySelector('[data-search-clear]');
            const spin = form.querySelector('[data-search-spin]');
            const results = document.getElementById('leads-results');
            let timer, controller, seq = 0;

            function run() {
                const mine = ++seq;
                const q = input.value.trim();
                const url = new URL(window.location.href);
                if (q) url.searchParams.set('search', q); else url.searchParams.delete('search');
                url.searchParams.delete('page');
                if (controller) controller.abort();
                controller = new AbortController();
                spin.classList.remove('hidden');
                results.classList.add('opacity-50');
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal })
                    .then(r => r.text())
                    .then(html => { if (mine === seq) { results.innerHTML = html; history.replaceState({}, '', url); } })
                    .catch(() => {})
                    .finally(() => { if (mine === seq) { spin.classList.add('hidden'); results.classList.remove('opacity-50'); clearBtn.classList.toggle('hidden', !input.value.trim()); } });
            }
            input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 300); });
            form.addEventListener('submit', (e) => { e.preventDefault(); clearTimeout(timer); run(); });
            clearBtn.addEventListener('click', () => { input.value = ''; input.focus(); run(); });
        })();
    </script>

@endsection
