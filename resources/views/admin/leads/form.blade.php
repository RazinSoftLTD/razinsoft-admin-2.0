@extends('admin.layouts.app')
@section('title', $lead->exists ? 'Edit Lead' : 'Add New Lead')

@php
    $countries = config('countries');
    $addedByUser = $lead->exists ? $lead->addedBy : auth()->user();
    $isYou = ! $lead->exists || optional($lead->addedBy)->id === auth()->id();
@endphp

@section('content')
    <form method="POST" action="{{ $lead->exists ? route('admin.leads.update', $lead) : route('admin.leads.store') }}"
          x-data="{ createDeal: {{ old('create_deal') ? 'true' : 'false' }} }">
        @csrf
        @if ($lead->exists) @method('PUT') @endif

        {{-- ── Sticky action bar ── --}}
        <div class="sticky top-16 z-10 -mx-4 mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-[var(--color-body)]/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.leads.index') }}" class="grid h-9 w-9 place-items-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50" title="Back to leads">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 12H5m6 6-6-6 6-6"/></svg>
                </a>
                <div>
                    <h1 class="text-base font-bold text-[var(--color-heading)] sm:text-lg">{{ $lead->exists ? 'Edit Lead' : 'Add New Lead' }}</h1>
                    <p class="text-xs text-[var(--color-muted)]">CRM › Leads › {{ $lead->exists ? 'Edit' : 'New' }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.leads.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
                @if (! $lead->exists && auth()->user()->allows('meetings', 'view'))
                    <button type="submit" name="after" value="meeting"
                            class="inline-flex items-center gap-2 rounded-lg border border-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-[var(--color-primary)] hover:bg-[var(--color-primary-soft)]">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg>
                        Save &amp; Create Meeting
                    </button>
                @endif
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-5 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                    {{ $lead->exists ? 'Save Changes' : 'Save Lead' }}
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

                {{-- Lead Contact Detail --}}
                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-gray-100 px-6 py-4">
                        <span class="grid h-9 w-9 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21a8 8 0 0 1 16 0"/></svg></span>
                        <div>
                            <h2 class="text-sm font-bold text-[var(--color-heading)]">Lead Contact Detail</h2>
                            <p class="text-xs text-[var(--color-muted)]">Who is this lead and how do you reach them?</p>
                        </div>
                    </div>
                    <div class="grid gap-5 p-6 sm:grid-cols-2 lg:grid-cols-6">
                        <div class="lg:col-span-1"><x-admin.field label="Salutation" name="salutation" type="select" :value="$lead->salutation" :options="['' => '--'] + array_combine(\App\Models\Lead::SALUTATIONS, \App\Models\Lead::SALUTATIONS)" /></div>
                        <div class="sm:col-span-1 lg:col-span-2"><x-admin.field label="Name" name="full_name" :value="$lead->full_name" required placeholder="e.g. John Doe" /></div>
                        <div class="lg:col-span-3"><x-admin.field label="Email" name="email" type="email" :value="$lead->email" placeholder="e.g. johndoe@example.com" hint="Used to send proposals." /></div>

                        {{-- Phone + WhatsApp --}}
                        <div class="sm:col-span-2 lg:col-span-3">
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Phone <span class="text-red-500">*</span></label>
                            <div class="flex">
                                <select name="dial_code" class="h-11 rounded-l-lg border border-r-0 border-gray-200 bg-gray-50 px-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                    @foreach ($countries as $c)
                                        <option value="{{ $c['dial'] }}" @selected(old('dial_code', $lead->dial_code ?? '+880') === $c['dial'])>{{ $c['flag'] }} {{ $c['dial'] }}</option>
                                    @endforeach
                                </select>
                                <input name="phone" value="{{ old('phone', $lead->phone) }}" required placeholder="1XXX-XXXXXX" class="h-11 w-full rounded-r-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            </div>
                            <label class="mt-2 inline-flex cursor-pointer items-center gap-2 text-sm text-[var(--color-heading)]">
                                <input type="checkbox" name="is_whatsapp" value="1" @checked(old('is_whatsapp', $lead->is_whatsapp)) class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                <svg class="h-4 w-4 text-emerald-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-1.7-.1a10 10 0 0 1-3-1.8 11 11 0 0 1-2.3-2.9c-.5-.8-.6-1.5-.6-1.8 0-.5.5-1.2.8-1.5.2-.2.4-.2.6-.2h.5c.2 0 .4 0 .5.4l.7 1.7c.1.2 0 .4-.1.5l-.4.5c-.1.2-.3.3-.1.6.3.5.8 1.2 1.4 1.7.7.6 1.3.8 1.6 1 .2 0 .4 0 .5-.1l.6-.7c.2-.2.3-.2.5-.1l1.6.8c.2.1.4.2.4.3.1.2.1.6-.1 1.1Z"/></svg>
                                This number is on WhatsApp
                            </label>
                        </div>
                        <div class="sm:col-span-2 lg:col-span-3"><x-admin.field label="Job Title" name="job_title" :value="$lead->job_title" placeholder="e.g. Marketing Manager" /></div>
                    </div>
                </section>

                {{-- Company Details --}}
                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-gray-100 px-6 py-4">
                        <span class="grid h-9 w-9 place-items-center rounded-lg bg-amber-50 text-amber-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 3h12a2 2 0 0 1 2 2v16H4V5a2 2 0 0 1 2-2Z M9 7h.01M9 11h.01M9 15h.01M15 7h.01M15 11h.01M15 15h.01"/></svg></span>
                        <div>
                            <h2 class="text-sm font-bold text-[var(--color-heading)]">Company Details</h2>
                            <p class="text-xs text-[var(--color-muted)]">Optional — organisation & address.</p>
                        </div>
                    </div>
                    <div class="grid gap-5 p-6 sm:grid-cols-2 lg:grid-cols-3">
                        <x-admin.field label="Company Name" name="company_name" :value="$lead->company_name" placeholder="Enter company name" />
                        <x-admin.field label="Website" name="website" :value="$lead->website" placeholder="https://…" />
                        <x-admin.field label="Mobile" name="mobile" :value="$lead->mobile" placeholder="Mobile number" />
                        <x-admin.field label="Office Phone Number" name="office_phone" :value="$lead->office_phone" placeholder="Office phone" />
                        <div class="lg:col-span-2"><x-admin.field label="Address" name="address" :value="$lead->address" placeholder="Street address" /></div>
                        <x-admin.field label="City" name="city" :value="$lead->city" placeholder="City" />
                        <x-admin.field label="State / Region" name="state" :value="$lead->state" placeholder="State" />
                        <x-admin.field label="Country" name="country" :value="$lead->country" placeholder="Country" />
                        <x-admin.field label="ZIP / Postal Code" name="zip" :value="$lead->zip" placeholder="ZIP" />
                    </div>
                </section>

                {{-- Notes --}}
                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="p-6">
                        <x-admin.field label="Description / Notes" name="notes" type="textarea" rows="4" :value="$lead->notes" placeholder="Notes about this lead…" hint="Max 500 characters." />
                    </div>
                </section>
            </div>

            {{-- Right sidebar --}}
            <div class="space-y-6">

                {{-- Ownership & Status --}}
                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">Ownership & Status</h2>
                    </div>
                    <div class="space-y-5 p-6">
                        <x-admin.field label="Lead Source" name="lead_source" type="select" required :value="$lead->lead_source" :options="['' => '--'] + array_combine(\App\Models\Lead::SOURCES, \App\Models\Lead::SOURCES)" />

                        {{-- Added By (read-only) --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Added By</label>
                            <div class="flex h-11 items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 text-sm">
                                <span class="grid h-6 w-6 place-items-center rounded-full bg-[var(--color-primary-soft)] text-[11px] font-bold text-[var(--color-primary)]">{{ strtoupper(substr($addedByUser->name ?? 'U', 0, 1)) }}</span>
                                <span class="font-medium text-[var(--color-heading)]">{{ $addedByUser->name ?? auth()->user()->name }}</span>
                                @if ($isYou)<span class="rounded bg-gray-200 px-1.5 py-0.5 text-[11px] font-semibold text-gray-600">It's you</span>@endif
                            </div>
                        </div>

                        <x-admin.field label="Lead Owner" name="assigned_to" type="select" required :value="$lead->assigned_to" :options="['' => 'Select owner'] + $users->pluck('name', 'id')->all()" />
                        <div class="grid grid-cols-2 gap-4">
                            <x-admin.field label="Status" name="lead_status" type="select" required :value="$lead->lead_status" :options="\App\Models\Lead::STATUSES" />
                            <x-admin.field label="Priority" name="priority" type="select" required :value="$lead->priority" :options="\App\Models\Lead::PRIORITIES" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <x-admin.field label="Team" name="team" type="select" :value="$lead->team" :options="['' => 'Select'] + array_combine(\App\Models\Lead::TEAMS, \App\Models\Lead::TEAMS)" />
                            <x-admin.field label="Industry" name="industry" type="select" :value="$lead->industry" :options="['' => 'Select'] + array_combine(\App\Models\Lead::INDUSTRIES, \App\Models\Lead::INDUSTRIES)" />
                        </div>
                        <x-admin.field label="Next Follow-up Date" name="next_follow_up_at" type="date" :value="optional($lead->next_follow_up_at)->toDateString()" />
                    </div>
                </section>

                {{-- Create Deal --}}
                <section class="overflow-hidden rounded-xl border shadow-sm transition"
                         :class="createDeal ? 'border-[var(--color-primary)]' : 'border-gray-100'">
                    <label class="flex cursor-pointer items-center gap-3 bg-white px-6 py-4"
                           :class="createDeal ? 'border-b border-gray-100' : ''">
                        <input type="checkbox" name="create_deal" value="1" x-model="createDeal" class="h-4 w-4 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                        <span>
                            <span class="block text-sm font-bold text-[var(--color-heading)]">Create a Deal</span>
                            <span class="block text-xs text-[var(--color-muted)]">Open a deal in the pipeline for this lead.</span>
                        </span>
                    </label>
                    <div x-show="createDeal" x-cloak class="space-y-5 bg-white p-6 pt-1">
                        <x-admin.field label="Deal Name" name="deal_name" :value="old('deal_name')" required placeholder="e.g. Website project" />
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Pipeline</label>
                            <select name="pipeline" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm"><option value="sales">📊 Sales Pipeline</option></select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Deal Stage <span class="text-red-500">*</span></label>
                            <select name="deal_stage" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                                @foreach (\App\Models\Deal::STAGES as $k => $label)
                                    <option value="{{ $k }}" @selected(old('deal_stage', 'new') === $k)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Deal Value <span class="text-red-500">*</span></label>
                            <div class="flex">
                                <select name="deal_currency" class="h-11 rounded-l-lg border border-r-0 border-gray-200 bg-gray-50 px-2 text-sm">
                                    @foreach (['BDT' => '৳', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'INR' => '₹'] as $code => $sym)
                                        <option value="{{ $code }}" @selected(old('deal_currency', 'BDT') === $code)>{{ $sym }} {{ $code }}</option>
                                    @endforeach
                                </select>
                                <input type="number" name="deal_value" step="0.01" min="0" value="{{ old('deal_value', 0) }}" class="h-11 w-full rounded-r-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </form>
@endsection
