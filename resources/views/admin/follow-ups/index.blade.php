@extends('admin.layouts.app')
@section('title', 'Follow-ups')

@section('content')
    @php
        $activeFilters = count(array_filter(request()->only(['assigned', 'type', 'source', 'status']), fn ($v) => $v !== null && $v !== ''));
        $cardDefs = [
            ['view' => 'today', 'label' => "Today's Follow-ups", 'count' => $cards['today'], 'accent' => 'text-orange-600', 'ring' => 'bg-orange-50 text-orange-600', 'icon' => 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z'],
            ['view' => 'upcoming', 'label' => 'Upcoming', 'count' => $cards['upcoming'], 'accent' => 'text-blue-600', 'ring' => 'bg-blue-50 text-blue-600', 'icon' => 'M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
            ['view' => 'overdue', 'label' => 'Overdue', 'count' => $cards['overdue'], 'accent' => 'text-red-600', 'ring' => 'bg-red-50 text-red-600', 'icon' => 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z'],
            ['view' => 'completed', 'label' => 'Completed Today', 'count' => $cards['completed_today'], 'accent' => 'text-emerald-600', 'ring' => 'bg-emerald-50 text-emerald-600', 'icon' => 'm5 13 4 4L19 7'],
            ['view' => 'all', 'label' => 'Total Pending', 'count' => $cards['pending'], 'accent' => 'text-indigo-600', 'ring' => 'bg-indigo-50 text-indigo-600', 'icon' => 'M9 5h10M9 12h10M9 19h10M5 5h.01M5 12h.01M5 19h.01'],
        ];
        $tabs = ['all' => 'All', 'today' => 'Today', 'tomorrow' => 'Tomorrow', 'week' => 'This Week', 'upcoming' => 'Upcoming', 'overdue' => 'Overdue', 'completed' => 'Completed'];
    @endphp

    <div x-data="{ filtersOpen: false }" @keydown.escape.window="filtersOpen = false">
        {{-- Header --}}
        <div class="mb-5 flex flex-wrap items-center gap-3">
            <div>
                <h1 class="text-xl font-bold text-[var(--color-heading)]">Follow-ups</h1>
                <p class="mt-1 text-sm text-[var(--color-muted)]">CRM &rsaquo; Follow-ups</p>
            </div>

            <form method="GET" data-search-form class="relative order-last w-full min-w-[12rem] flex-1 lg:order-none lg:mx-2 lg:w-auto">
                @foreach (request()->except('search', 'page') as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3-3"/></svg>
                <input name="search" data-fu-search type="text" value="{{ request('search') }}" autocomplete="off"
                       placeholder="Search by lead, company, phone, ID…"
                       class="h-11 w-full rounded-lg border border-gray-200 bg-white pl-11 pr-20 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                <span data-search-spin class="absolute right-14 top-1/2 hidden h-4 w-4 -translate-y-1/2 animate-spin rounded-full border-2 border-gray-200 border-t-[var(--color-primary)]"></span>
                <button type="button" data-search-clear class="{{ request('search') ? '' : 'hidden' }} absolute right-2.5 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-semibold text-[var(--color-muted)] hover:bg-gray-100">Clear</button>
            </form>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.follow-ups.calendar') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg>
                    Calendar
                </a>
                <button type="button" @click="filtersOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M6 12h12M9 18h6"/></svg>
                    Filters
                    @if ($activeFilters)<span class="grid h-5 min-w-5 place-items-center rounded-full bg-[var(--color-primary)] px-1.5 text-[11px] font-bold text-white">{{ $activeFilters }}</span>@endif
                </button>
                @if ($activeFilters)
                    <a href="{{ route('admin.follow-ups.index', ['view' => $view]) }}" class="text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">Clear</a>
                @endif
            </div>
        </div>

        {{-- Dashboard summary cards --}}
        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($cardDefs as $c)
                <a href="{{ route('admin.follow-ups.index', ['view' => $c['view']]) }}"
                   class="group rounded-xl border bg-white p-4 shadow-sm transition hover:shadow-md {{ $view === $c['view'] ? 'border-[var(--color-primary)] ring-1 ring-[var(--color-primary)]' : 'border-gray-100' }}">
                    <div class="flex items-center justify-between">
                        <span class="grid h-9 w-9 place-items-center rounded-lg {{ $c['ring'] }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $c['icon'] }}"/></svg>
                        </span>
                        <span class="text-2xl font-extrabold {{ $c['accent'] }}">{{ $c['count'] }}</span>
                    </div>
                    <p class="mt-2 text-sm font-semibold text-[var(--color-heading)]">{{ $c['label'] }}</p>
                </a>
            @endforeach
        </div>

        {{-- Quick-view tabs --}}
        <div class="mb-4 flex flex-wrap gap-1.5">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.follow-ups.index', array_merge(request()->except('view', 'page'), ['view' => $key])) }}"
                   class="rounded-lg px-3.5 py-1.5 text-sm font-semibold transition {{ $view === $key ? 'bg-[var(--color-primary)] text-white' : 'bg-white text-[var(--color-muted)] ring-1 ring-gray-200 hover:bg-gray-50' }}">{{ $label }}</a>
            @endforeach
        </div>

        {{-- Filters drawer --}}
        <div x-show="filtersOpen" x-cloak class="fixed inset-0 z-40">
            <div x-show="filtersOpen" x-transition.opacity @click="filtersOpen = false" class="absolute inset-0 bg-black/30"></div>
            <div x-show="filtersOpen"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                 class="absolute right-0 top-0 flex h-full w-80 max-w-full flex-col bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Filter Follow-ups</h2>
                    <button type="button" @click="filtersOpen = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-500 hover:bg-gray-100">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
                <form id="fu-filters" method="GET" class="flex-1 space-y-4 overflow-y-auto p-5">
                    <input type="hidden" name="view" value="{{ $view }}">
                    <input type="hidden" name="search" value="{{ request('search') }}">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Assigned User</label>
                        <select name="assigned" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All Users</option>
                            @foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('assigned') == $u->id)>{{ $u->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Follow-up Type</label>
                        <select name="type" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All Types</option>
                            @foreach (\App\Models\LeadFollowUp::TYPES as $k => $label)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Lead Source</label>
                        <select name="source" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All Sources</option>
                            @foreach ($sources as $s)<option value="{{ $s }}" @selected(request('source') === $s)>{{ $s }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Status</label>
                        <select name="status" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                            <option value="">All Statuses</option>
                            @foreach (\App\Models\LeadFollowUp::STATUSES as $k => $label)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                </form>
                <div class="flex gap-2 border-t border-gray-100 px-5 py-4">
                    <a href="{{ route('admin.follow-ups.index', ['view' => $view]) }}" class="flex-1 rounded-lg border border-gray-200 px-4 py-2.5 text-center text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Reset</a>
                    <button type="submit" form="fu-filters" class="flex-1 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Apply</button>
                </div>
            </div>
        </div>
    </div>

    <div id="fu-results">
        @include('admin.follow-ups._results')
    </div>

    {{-- Mark Done (+ schedule next) modal — shared, opened via the `open-done` window event. --}}
    @include('admin.follow-ups._done-modal')

    <script>
        (function () {
            const form = document.querySelector('[data-search-form]');
            if (!form || form.dataset.bound) return;
            form.dataset.bound = '1';
            const input = form.querySelector('[data-fu-search]');
            const clearBtn = form.querySelector('[data-search-clear]');
            const spin = form.querySelector('[data-search-spin]');
            const results = document.getElementById('fu-results');
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
