@extends('admin.layouts.app')
@section('title', 'Channel Settings')

@php
    $me = auth()->user();
    $memberIds = $conversation->members->pluck('id')->all();
    $managerIds = $conversation->members->where('pivot.is_manager', true)->pluck('id')->all();
@endphp

@section('content')
    <a href="{{ route('admin.chat.show', $conversation) }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to channel
    </a>

    <form method="POST" action="{{ route('admin.chat.groups.update', $conversation) }}" enctype="multipart/form-data" class="max-w-2xl">
        @csrf

        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-base font-bold text-[var(--color-heading)]">Channel settings</h1>
                    <p class="text-sm text-[var(--color-muted)]">Created {{ $conversation->created_at->format('d M, Y') }} · {{ $conversation->created_at->diffForHumans() }}</p>
                </div>
                <span class="rounded-full bg-[var(--color-primary-soft)] px-3 py-1 text-xs font-semibold text-[var(--color-primary)]">{{ $conversation->members->count() }} members</span>
            </div>

            {{-- Photo --}}
            <div class="mt-5 flex items-center gap-4">
                <div id="avatar-preview" class="grid h-20 w-20 shrink-0 place-items-center overflow-hidden rounded-2xl bg-gray-100 text-gray-400">
                    @if ($conversation->photo_url)
                        <img src="{{ $conversation->photo_url }}" class="h-full w-full object-cover" alt="">
                    @else
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 9h12M6 15h12M9 4 7 20M17 4l-2 16"/></svg>
                    @endif
                </div>
                <div>
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4-4a3 3 0 0 1 4 0l4 4M14 14l1-1a3 3 0 0 1 4 0l1 1M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z"/></svg>
                        Change picture
                        <input type="file" name="photo" id="photo-input" class="hidden" accept="image/*">
                    </label>
                    <p class="mt-1 text-xs text-[var(--color-muted)]">JPG/PNG, up to 5MB.</p>
                </div>
            </div>

            {{-- Name + slug --}}
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Channel name <span class="text-red-500">*</span></label>
                    <input name="name" value="{{ old('name', $conversation->name) }}" required maxlength="120"
                           class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Slug</label>
                    <div class="flex items-center rounded-lg border border-gray-200 focus-within:border-[var(--color-primary)]">
                        <span class="pl-3 text-sm text-gray-400">#</span>
                        <input name="slug" value="{{ old('slug', $conversation->slug) }}" maxlength="120" placeholder="auto from name"
                               class="h-11 w-full rounded-lg border-0 px-1.5 text-sm focus:ring-0">
                    </div>
                    <p class="mt-1 text-xs text-[var(--color-muted)]">Leave blank to generate from the name.</p>
                </div>
            </div>

            <div class="mt-4">
                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Description</label>
                <input name="description" value="{{ old('description', $conversation->description) }}" maxlength="255" placeholder="Optional"
                       class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm">
            </div>

            {{-- Members --}}
            <div class="mt-6 border-t border-gray-100 pt-5">
                <div class="mb-2 flex items-center justify-between">
                    <label class="text-sm font-semibold text-[var(--color-heading)]">Members</label>
                    <input type="text" data-member-search placeholder="Search…" class="h-8 w-40 rounded-lg border border-gray-200 px-2.5 text-xs">
                </div>
                <div class="max-h-80 space-y-1 overflow-y-auto rounded-lg border border-gray-100 p-2">
                    @foreach ($people as $p)
                        @php $isMember = in_array($p->id, $memberIds); $isManager = in_array($p->id, $managerIds); @endphp
                        <label data-member-row="{{ strtolower($p->name) }}" class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-2 hover:bg-gray-50">
                            <input type="checkbox" name="members[]" value="{{ $p->id }}"
                                   @checked($isMember) @disabled($isManager)
                                   class="h-4 w-4 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                            @if ($p->photo_url)
                                <img src="{{ $p->photo_url }}" class="h-8 w-8 rounded-full object-cover" alt="">
                            @else
                                <span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--color-primary-soft)] text-xs font-bold text-[var(--color-primary)]">{{ strtoupper(substr($p->name, 0, 1)) }}</span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-[var(--color-heading)]">{{ $p->name }}{{ $p->id === $me->id ? ' (you)' : '' }}</span>
                                <span class="block truncate text-xs text-[var(--color-muted)]">{{ $p->designation->name ?? 'Team member' }}</span>
                            </span>
                            @if ($isManager)<span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-500">Manager</span>@endif
                        </label>
                    @endforeach
                </div>
                <p class="mt-1.5 text-xs text-[var(--color-muted)]">Uncheck to remove a member. Managers can't be removed here.</p>
            </div>

            @if ($errors->any())
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
        </div>

        <div class="mt-5 flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save changes</button>
            <a href="{{ route('admin.chat.show', $conversation) }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>

    <script>
        (function () {
            // Live preview of the chosen picture
            const input = document.getElementById('photo-input');
            const box = document.getElementById('avatar-preview');
            if (input) input.addEventListener('change', function () {
                if (!this.files || !this.files[0]) return;
                const url = URL.createObjectURL(this.files[0]);
                box.innerHTML = '<img src="' + url + '" class="h-full w-full object-cover" alt="">';
            });
            // Member search
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
