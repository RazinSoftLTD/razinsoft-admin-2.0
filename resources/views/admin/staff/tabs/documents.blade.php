@php $catTone = ['contract' => 'bg-indigo-50 text-indigo-700', 'nid' => 'bg-sky-50 text-sky-700', 'certificate' => 'bg-emerald-50 text-emerald-700', 'cv' => 'bg-amber-50 text-amber-700']; @endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-xl border border-gray-100 bg-white shadow-sm">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3.5">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Documents</h2>
            <span class="text-xs text-gray-400">{{ $documents->count() }} file(s)</span>
        </header>
        <div class="overflow-x-auto">
        <table class="w-full text-left text-sm" style="min-width:560px">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-5 py-3 font-semibold">Document</th>
                    <th class="px-5 py-3 font-semibold">Category</th>
                    <th class="px-5 py-3 font-semibold">Expires</th>
                    <th class="px-5 py-3 font-semibold">Uploaded</th>
                    <th class="px-5 py-3 text-right font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($documents as $doc)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <a href="{{ \App\Http\Resources\ProductResource::media($doc->path) }}" target="_blank" rel="noopener" class="font-semibold text-[var(--color-primary)] hover:underline">{{ $doc->title }}</a>
                            <p class="text-xs text-gray-400">{{ $doc->original_name }} · {{ $doc->sizeLabel() }}</p>
                        </td>
                        <td class="px-5 py-3"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $catTone[$doc->category] ?? 'bg-gray-100 text-gray-600' }}">{{ \App\Models\EmployeeDocument::CATEGORIES[$doc->category] ?? $doc->category }}</span></td>
                        <td class="px-5 py-3 {{ $doc->isExpired() ? 'font-semibold text-red-600' : 'text-[var(--color-muted)]' }}">
                            {{ $doc->expires_on?->format('d M Y') ?? '—' }}{{ $doc->isExpired() ? ' · expired' : '' }}
                        </td>
                        <td class="px-5 py-3 text-[var(--color-muted)]">{{ $doc->created_at?->format('d M Y') }}<br><span class="text-xs text-gray-400">{{ $doc->uploader?->name }}</span></td>
                        <td class="px-5 py-3 text-right">
                            @if ($canEdit)
                                <form method="POST" action="{{ route('admin.staff.documents.destroy', [$staff, $doc]) }}" onsubmit="return confirm('Remove “{{ $doc->title }}”?')">
                                    @csrf @method('DELETE')
                                    <button class="rounded p-1.5 text-red-400 hover:bg-red-50 hover:text-red-600" title="Remove">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    @include('admin.staff.tabs._empty', ['cols' => 5, 'icon' => 'M14 3v5h5M8 3h7l5 5v11a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z', 'title' => 'No documents uploaded', 'hint' => 'Contracts, NIDs and certificates can be attached here.'])
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    @if ($canEdit)
        <form method="POST" action="{{ route('admin.staff.documents.store', $staff) }}" enctype="multipart/form-data" class="space-y-3 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            @csrf
            <div class="flex items-center gap-2.5 border-b border-gray-100 pb-3">
                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                </span>
                <h3 class="text-sm font-bold text-[var(--color-heading)]">Upload document</h3>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Title <span class="text-red-500">*</span></label>
                <input name="title" required maxlength="150" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none" placeholder="e.g. Employment contract">
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Category</label>
                <select name="category" required class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    @foreach (\App\Models\EmployeeDocument::CATEGORIES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Issued on</label>
                    <input type="date" name="issued_on" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">Expires on</label>
                    <input type="date" name="expires_on" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-[var(--color-muted)]">File <span class="text-red-500">*</span></label>
                <input type="file" name="file" required class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
            </div>
            <button class="w-full rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Upload</button>
        </form>
    @endif
</div>
