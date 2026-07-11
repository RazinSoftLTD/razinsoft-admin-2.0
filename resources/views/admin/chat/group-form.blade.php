@extends('admin.layouts.app')
@section('title', 'New Group')

@section('content')
    <a href="{{ route('admin.chat.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Team Chat
    </a>

    <form method="POST" action="{{ route('admin.chat.groups.store') }}" class="max-w-2xl">
        @csrf
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h1 class="text-base font-bold text-[var(--color-heading)]">Create a channel</h1>
            <p class="mb-5 text-sm text-[var(--color-muted)]">Group teammates into a shared conversation. You'll be added as the manager.</p>

            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Channel name <span class="text-red-500">*</span></label>
                    <input name="name" value="{{ old('name') }}" required maxlength="120" placeholder="e.g. Development Team"
                           class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Description</label>
                    <input name="description" value="{{ old('description') }}" maxlength="255" placeholder="Optional — what's this channel about?"
                           class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                </div>

                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <label class="text-sm font-medium text-[var(--color-heading)]">Members</label>
                        <input type="text" data-member-search placeholder="Search…" class="h-8 w-40 rounded-lg border border-gray-200 px-2.5 text-xs">
                    </div>
                    <div class="max-h-72 space-y-1 overflow-y-auto rounded-lg border border-gray-100 p-2">
                        @forelse ($people as $p)
                            <label data-member-row="{{ strtolower($p->name) }}" class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-2 hover:bg-gray-50">
                                <input type="checkbox" name="members[]" value="{{ $p->id }}" @checked(in_array($p->id, old('members', [])))
                                       class="h-4 w-4 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                                @if ($p->photo_url)
                                    <img src="{{ $p->photo_url }}" class="h-8 w-8 rounded-full object-cover" alt="">
                                @else
                                    <span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--color-primary-soft)] text-xs font-bold text-[var(--color-primary)]">{{ strtoupper(substr($p->name, 0, 1)) }}</span>
                                @endif
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-[var(--color-heading)]">{{ $p->name }}</span>
                                    <span class="block truncate text-xs text-[var(--color-muted)]">{{ $p->designation->name ?? 'Team member' }}</span>
                                </span>
                            </label>
                        @empty
                            <p class="px-2 py-3 text-sm text-[var(--color-muted)]">No teammates available.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            @if ($errors->any())
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
        </div>

        <div class="mt-5 flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Create channel</button>
            <a href="{{ route('admin.chat.index') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>

    <script>
        (function () {
            const s = document.querySelector('[data-member-search]');
            if (s) s.addEventListener('input', function () {
                const q = this.value.trim().toLowerCase();
                document.querySelectorAll('[data-member-row]').forEach(function (row) {
                    row.style.display = row.dataset.memberRow.includes(q) ? '' : 'none';
                });
            });
        })();
    </script>
@endsection
