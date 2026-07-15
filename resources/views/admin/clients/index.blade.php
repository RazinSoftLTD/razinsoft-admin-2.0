@extends('admin.layouts.app')
@section('title', 'Clients')

@php
    $user = auth()->user();
    // Build a sort link for a column, toggling asc/desc and keeping other query params.
    $sortUrl = function ($col) use ($sort, $dir) {
        $next = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
        return request()->fullUrlWithQuery(['sort' => $col, 'dir' => $next, 'page' => 1]);
    };
    $arrow = fn ($col) => $sort === $col ? ($dir === 'asc' ? '↑' : '↓') : '⇅';
    // [label, text colour, dot colour] per status.
    $statusChip = fn ($s) => match ($s) {
        'active' => ['Active', 'text-emerald-600', 'bg-emerald-500'],
        'inactive' => ['Inactive', 'text-amber-600', 'bg-amber-400'],
        default => ['Blocked', 'text-red-600', 'bg-red-500'],
    };
@endphp

@section('content')
    <div x-data="{ showFilters: {{ collect($filters ?? [])->filter()->isNotEmpty() ? 'true' : 'false' }}, selCount: 0, recount() { this.selCount = document.querySelectorAll('.row-check:checked').length } }">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        {{-- Undo last import --}}
        @isset($lastImport)
            @if ($lastImport)
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm">
                    <span class="text-amber-800">
                        Last import added <strong>{{ $lastImport->count }}</strong> client(s) {{ $lastImport->created_at->diffForHumans() }}@if ($lastImport->importer) by {{ $lastImport->importer->name }}@endif.
                    </span>
                    <form method="POST" action="{{ route('admin.clients.import.undo') }}" onsubmit="return confirm('Undo the last import? This will delete the {{ $lastImport->count }} imported client(s) that have no orders/invoices/tickets yet.')">
                        @csrf
                        <button class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14 4 9l5-5M4 9h11a5 5 0 0 1 0 10h-1"/></svg>
                            Undo last import
                        </button>
                    </form>
                </div>
            @endif
        @endisset
        @if (session('import_skipped') && count(session('import_skipped')))
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                <p class="font-semibold">Some rows were skipped:</p>
                <ul class="mt-1 list-inside list-disc">@foreach (session('import_skipped') as $s)<li>{{ $s }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Top toolbar --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @if ($user->allows('clients', 'create'))
                    <a href="{{ route('admin.clients.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Client
                    </a>
                @endif

                {{-- Export dropdown: CSV · Excel · PDF --}}
                @if ($user->allows('clients', 'import_export'))
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg> Export
                        <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" x-cloak class="absolute z-20 mt-1 w-36 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'page' => null]) }}" class="block px-4 py-2 text-sm font-medium text-[var(--color-heading)] hover:bg-gray-50">CSV</a>
                        <a href="{{ request()->fullUrlWithQuery(['export' => 'excel', 'page' => null]) }}" class="block px-4 py-2 text-sm font-medium text-[var(--color-heading)] hover:bg-gray-50">Excel</a>
                        <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf', 'page' => null]) }}" class="block px-4 py-2 text-sm font-medium text-[var(--color-heading)] hover:bg-gray-50">PDF</a>
                    </div>
                </div>
                @endif

                {{-- Import dropdown: CSV · Excel --}}
                @if ($user->allows('clients', 'import_export'))
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15V3m0 0L8 7m4-4 4 4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg> Import
                            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div x-show="open" x-cloak class="absolute z-20 mt-1 w-36 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                            <a href="{{ route('admin.clients.import.form') }}" class="block px-4 py-2 text-sm font-medium text-[var(--color-heading)] hover:bg-gray-50">CSV file</a>
                            <a href="{{ route('admin.clients.import.form') }}" class="block px-4 py-2 text-sm font-medium text-[var(--color-heading)] hover:bg-gray-50">Excel file</a>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <form method="GET" class="flex items-center gap-2">
                    @foreach (request()->except(['search', 'page']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    <input name="search" value="{{ request('search') }}" placeholder="Search…" class="h-10 w-56 rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                </form>

                {{-- Filters toggle --}}
                @php $activeFilters = collect($filters ?? [])->filter()->count() + (request('sort') === 'top_paying' ? 1 : 0); @endphp
                <button type="button" @click="showFilters = !showFilters" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4Z"/></svg>
                    Filters @if ($activeFilters)<span class="grid h-5 min-w-[1.25rem] place-items-center rounded-full bg-[var(--color-primary)] px-1 text-xs font-bold text-white">{{ $activeFilters }}</span>@endif
                </button>

                {{-- List / grid view toggle --}}
                <div class="flex overflow-hidden rounded-lg border border-gray-200">
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'list']) }}" title="List view"
                       class="grid h-10 w-10 place-items-center {{ $view === 'list' ? 'bg-[var(--color-heading)] text-white' : 'bg-white text-gray-400 hover:bg-gray-50' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'grid']) }}" title="Grid view"
                       class="grid h-10 w-10 place-items-center border-l border-gray-200 {{ $view === 'grid' ? 'bg-[var(--color-heading)] text-white' : 'bg-white text-gray-400 hover:bg-gray-50' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>
                    </a>
                </div>
            </div>
        </div>

        {{-- ===== Filter drawer (slides in from the right) ===== --}}
        <div x-show="showFilters" x-cloak class="fixed inset-0 z-50">
            {{-- backdrop --}}
            <div x-show="showFilters" x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 @click="showFilters = false" class="absolute inset-0 bg-black/40"></div>
            {{-- panel --}}
            <aside x-show="showFilters" x-transition:enter="transform transition ease-out duration-250" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                   x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                   class="absolute inset-y-0 right-0 w-full max-w-sm bg-white shadow-2xl">
                <form method="GET" class="flex h-full flex-col">
                    @if (request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                    @if (request('view'))<input type="hidden" name="view" value="{{ request('view') }}">@endif

                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <h2 class="text-base font-bold text-[var(--color-heading)]">Filters</h2>
                        <button type="button" @click="showFilters = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </div>

                    <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Status</label>
                            <select name="status" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                                <option value="">All</option>
                                @foreach ($statuses as $sv => $sl)<option value="{{ $sv }}" @selected(request('status') === $sv)>{{ $sl }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Client Label</label>
                            <select name="label" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                                <option value="">All</option>
                                @foreach ($clientLabels as $lbl)<option value="{{ $lbl->name }}" @selected(request('label') === $lbl->name)>{{ $lbl->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Category</label>
                            <select name="category" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                                <option value="">All</option>
                                @foreach ($filterCategories as $cat)<option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $cat }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Sub Category</label>
                            <select name="sub_category" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                                <option value="">All</option>
                                @foreach ($filterSubCategories as $sc)<option value="{{ $sc }}" @selected(request('sub_category') === $sc)>{{ $sc }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Country</label>
                            <select name="country" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                                <option value="">All</option>
                                @foreach ($filterCountries as $co)<option value="{{ $co }}" @selected(request('country') === $co)>{{ $co }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Added</label>
                            <select name="date_range" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                                <option value="">Any time</option>
                                @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'year' => 'This year'] as $dv => $dl)
                                    <option value="{{ $dv }}" @selected(request('date_range') === $dv)>{{ $dl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">From</label>
                                <input type="date" name="from" value="{{ request('from') }}" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">To</label>
                                <input type="date" name="to" value="{{ request('to') }}" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Sort by</label>
                            <select name="sort" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                                <option value="">Newest first</option>
                                <option value="top_paying" @selected(request('sort') === 'top_paying')>Top paying (highest spend)</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 border-t border-gray-100 px-5 py-4">
                        <a href="{{ route('admin.clients.index') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Clear</a>
                        <button type="submit" class="rounded-lg bg-[var(--color-primary)] px-6 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Apply filters</button>
                    </div>
                </form>
            </aside>
        </div>

        {{-- ===== Bulk action bar (appears when rows are checked) ===== --}}
        @if ($user->allows('clients', 'delete'))
            <div x-show="selCount > 0" x-cloak class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-[var(--color-primary)]/30 bg-[var(--color-primary-soft)] px-4 py-3 text-sm">
                <span class="font-semibold text-[var(--color-primary)]"><span x-text="selCount"></span> client(s) selected</span>
                <button type="button"
                        @click="const ids=[...document.querySelectorAll('.row-check:checked')].map(c=>c.value); if(!ids.length)return; if(!confirm('Delete '+ids.length+' selected client(s)? This cannot be undone.'))return; const box=document.getElementById('bulk-ids'); box.innerHTML=''; ids.forEach(id=>{const i=document.createElement('input');i.type='hidden';i.name='ids[]';i.value=id;box.appendChild(i);}); document.getElementById('bulk-delete-form').submit();"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-4 py-2 text-xs font-semibold text-white hover:bg-red-700">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                    Delete selected
                </button>
            </div>
            <form id="bulk-delete-form" method="POST" action="{{ route('admin.clients.bulk-destroy') }}" class="hidden">
                @csrf @method('DELETE')
                <div id="bulk-ids"></div>
            </form>
        @endif

        @if ($view === 'list')
        {{-- Table --}}
        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="w-10 px-5 py-3"><input type="checkbox" x-on:change="$root.querySelectorAll('.row-check').forEach(c => c.checked = $event.target.checked); recount()" class="h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]"></th>
                            <th class="px-5 py-3"><a href="{{ $sortUrl('id') }}" class="inline-flex items-center gap-1 hover:text-[var(--color-heading)]">Id <span class="text-[10px]">{{ $arrow('id') }}</span></a></th>
                            <th class="px-5 py-3"><a href="{{ $sortUrl('name') }}" class="inline-flex items-center gap-1 hover:text-[var(--color-heading)]">Name <span class="text-[10px]">{{ $arrow('name') }}</span></a></th>
                            <th class="px-5 py-3"><a href="{{ $sortUrl('email') }}" class="inline-flex items-center gap-1 hover:text-[var(--color-heading)]">Email <span class="text-[10px]">{{ $arrow('email') }}</span></a></th>
                            <th class="px-5 py-3"><a href="{{ $sortUrl('phone') }}" class="inline-flex items-center gap-1 hover:text-[var(--color-heading)]">Mobile <span class="text-[10px]">{{ $arrow('phone') }}</span></a></th>
                            <th class="px-5 py-3"><a href="{{ $sortUrl('status') }}" class="inline-flex items-center gap-1 hover:text-[var(--color-heading)]">Status <span class="text-[10px]">{{ $arrow('status') }}</span></a></th>
                            <th class="px-5 py-3">Label</th>
                            @if (request('sort') === 'top_paying')<th class="px-5 py-3 text-right">Paid</th>@endif
                            <th class="px-5 py-3"><a href="{{ $sortUrl('created_at') }}" class="inline-flex items-center gap-1 hover:text-[var(--color-heading)]">Created <span class="text-[10px]">{{ $arrow('created_at') }}</span></a></th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($clients as $c)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3"><input type="checkbox" value="{{ $c->id }}" x-on:change="recount()" class="row-check h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]"></td>
                                <td class="px-5 py-3 font-semibold text-[var(--color-heading)]">{{ $c->id }}</td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.clients.show', $c) }}" class="flex items-center gap-3 hover:opacity-80">
                                        @if ($c->photo)
                                            <img src="{{ asset('storage/'.$c->photo) }}" alt="" class="h-9 w-9 shrink-0 rounded-full border border-gray-200 object-cover">
                                        @else
                                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--color-primary-soft)] text-xs font-bold text-[var(--color-primary)]">{{ strtoupper(substr($c->name, 0, 1)) }}</span>
                                        @endif
                                        <span class="leading-tight">
                                            <span class="block font-semibold text-[var(--color-heading)] hover:text-[var(--color-primary)]">{{ $c->name }}</span>
                                            @if ($c->company)<span class="block text-xs text-[var(--color-muted)]">{{ $c->company }}</span>@endif
                                        </span>
                                    </a>
                                </td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $c->email }}</td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ trim($c->dial_code.' '.$c->phone) ?: '--' }}</td>
                                <td class="px-5 py-3">
                                    @php [$slbl, $stc, $sdc] = $statusChip($c->status); @endphp
                                    <span class="inline-flex items-center gap-1.5 text-sm font-medium {{ $stc }}"><span class="h-2 w-2 rounded-full {{ $sdc }}"></span> {{ $slbl }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($c->client_label)
                                        @php $lbl = $clientLabels->firstWhere('name', $c->client_label); @endphp
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold {{ $lbl?->badgeClass() ?? 'bg-gray-100 text-gray-600' }}">{{ $c->client_label }}</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                @if (request('sort') === 'top_paying')
                                    <td class="px-5 py-3 text-right font-semibold text-[var(--color-heading)]">{{ number_format((float) ($c->total_paid ?? 0), 2) }}</td>
                                @endif
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $c->created_at?->format('d F, Y') }}</td>
                                <td class="px-5 py-3">
                                    <div class="relative flex justify-end" x-data="{ open: false }">
                                        <button @click="open = !open" @click.outside="open = false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="Actions">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                                        </button>
                                        <div x-show="open" x-cloak class="absolute right-0 top-full z-20 mt-1 w-36 rounded-lg border border-gray-100 bg-white py-1 text-sm shadow-lg">
                                            <a href="{{ route('admin.clients.show', $c) }}" class="block px-4 py-2 text-[var(--color-heading)] hover:bg-gray-50">View</a>
                                            @if ($user->allows('clients', 'edit'))
                                                <a href="{{ route('admin.clients.edit', $c) }}" class="block px-4 py-2 text-[var(--color-heading)] hover:bg-gray-50">Edit</a>
                                                <div class="my-1 border-t border-gray-100"></div>
                                                @foreach (\App\Models\User::STATUSES as $sv => $sl)
                                                    @if ($sv !== $c->status)
                                                        <form method="POST" action="{{ route('admin.clients.status', $c) }}">
                                                            @csrf @method('PATCH')
                                                            <input type="hidden" name="status" value="{{ $sv }}">
                                                            <button class="block w-full px-4 py-2 text-left text-[var(--color-heading)] hover:bg-gray-50">Mark {{ $sl }}</button>
                                                        </form>
                                                    @endif
                                                @endforeach
                                                <div class="my-1 border-t border-gray-100"></div>
                                            @endif
                                            @if ($user->allows('clients', 'delete'))
                                                <form method="POST" action="{{ route('admin.clients.destroy', $c) }}" onsubmit="return confirm('Delete this client?')">
                                                    @csrf @method('DELETE')
                                                    <button class="block w-full px-4 py-2 text-left text-red-600 hover:bg-red-50">Delete</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ request('sort') === 'top_paying' ? 10 : 9 }}" class="px-5 py-12 text-center text-gray-400">No clients found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @else
        {{-- Grid --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse ($clients as $c)
                <div class="relative rounded-xl border border-gray-100 bg-white p-5 shadow-sm hover:shadow-md">
                    <div class="absolute right-3 top-3" x-data="{ open: false }">
                        <button @click="open = !open" @click.outside="open = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="Actions">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                        </button>
                        <div x-show="open" x-cloak class="absolute right-0 top-full z-20 mt-1 w-36 rounded-lg border border-gray-100 bg-white py-1 text-sm shadow-lg">
                            <a href="{{ route('admin.clients.show', $c) }}" class="block px-4 py-2 text-[var(--color-heading)] hover:bg-gray-50">View</a>
                            @if ($user->allows('clients', 'edit'))
                                <a href="{{ route('admin.clients.edit', $c) }}" class="block px-4 py-2 text-[var(--color-heading)] hover:bg-gray-50">Edit</a>
                                <div class="my-1 border-t border-gray-100"></div>
                                @foreach (\App\Models\User::STATUSES as $sv => $sl)
                                    @if ($sv !== $c->status)
                                        <form method="POST" action="{{ route('admin.clients.status', $c) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $sv }}">
                                            <button class="block w-full px-4 py-2 text-left text-[var(--color-heading)] hover:bg-gray-50">Mark {{ $sl }}</button>
                                        </form>
                                    @endif
                                @endforeach
                                <div class="my-1 border-t border-gray-100"></div>
                            @endif
                            @if ($user->allows('clients', 'delete'))
                                <form method="POST" action="{{ route('admin.clients.destroy', $c) }}" onsubmit="return confirm('Delete this client?')">
                                    @csrf @method('DELETE')
                                    <button class="block w-full px-4 py-2 text-left text-red-600 hover:bg-red-50">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <a href="{{ route('admin.clients.show', $c) }}" class="flex flex-col items-center text-center">
                        @if ($c->photo)
                            <img src="{{ asset('storage/'.$c->photo) }}" alt="" class="h-16 w-16 rounded-full border border-gray-200 object-cover">
                        @else
                            <span class="grid h-16 w-16 place-items-center rounded-full bg-[var(--color-primary-soft)] text-lg font-bold text-[var(--color-primary)]">{{ strtoupper(substr($c->name, 0, 1)) }}</span>
                        @endif
                        <span class="mt-3 font-semibold text-[var(--color-heading)]">{{ $c->name }}</span>
                        @if ($c->company)<span class="text-xs text-[var(--color-muted)]">{{ $c->company }}</span>@endif
                    </a>

                    <div class="mt-4 space-y-1.5 border-t border-gray-100 pt-4 text-sm">
                        <p class="truncate text-[var(--color-muted)]" title="{{ $c->email }}">{{ $c->email }}</p>
                        <p class="text-[var(--color-muted)]">{{ trim($c->dial_code.' '.$c->phone) ?: '--' }}</p>
                        <p class="pt-1">
                            @php [$slbl, $stc, $sdc] = $statusChip($c->status); @endphp
                            <span class="inline-flex items-center gap-1.5 text-sm font-medium {{ $stc }}"><span class="h-2 w-2 rounded-full {{ $sdc }}"></span> {{ $slbl }}</span>
                        </p>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-xl border border-gray-100 bg-white px-5 py-12 text-center text-gray-400">No clients found.</div>
            @endforelse
        </div>
        @endif

        {{-- Footer: row count · per-page · compact pagination (same as CRM Leads) --}}
        <div class="mt-4 flex flex-col items-center justify-between gap-3 sm:flex-row">
            <div class="flex items-center gap-4 text-sm text-[var(--color-muted)]">
                <span>Showing <span class="font-semibold text-[var(--color-heading)]">{{ $clients->count() ? $clients->firstItem() : 0 }}</span>–<span class="font-semibold text-[var(--color-heading)]">{{ $clients->lastItem() ?? 0 }}</span> of <span class="font-semibold text-[var(--color-heading)]">{{ $clients->total() }}</span></span>
                <form method="GET" class="flex items-center gap-2">
                    @foreach (request()->except('per_page', 'page') as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    <label class="hidden sm:inline">Show</label>
                    <select name="per_page" onchange="this.form.submit()" class="h-9 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                        @foreach ([10, 25, 50, 100, 250, 500, 1000] as $n)<option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>@endforeach
                    </select>
                </form>
            </div>
            <div>{{ $clients->links('admin.partials._pagination') }}</div>
        </div>

    </div>
@endsection
