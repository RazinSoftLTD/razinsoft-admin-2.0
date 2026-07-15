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
    <div>
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
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

                {{-- Import dropdown: CSV · Excel --}}
                @if ($user->allows('clients', 'create'))
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

        @if ($view === 'list')
        {{-- Table --}}
        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="w-10 px-5 py-3"><input type="checkbox" x-on:change="$root.querySelectorAll('.row-check').forEach(c => c.checked = $event.target.checked)" class="h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]"></th>
                            <th class="px-5 py-3"><a href="{{ $sortUrl('id') }}" class="inline-flex items-center gap-1 hover:text-[var(--color-heading)]">Id <span class="text-[10px]">{{ $arrow('id') }}</span></a></th>
                            <th class="px-5 py-3"><a href="{{ $sortUrl('name') }}" class="inline-flex items-center gap-1 hover:text-[var(--color-heading)]">Name <span class="text-[10px]">{{ $arrow('name') }}</span></a></th>
                            <th class="px-5 py-3"><a href="{{ $sortUrl('email') }}" class="inline-flex items-center gap-1 hover:text-[var(--color-heading)]">Email <span class="text-[10px]">{{ $arrow('email') }}</span></a></th>
                            <th class="px-5 py-3"><a href="{{ $sortUrl('phone') }}" class="inline-flex items-center gap-1 hover:text-[var(--color-heading)]">Mobile <span class="text-[10px]">{{ $arrow('phone') }}</span></a></th>
                            <th class="px-5 py-3"><a href="{{ $sortUrl('status') }}" class="inline-flex items-center gap-1 hover:text-[var(--color-heading)]">Status <span class="text-[10px]">{{ $arrow('status') }}</span></a></th>
                            <th class="px-5 py-3"><a href="{{ $sortUrl('created_at') }}" class="inline-flex items-center gap-1 hover:text-[var(--color-heading)]">Created <span class="text-[10px]">{{ $arrow('created_at') }}</span></a></th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($clients as $c)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3"><input type="checkbox" value="{{ $c->id }}" class="row-check h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]"></td>
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
                            <tr><td colspan="8" class="px-5 py-12 text-center text-gray-400">No clients found.</td></tr>
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
                        @foreach ([10, 25, 50, 100] as $n)<option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>@endforeach
                    </select>
                </form>
            </div>
            <div>{{ $clients->links('admin.partials._pagination') }}</div>
        </div>

    </div>
@endsection
