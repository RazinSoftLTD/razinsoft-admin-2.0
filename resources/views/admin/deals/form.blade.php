@extends('admin.layouts.app')
@section('title', $deal->exists ? 'Edit Deal' : 'New Deal')

@section('content')
    <form method="POST" action="{{ $deal->exists ? route('admin.deals.update', $deal) : route('admin.deals.store') }}">
        @csrf
        @if ($deal->exists) @method('PUT') @endif
        @if ($deal->lead_id)<input type="hidden" name="lead_id" value="{{ $deal->lead_id }}">@endif

        {{-- ── Sticky action bar ── --}}
        <div class="sticky top-16 z-10 -mx-4 mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-[var(--color-body)]/95 px-4 py-3 backdrop-blur sm:px-6">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.deals.index') }}" class="grid h-9 w-9 place-items-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50" title="Back to deals">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 12H5m6 6-6-6 6-6"/></svg>
                </a>
                <div>
                    <h1 class="text-base font-bold text-[var(--color-heading)] sm:text-lg">{{ $deal->exists ? 'Edit Deal' : 'New Deal' }}</h1>
                    <p class="text-xs text-[var(--color-muted)]">CRM › Deals › {{ $deal->exists ? 'Edit' : 'New' }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.deals.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-5 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                    {{ $deal->exists ? 'Save Changes' : 'Create Deal' }}
                </button>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <p class="font-semibold">Please fix the following:</p>
                <ul class="mt-1 list-inside list-disc space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- ── Main grid: content + sidebar ── --}}
        <div class="grid gap-6 xl:grid-cols-3">

            {{-- Left column --}}
            <div class="space-y-6 xl:col-span-2">

                {{-- What the deal is --}}
                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-gray-100 px-6 py-4">
                        <span class="grid h-9 w-9 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2M3 7h18v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/></svg>
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-[var(--color-heading)]">Deal Detail</h2>
                            <p class="text-xs text-[var(--color-muted)]">What is being sold, and under which product line.</p>
                        </div>
                    </div>
                    <div class="space-y-5 p-6">
                        <div class="grid gap-5 sm:grid-cols-3">
                            <div class="sm:col-span-2"><x-admin.field label="Deal Title" name="title" :value="$deal->title" required placeholder="e.g. Acme — Website Project" /></div>
                            <x-admin.field label="Project Type" name="project_type" type="select" :value="$deal->project_type" :options="['' => 'Select…'] + array_combine(\App\Models\Deal::PROJECT_TYPES, \App\Models\Deal::PROJECT_TYPES)" />
                        </div>
                        <x-admin.product-category-fields :category="old('product_category', $deal->product_category)" :sub-category="old('product_sub_category', $deal->product_sub_category)" />
                        <x-admin.field label="Notes" name="notes" type="textarea" rows="4" :value="$deal->notes" placeholder="Scope, requirements, next steps…" />
                    </div>
                </section>

                {{-- Money and dates --}}
                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-gray-100 px-6 py-4">
                        <span class="grid h-9 w-9 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M7 7h7a3 3 0 0 1 0 6H7h8"/></svg>
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-[var(--color-heading)]">Value &amp; Timeline</h2>
                            <p class="text-xs text-[var(--color-muted)]">What it is worth and when you expect to close it.</p>
                        </div>
                    </div>
                    <div class="space-y-5 p-6">
                        <div class="grid gap-5 sm:grid-cols-3">
                            <x-admin.field label="Value" name="value" type="number" :value="$deal->value ?? 0" required />
                            <x-admin.field label="Currency" name="currency" type="select" :value="$deal->currency ?? 'BDT'" :options="['BDT' => 'BDT (৳)', 'USD' => 'USD ($)', 'EUR' => 'EUR (€)', 'GBP' => 'GBP (£)', 'INR' => 'INR (₹)']" required />
                            <x-admin.field label="Expected Close Date" name="expected_close_date" type="date" :value="optional($deal->expected_close_date)->toDateString()" />
                        </div>
                        {{-- Wrapped, not styled directly: x-admin.field only forwards `class`. --}}
                        <div style="max-width:16rem">
                            <x-admin.field label="Win Probability (%)" name="probability" type="number" :value="$deal->probability" placeholder="auto from stage" hint="Leave blank to use the stage default." />
                        </div>
                    </div>
                </section>
            </div>

            {{-- Right column --}}
            <div class="space-y-6">

                {{-- Who owns it and where it sits in the pipeline --}}
                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">Ownership &amp; Stage</h2>
                    </div>
                    <div class="space-y-5 p-6">
                        <x-admin.field label="Client" name="client_id" type="select" :value="$deal->client_id" :options="['' => 'No client yet'] + $clients->pluck('name', 'id')->all()" />
                        <x-admin.field label="Assigned To" name="assigned_to" type="select" :value="$deal->assigned_to" :options="['' => 'Unassigned'] + $staff->pluck('name', 'id')->all()" />
                        <div class="grid grid-cols-2 gap-4">
                            <x-admin.field label="Stage" name="stage" type="select" :value="$deal->stage" :options="\App\Models\Deal::stages()" required />
                            <x-admin.field label="Priority" name="priority" type="select" :value="$deal->priority ?? 'medium'" :options="\App\Models\Deal::PRIORITIES" required />
                        </div>

                        {{-- Only worth asking once the deal is actually lost. --}}
                        @if (($deal->stage ?? '') === 'lost')
                            <x-admin.field label="Lost Reason" name="lost_reason" :value="$deal->lost_reason" placeholder="Why was this deal lost?" />
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </form>
@endsection
