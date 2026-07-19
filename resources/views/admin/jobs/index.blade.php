@extends('admin.layouts.app')
@section('title', 'Careers')

@section('content')
    @php $me = auth()->user(); @endphp
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-[var(--color-heading)]">Careers — Openings</h1>
            <p class="mt-0.5 text-sm text-[var(--color-muted)]">Only <span class="font-semibold text-emerald-600">Published</span> roles appear on the public website. Drafts stay internal.</p>
        </div>
        @if ($me->hasPermission('careers.create'))
            <a href="{{ route('admin.jobs.create') }}" class="inline-flex h-10 items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                Add opening
            </a>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        @if ($jobs->isEmpty())
            <div class="grid place-items-center px-6 py-16 text-center">
                <span class="grid h-14 w-14 place-items-center rounded-2xl bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7H4a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1ZM8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </span>
                <p class="mt-3 text-sm font-semibold text-[var(--color-heading)]">No openings yet</p>
                <p class="mt-1 text-sm text-[var(--color-muted)]">Add a role, keep it as a draft to review, then publish when it's ready.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                            <th class="px-5 py-3 font-semibold">Role</th>
                            <th class="px-5 py-3 font-semibold">Department</th>
                            <th class="px-5 py-3 font-semibold">Type</th>
                            <th class="px-5 py-3 font-semibold">Location</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($jobs as $job)
                            <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/60">
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-[var(--color-heading)]">{{ $job->title }}</p>
                                    <p class="text-xs text-gray-400">by {{ optional($job->creator)->name ?? '—' }} · {{ $job->created_at->format('d M Y') }}</p>
                                </td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $job->department ?: '—' }}</td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $job->type }}</td>
                                <td class="px-5 py-3 text-[var(--color-muted)]">{{ $job->location ?: '—' }}</td>
                                <td class="px-5 py-3">
                                    @if ($job->isPublished())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Published</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-600"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Draft</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($me->hasPermission('careers.publish'))
                                            <form method="POST" action="{{ route('admin.jobs.publish', $job) }}">
                                                @csrf
                                                <button type="submit" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold {{ $job->isPublished() ? 'text-amber-600 hover:bg-amber-50' : 'text-emerald-600 hover:bg-emerald-50' }}">
                                                    {{ $job->isPublished() ? 'Unpublish' : 'Publish' }}
                                                </button>
                                            </form>
                                        @endif
                                        @if ($me->hasPermission('careers.edit'))
                                            <a href="{{ route('admin.jobs.edit', $job) }}" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="Edit">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                            </a>
                                        @endif
                                        @if ($me->hasPermission('careers.delete'))
                                            <x-admin.del-button :action="route('admin.jobs.destroy', $job)" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
