@extends('admin.layouts.app')
@section('title', $maintenance->exists ? 'Edit · '.$maintenance->title : 'New Maintenance')

@section('content')
@php $editing = $maintenance->exists; @endphp

<a href="{{ $editing ? route('admin.maintenance.show', $maintenance) : route('admin.maintenance.index') }}"
   class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg>
    Back
</a>

<h1 class="mb-4 text-xl font-bold text-[var(--color-heading)]">{{ $editing ? 'Edit maintenance' : 'New maintenance' }}</h1>

<form method="POST" action="{{ $editing ? route('admin.maintenance.update', $maintenance) : route('admin.maintenance.store') }}" class="max-w-4xl">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-400">Contract</h3>
        <div class="space-y-4">
            <x-admin.field label="Title" name="title" :value="$maintenance->title" required
                           placeholder="e.g. Ready eCommerce — annual maintenance" />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field label="Client" name="client_id" type="select" :value="$maintenance->client_id" required
                               :options="$clients->pluck('name', 'id')->all()" />
                {{-- Optional: some contracts cover work this panel never tracked as a project, and
                     refusing those would push them back into a spreadsheet. --}}
                <x-admin.field label="Project" name="project_id" type="select" :value="$maintenance->project_id"
                               :options="['' => 'Not linked to a project'] + $projects->pluck('name', 'id')->all()"
                               hint="Optional — link it if we built the project here." />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field label="Starts on" name="starts_on" type="date" required
                               :value="optional($maintenance->starts_on)->toDateString()" />
                <x-admin.field label="Runs out on" name="ends_on" type="date" required
                               :value="optional($maintenance->ends_on)->toDateString()"
                               hint="Renewal is asked for {{ \App\Models\MaintenanceProject::RENEWAL_WINDOW_DAYS }} days before this." />
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.field label="Fee" name="fee" type="number" step="0.01" min="0" :value="$maintenance->fee" />
                <x-admin.field label="Currency" name="currency" :value="$maintenance->currency ?? 'USD'" />
                <x-admin.field label="Billing" name="billing_cycle" type="select" :value="$maintenance->billing_cycle"
                               :options="\App\Models\MaintenanceProject::CYCLES" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field label="Status" name="status" type="select" :value="$maintenance->status"
                               :options="\App\Models\MaintenanceProject::STATUSES" />
                <x-admin.field label="Looked after by" name="assigned_to" type="select" :value="$maintenance->assigned_to"
                               :options="['' => 'Unassigned'] + $staff->pluck('name', 'id')->all()" />
            </div>

            <x-admin.field label="What is covered" name="scope" type="textarea" :rows="4" :value="$maintenance->scope"
                           hint="What the client is paying for — useful when someone asks whether a request is in scope." />
            <x-admin.field label="Internal notes" name="notes" type="textarea" :rows="3" :value="$maintenance->notes" />
        </div>
    </div>

    <div class="mt-5 flex gap-3">
        <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
            {{ $editing ? 'Save changes' : 'Create maintenance' }}
        </button>
        <a href="{{ $editing ? route('admin.maintenance.show', $maintenance) : route('admin.maintenance.index') }}"
           class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
    </div>
</form>

@if (! $editing)
    <p class="mt-4 max-w-4xl text-sm text-[var(--color-muted)]">Add the daily / weekly / monthly plan after this is created.</p>
@endif
@endsection
