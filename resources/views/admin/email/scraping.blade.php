@extends('admin.layouts.app')
@section('title', 'Email Scraping')

@section('content')
<x-admin.email-shell>

    {{-- Start a crawl --}}
    <div class="mb-6 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-bold text-[var(--color-heading)]">Collect addresses from a website</h2>
        <p class="mt-1 text-xs text-[var(--color-muted)]">
            Enter a company's website. The crawler stays on that site, looks at its contact, about and team
            pages first, and records the addresses published there. It reads served HTML, so a site that
            renders entirely in JavaScript may come back empty.
        </p>

        <form method="POST" action="{{ route('admin.email.scraping.store') }}" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-0 flex-1">
                <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">Website</label>
                <input type="text" name="url" required placeholder="example.com"
                       class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-medium text-[var(--color-muted)]">Pages to crawl</label>
                <input type="number" name="max_pages" value="25" min="1" max="100"
                       class="h-11 w-24 rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
            </div>
            <button class="h-11 rounded-lg bg-[var(--color-primary)] px-5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                Start crawl
            </button>
        </form>
    </div>

    {{-- Recent runs --}}
    @if ($runs->count())
        <div class="mb-6 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Recent crawls</h2>
                <p class="text-xs text-[var(--color-muted)]">A crawl runs in the background — refresh to see it finish.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Site</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Pages</th>
                            <th class="px-5 py-3 text-right font-semibold">Found</th>
                            <th class="px-5 py-3 text-right font-semibold">New</th>
                            <th class="px-5 py-3 font-semibold">Started</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($runs as $run)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <span class="font-medium text-[var(--color-heading)]">{{ $run->domain }}</span>
                                    @if ($run->error)
                                        <span class="block text-xs text-red-500">{{ $run->error }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @php $tone = ['done' => 'bg-emerald-50 text-emerald-600', 'failed' => 'bg-red-50 text-red-600', 'running' => 'bg-sky-50 text-sky-600'][$run->status] ?? 'bg-gray-100 text-gray-500'; @endphp
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $tone }}">{{ ucfirst($run->status) }}</span>
                                </td>
                                <td class="px-5 py-3 text-right text-[var(--color-muted)]">{{ $run->pages_crawled }}</td>
                                <td class="px-5 py-3 text-right text-[var(--color-muted)]">{{ $run->emails_found }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-[var(--color-heading)]">{{ $run->emails_new }}</td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ optional($run->created_at)->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Stats --}}
    <div class="mb-6 flex flex-wrap gap-4">
        @foreach ([['Addresses', $total, 'bg-[var(--color-primary-soft)] text-[var(--color-primary)]'],
                   ['People (not role)', $people, 'bg-emerald-50 text-emerald-600'],
                   ['Imported as clients', $imported, 'bg-sky-50 text-sky-600']] as [$label, $value, $tint])
            <div class="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg {{ $tint }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z"/></svg>
                </span>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $label }}</p>
                    <p class="text-lg font-bold text-[var(--color-heading)]">{{ number_format($value) }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Collected addresses --}}
    <form method="POST" action="{{ route('admin.email.scraping.import') }}"
          x-data="{ picked: [], label: '' }"
          @submit="if (!picked.length) { $event.preventDefault(); alert('Select at least one address.'); }">
        @csrf

        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-gray-100 px-6 py-4">
                <div>
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Collected addresses</h2>
                    <p class="text-xs text-[var(--color-muted)]">
                        Import the ones worth keeping into the client book under a label — campaigns aim by label,
                        so the label is the mailing list.
                    </p>
                </div>

                <div class="flex flex-wrap items-end gap-2">
                    <input type="text" name="label" x-model="label" placeholder="Label, e.g. Prospects — Aug"
                           class="h-10 rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    <button type="submit"
                            class="h-10 rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                        Add <span x-text="picked.length"></span> to clients
                    </button>
                    <a href="{{ route('admin.email.scraping.export', request()->only(['domain', 'kind'])) }}"
                       class="h-10 rounded-lg border border-gray-200 px-4 text-sm font-semibold leading-10 text-[var(--color-muted)] hover:bg-gray-50">
                        Export CSV
                    </a>
                </div>
            </div>

            {{-- Filters --}}
            <div class="border-b border-gray-100 px-6 py-3">
                <form method="GET" class="flex flex-wrap items-end gap-2">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search address, name or site"
                           class="h-9 w-56 rounded-lg border border-gray-200 px-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    <select name="domain" class="h-9 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                        <option value="">Every site</option>
                        @foreach ($domains as $d)
                            <option value="{{ $d }}" @selected(request('domain') === $d)>{{ $d }}</option>
                        @endforeach
                    </select>
                    <select name="kind" class="h-9 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                        <option value="">People and role</option>
                        <option value="people" @selected(request('kind') === 'people')>People only</option>
                        <option value="role" @selected(request('kind') === 'role')>Role only (info@, sales@…)</option>
                    </select>
                    <select name="imported" class="h-9 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                        <option value="">Imported or not</option>
                        <option value="no" @selected(request('imported') === 'no')>Not imported</option>
                        <option value="yes" @selected(request('imported') === 'yes')>Imported</option>
                    </select>
                    <button class="h-9 rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Filter</button>
                    <a href="{{ route('admin.email.scraping') }}" class="h-9 rounded-lg border border-gray-200 px-4 text-sm font-semibold leading-9 text-[var(--color-muted)] hover:bg-gray-50">Clear</a>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">
                                <input type="checkbox" class="h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]"
                                       @change="picked = $event.target.checked ? [...$root.querySelectorAll('[data-pick]')].filter(c => !c.disabled).map(c => c.value) : []">
                            </th>
                            <th class="px-5 py-3 font-semibold">Address</th>
                            <th class="px-5 py-3 font-semibold">Site</th>
                            <th class="px-5 py-3 font-semibold">Type</th>
                            <th class="px-5 py-3 font-semibold">Found on</th>
                            <th class="px-5 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($emails as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <input type="checkbox" name="ids[]" data-pick value="{{ $row->id }}" x-model="picked"
                                           @disabled($row->imported_client_id)
                                           class="h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)] disabled:opacity-40">
                                </td>
                                <td class="px-5 py-3">
                                    <span class="block font-medium text-[var(--color-heading)]">{{ $row->email }}</span>
                                    @if ($row->name)
                                        <span class="block text-xs text-[var(--color-muted)]">{{ $row->name }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $row->domain ?: '—' }}</td>
                                <td class="px-5 py-3">
                                    @if ($row->is_role_address)
                                        <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500">Role</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600">Person</span>
                                    @endif
                                    @if ($row->imported_client_id)
                                        <span class="ml-1 inline-flex rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-600">Imported</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($row->source_url)
                                        <a href="{{ $row->source_url }}" target="_blank" rel="noopener"
                                           class="block max-w-xs truncate font-mono text-xs text-[var(--color-primary)] hover:underline">{{ $row->source_url }}</a>
                                    @else
                                        <span class="text-[var(--color-muted)]">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <button type="button"
                                            @click="if (confirm('Remove {{ $row->email }}?')) { document.getElementById('del-{{ $row->id }}').submit(); }"
                                            class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Remove">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">Nothing collected yet — start a crawl above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $emails->links() }}</div>
    </form>

    {{-- Delete forms live outside the import form: a form inside a form does not submit. --}}
    @foreach ($emails as $row)
        <form id="del-{{ $row->id }}" method="POST" action="{{ route('admin.email.scraping.destroy', $row) }}" class="hidden">
            @csrf @method('DELETE')
        </form>
    @endforeach

</x-admin.email-shell>
@endsection
