@extends('admin.layouts.app')
@section('title', $lead->exists ? 'Edit Lead' : 'Add New Lead')

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">{{ $lead->exists ? 'Edit Lead' : 'Add New Lead' }}</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">CRM &rsaquo; Leads &rsaquo; {{ $lead->exists ? 'Edit Lead' : 'Add New Lead' }}</p>
        </div>
        <a href="{{ route('admin.leads.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 12H5m6 6-6-6 6-6"/></svg> Back to All Leads
        </a>
    </div>

    <form method="POST" action="{{ $lead->exists ? route('admin.leads.update', $lead) : route('admin.leads.store') }}" class="max-w-4xl space-y-6">
        @csrf
        @if ($lead->exists) @method('PUT') @endif

        {{-- 1. Lead Information --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-5 text-sm font-bold text-[var(--color-heading)]">1. Lead Information</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <x-admin.field label="Full Name" name="full_name" :value="$lead->full_name" required placeholder="Enter full name" />
                <x-admin.field label="Email" name="email" type="email" :value="$lead->email" placeholder="Enter email address" />
                <x-admin.field label="Phone" name="phone" :value="$lead->phone" required placeholder="+880 1XXX-XXXXXX" />
                <x-admin.field label="Company Name" name="company_name" :value="$lead->company_name" placeholder="Enter company name" />
                <x-admin.field label="Website" name="website" :value="$lead->website" placeholder="Enter website (optional)" />
                <x-admin.field label="Job Title" name="job_title" :value="$lead->job_title" placeholder="Enter job title (optional)" />
                <x-admin.field label="Lead Source" name="lead_source" type="select" required :value="$lead->lead_source" :options="['' => 'Select lead source'] + array_combine(\App\Models\Lead::SOURCES, \App\Models\Lead::SOURCES)" />
                <x-admin.field label="Industry" name="industry" type="select" :value="$lead->industry" :options="['' => 'Select industry'] + array_combine(\App\Models\Lead::INDUSTRIES, \App\Models\Lead::INDUSTRIES)" />
                <x-admin.field label="Lead Status" name="lead_status" type="select" required :value="$lead->lead_status" :options="\App\Models\Lead::STATUSES" />
            </div>
        </div>

        {{-- 2. Additional Information --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-5 text-sm font-bold text-[var(--color-heading)]">2. Additional Information</h2>
            <div class="space-y-5">
                <x-admin.field label="Address" name="address" :value="$lead->address" placeholder="Enter address" />
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <x-admin.field label="City" name="city" :value="$lead->city" placeholder="Enter city" />
                    <x-admin.field label="State / Region" name="state" :value="$lead->state" placeholder="Enter state or region" />
                    <x-admin.field label="Country" name="country" :value="$lead->country" placeholder="Enter country" />
                    <x-admin.field label="ZIP / Postal Code" name="zip" :value="$lead->zip" placeholder="Enter postal code" />
                </div>
                <x-admin.field label="Description / Notes" name="notes" type="textarea" rows="4" :value="$lead->notes" placeholder="Enter description or any additional notes about this lead…" hint="Max 500 characters." />
            </div>
        </div>

        {{-- 3. Assignment --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-5 text-sm font-bold text-[var(--color-heading)]">3. Assignment</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <x-admin.field label="Assign To" name="assigned_to" type="select" required :value="$lead->assigned_to" :options="['' => 'Select user'] + $users->pluck('name', 'id')->all()" />
                <x-admin.field label="Team" name="team" type="select" :value="$lead->team" :options="['' => 'Select team'] + array_combine(\App\Models\Lead::TEAMS, \App\Models\Lead::TEAMS)" />
                <x-admin.field label="Priority" name="priority" type="select" required :value="$lead->priority" :options="\App\Models\Lead::PRIORITIES" />
            </div>
            <div class="mt-5">
                <x-admin.field label="Next Follow-up Date" name="next_follow_up_at" type="date" :value="optional($lead->next_follow_up_at)->toDateString()" hint="Schedule when to reach out next — shows on the Follow-up page." class="sm:max-w-xs" />
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-wrap justify-end gap-3">
            <a href="{{ route('admin.leads.index') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">{{ $lead->exists ? 'Save Changes' : 'Save Lead' }}</button>
        </div>
    </form>
@endsection
