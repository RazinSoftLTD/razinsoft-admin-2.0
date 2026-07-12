@extends('admin.layouts.app')
@section('title', $project->exists ? 'Edit Project' : 'New Project')

@section('content')
    <a href="{{ $project->exists ? route('admin.projects.show', $project) : route('admin.projects.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back
    </a>

    <form method="POST" action="{{ $project->exists ? route('admin.projects.update', $project) : route('admin.projects.store') }}" class="max-w-4xl">
        @csrf
        @if ($project->exists) @method('PUT') @endif

        <div class="space-y-6">
            <div class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Basic Information</h2>
                <div class="grid gap-5 sm:grid-cols-3">
                    <div class="sm:col-span-2"><x-admin.field label="Project Name" name="name" :value="$project->name" required placeholder="e.g. Food Delivery System" /></div>
                    <x-admin.field label="Project Type" name="project_type" type="select" :value="$project->project_type" :options="['' => 'Select…'] + array_combine(\App\Models\Project::TYPES, \App\Models\Project::TYPES)" />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-admin.field label="Client" name="client_id" type="select" :value="$project->client_id" :options="['' => 'No client'] + $clients->pluck('name', 'id')->all()" />
                    <x-admin.field label="Company" name="company" :value="$project->company" placeholder="Client company" />
                </div>
                <div class="grid gap-5 sm:grid-cols-3">
                    <x-admin.field label="Sales Person" name="sales_person_id" type="select" :value="$project->sales_person_id" :options="['' => '—'] + $staff->pluck('name', 'id')->all()" />
                    <x-admin.field label="Project Manager" name="project_manager_id" type="select" :value="$project->project_manager_id" :options="['' => '—'] + $staff->pluck('name', 'id')->all()" />
                    <x-admin.field label="Account Manager" name="account_manager_id" type="select" :value="$project->account_manager_id" :options="['' => '—'] + $staff->pluck('name', 'id')->all()" />
                </div>
            </div>

            <div class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Status &amp; Commercials</h2>
                <div class="grid gap-5 sm:grid-cols-3">
                    <x-admin.field label="Status" name="status" type="select" :value="$project->status ?? 'draft'" :options="\App\Models\Project::STATUSES" required />
                    <x-admin.field label="Priority" name="priority" type="select" :value="$project->priority ?? 'medium'" :options="\App\Models\Project::PRIORITIES" required />
                    <x-admin.field label="Progress (%)" name="progress" type="number" :value="$project->progress ?? 0" hint="Auto-updates from tasks too." />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-admin.field label="Budget" name="budget" type="number" :value="$project->budget" placeholder="0.00" />
                    <x-admin.field label="Currency" name="currency" type="select" :value="$project->currency ?? 'BDT'" :options="['BDT' => 'BDT (৳)', 'USD' => 'USD ($)', 'EUR' => 'EUR (€)', 'GBP' => 'GBP (£)', 'INR' => 'INR (₹)']" required />
                </div>
                <div class="grid gap-5 sm:grid-cols-3">
                    <x-admin.field label="Start Date" name="start_date" type="date" :value="optional($project->start_date)->toDateString()" />
                    <x-admin.field label="Expected Delivery" name="expected_delivery" type="date" :value="optional($project->expected_delivery)->toDateString()" />
                    <x-admin.field label="Actual Delivery" name="actual_delivery" type="date" :value="optional($project->actual_delivery)->toDateString()" />
                </div>
                <x-admin.field label="Description" name="description" type="textarea" rows="4" :value="$project->description" placeholder="Scope, goals, notes…" />
            </div>
        </div>

        @if ($errors->any())
            <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"><ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="mt-5 flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">{{ $project->exists ? 'Save changes' : 'Create project' }}</button>
            <a href="{{ $project->exists ? route('admin.projects.show', $project) : route('admin.projects.index') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>
@endsection
