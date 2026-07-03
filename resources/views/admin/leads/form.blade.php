@extends('admin.layouts.app')
@section('title', 'Add New Lead')

@section('content')
    <h1 class="text-xl font-bold text-[var(--color-heading)]">Add New Lead</h1>
    <p class="mt-1 text-sm text-[var(--color-muted)]">CRM &rsaquo; Leads &rsaquo; Add New Lead</p>

    <form method="POST" action="{{ route('admin.leads.store') }}" class="mt-6 max-w-4xl space-y-6">
        @csrf

        {{-- 1. Lead Information --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-5 text-sm font-bold text-[var(--color-heading)]">1. Lead Information</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <x-admin.field label="Full Name" name="full_name" required placeholder="Enter full name" />
                <x-admin.field label="Email" name="email" type="email" placeholder="Enter email address" />
                <x-admin.field label="Phone" name="phone" required placeholder="+880 1XXX-XXXXXX" />
                <x-admin.field label="Company Name" name="company_name" placeholder="Enter company name" />
                <x-admin.field label="Website" name="website" placeholder="Enter website (optional)" />
                <x-admin.field label="Job Title" name="job_title" placeholder="Enter job title (optional)" />
                <x-admin.field label="Lead Source" name="lead_source" type="select" required :options="['' => 'Select lead source'] + array_combine(\App\Models\Lead::SOURCES, \App\Models\Lead::SOURCES)" />
                <x-admin.field label="Industry" name="industry" type="select" :options="['' => 'Select industry'] + array_combine(\App\Models\Lead::INDUSTRIES, \App\Models\Lead::INDUSTRIES)" />
                <x-admin.field label="Lead Status" name="lead_status" type="select" required :options="\App\Models\Lead::STATUSES" value="new" />
            </div>
        </div>

        {{-- 2. Additional Information --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-5 text-sm font-bold text-[var(--color-heading)]">2. Additional Information</h2>
            <div class="space-y-5">
                <x-admin.field label="Address" name="address" placeholder="Enter address" />
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <x-admin.field label="City" name="city" placeholder="Enter city" />
                    <x-admin.field label="State / Region" name="state" placeholder="Enter state or region" />
                    <x-admin.field label="Country" name="country" placeholder="Enter country" />
                    <x-admin.field label="ZIP / Postal Code" name="zip" placeholder="Enter postal code" />
                </div>
                <x-admin.field label="Description / Notes" name="notes" type="textarea" rows="4" placeholder="Enter description or any additional notes about this lead…" hint="Max 500 characters." />
            </div>
        </div>

        {{-- 3. Assignment --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-5 text-sm font-bold text-[var(--color-heading)]">3. Assignment</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <x-admin.field label="Assign To" name="assigned_to" type="select" required :options="['' => 'Select user'] + $users->pluck('name', 'id')->all()" />
                <x-admin.field label="Team" name="team" type="select" :options="['' => 'Select team'] + array_combine(\App\Models\Lead::TEAMS, \App\Models\Lead::TEAMS)" />
                <x-admin.field label="Priority" name="priority" type="select" required :options="\App\Models\Lead::PRIORITIES" value="high" />
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
            <a href="{{ route('admin.dashboard') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save Lead</button>
        </div>
    </form>
@endsection
