@extends('admin.layouts.app')
@section('title', 'Import Leads')

@section('content')
    <a href="{{ route('admin.leads.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to All Leads
    </a>

    <h1 class="text-xl font-bold text-[var(--color-heading)]">Import Leads</h1>
    <p class="mt-1 text-sm text-[var(--color-muted)]">Upload a CSV to bulk-create leads.</p>

    <form method="POST" action="{{ route('admin.leads.import') }}" enctype="multipart/form-data" class="mt-6 max-w-2xl space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        @csrf
        <div class="rounded-lg bg-gray-50 p-4 text-sm text-[var(--color-muted)]">
            <p class="font-semibold text-[var(--color-heading)]">CSV columns</p>
            <p class="mt-1"><code>full_name, email, phone, company_name, job_title, lead_source, industry, lead_status, priority</code></p>
            <p class="mt-2"><strong>full_name</strong> and <strong>phone</strong> are required. Rows with a duplicate email are skipped.</p>
            <a href="{{ route('admin.leads.import.sample') }}" class="mt-3 inline-flex items-center gap-1.5 font-semibold text-[var(--color-primary)] hover:underline">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                Download template
            </a>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">CSV file <span class="text-red-500">*</span></label>
            <input type="file" name="file" accept=".csv,text/csv" required class="text-sm text-[var(--color-muted)] file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
        </div>

        <x-admin.field label="Assign imported leads to" name="assigned_to" type="select" required :options="['' => 'Select user'] + $users->pluck('name', 'id')->all()" class="max-w-xs" />

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"><ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Import Leads</button>
    </form>
@endsection
