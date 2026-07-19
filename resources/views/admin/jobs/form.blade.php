@extends('admin.layouts.app')
@section('title', $job->exists ? 'Edit Opening' : 'Add Opening')

@section('content')
    @php $me = auth()->user(); $canPublish = $me->hasPermission('careers.publish'); @endphp
    <div class="mx-auto max-w-3xl">
        <div class="mb-5 flex items-center gap-2">
            <a href="{{ route('admin.jobs.index') }}" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="Back">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-lg font-bold text-[var(--color-heading)]">{{ $job->exists ? 'Edit opening' : 'Add opening' }}</h1>
        </div>

        <form method="POST" action="{{ $job->exists ? route('admin.jobs.update', $job) : route('admin.jobs.store') }}"
              class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            @csrf
            @if ($job->exists)@method('PUT')@endif

            <x-admin.field label="Role title" name="title" :value="$job->title" required placeholder="e.g. Senior Full-Stack Engineer" />

            <div class="grid gap-5 sm:grid-cols-3">
                <x-admin.field label="Department" name="department" :value="$job->department" placeholder="Engineering" />
                <x-admin.field label="Type" name="type" type="select" :value="$job->type"
                    :options="collect(\App\Models\JobOpening::TYPES)->mapWithKeys(fn ($t) => [$t => $t])->all()" required />
                <x-admin.field label="Location" name="location" :value="$job->location" placeholder="Dhaka / Remote" />
            </div>

            <x-admin.field label="Description" name="description" type="textarea" :rows="6" :value="$job->description"
                placeholder="Responsibilities, requirements, what we offer…" />

            <x-admin.field label="Apply link (optional)" name="apply_url" type="url" :value="$job->apply_url"
                placeholder="https://…  — leave blank to use the website's Apply form" hint="External application URL. If empty, applicants use the site's contact form." />

            {{-- Publish control — only users with the publish permission see the live option. --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Visibility</label>
                @if ($canPublish)
                    <div class="flex flex-wrap gap-3">
                        <label class="flex flex-1 cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 has-[:checked]:border-[var(--color-primary)] has-[:checked]:bg-[var(--color-primary-soft)]">
                            <input type="radio" name="status" value="draft" class="mt-0.5 accent-[var(--color-primary)]" @checked(! $job->isPublished())>
                            <span><span class="block text-sm font-semibold text-[var(--color-heading)]">Draft</span><span class="block text-xs text-gray-500">Internal only — not on the website.</span></span>
                        </label>
                        <label class="flex flex-1 cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-50">
                            <input type="radio" name="status" value="published" class="mt-0.5 accent-emerald-600" @checked($job->isPublished())>
                            <span><span class="block text-sm font-semibold text-[var(--color-heading)]">Published</span><span class="block text-xs text-gray-500">Live on razinsoft.com/careers.</span></span>
                        </label>
                    </div>
                @else
                    <input type="hidden" name="status" value="draft">
                    <p class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-xs text-amber-700">Saved as a <span class="font-semibold">draft</span>. A user with publish rights will review and make it live.</p>
                @endif
            </div>

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.jobs.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-[var(--color-primary)] px-5 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">{{ $job->exists ? 'Save changes' : 'Create opening' }}</button>
            </div>
        </form>
    </div>
@endsection
