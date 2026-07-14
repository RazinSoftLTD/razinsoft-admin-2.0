@extends('admin.layouts.app')
@section('title', $deal->exists ? 'Edit Deal' : 'New Deal')

@section('content')
    <a href="{{ route('admin.deals.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to deals
    </a>

    <form method="POST" action="{{ $deal->exists ? route('admin.deals.update', $deal) : route('admin.deals.store') }}" class="max-w-2xl">
        @csrf
        @if ($deal->exists) @method('PUT') @endif
        @if ($deal->lead_id)<input type="hidden" name="lead_id" value="{{ $deal->lead_id }}">@endif

        <div class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="grid gap-5 sm:grid-cols-3">
                <div class="sm:col-span-2"><x-admin.field label="Deal Title" name="title" :value="$deal->title" required placeholder="e.g. Acme — Website Project" /></div>
                <x-admin.field label="Project Type" name="project_type" type="select" :value="$deal->project_type" :options="['' => 'Select…'] + array_combine(\App\Models\Deal::PROJECT_TYPES, \App\Models\Deal::PROJECT_TYPES)" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-admin.field label="Client" name="client_id" type="select" :value="$deal->client_id" :options="['' => 'No client yet'] + $clients->pluck('name', 'id')->all()" />
                <x-admin.field label="Assigned To" name="assigned_to" type="select" :value="$deal->assigned_to" :options="['' => 'Unassigned'] + $staff->pluck('name', 'id')->all()" />
            </div>
            <div class="grid gap-5 sm:grid-cols-3">
                <x-admin.field label="Stage" name="stage" type="select" :value="$deal->stage" :options="\App\Models\Deal::stages()" required />
                <x-admin.field label="Priority" name="priority" type="select" :value="$deal->priority ?? 'medium'" :options="\App\Models\Deal::PRIORITIES" required />
                <x-admin.field label="Win Probability (%)" name="probability" type="number" :value="$deal->probability" placeholder="auto from stage" hint="Leave blank to use the stage default." />
            </div>
            <div class="grid gap-5 sm:grid-cols-3">
                <x-admin.field label="Value" name="value" type="number" :value="$deal->value ?? 0" required />
                <x-admin.field label="Currency" name="currency" type="select" :value="$deal->currency ?? 'BDT'" :options="['BDT' => 'BDT (৳)', 'USD' => 'USD ($)', 'EUR' => 'EUR (€)', 'GBP' => 'GBP (£)', 'INR' => 'INR (₹)']" required />
                <x-admin.field label="Expected Close Date" name="expected_close_date" type="date" :value="optional($deal->expected_close_date)->toDateString()" />
            </div>
            @if (($deal->stage ?? '') === 'lost')
                <x-admin.field label="Lost Reason" name="lost_reason" :value="$deal->lost_reason" placeholder="Why was this deal lost?" />
            @endif
            <x-admin.field label="Notes" name="notes" type="textarea" rows="3" :value="$deal->notes" placeholder="Scope, requirements, next steps…" />
        </div>

        @if ($errors->any())
            <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"><ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="mt-5 flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">{{ $deal->exists ? 'Save changes' : 'Create deal' }}</button>
            <a href="{{ route('admin.deals.index') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>
@endsection
