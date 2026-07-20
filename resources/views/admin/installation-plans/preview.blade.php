@extends('admin.layouts.app')
@section('title', $product->name.' — Plan preview')

@php
    $currency = $product->currency ?: 'USD';
    $symbol = ['USD' => '$', 'BDT' => '৳', 'EUR' => '€', 'GBP' => '£'][$currency] ?? ($currency.' ');
    $features = $product->installationFeatures;
    $blockStatus = \App\Models\InstallationPlan::STATUSES[$product->installation_status ?? 'published'] ?? 'Published';
@endphp

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <nav class="mb-1 flex items-center gap-2 text-sm text-[var(--color-muted)]">
                <a href="{{ route('admin.installation-plans') }}?product={{ $product->id }}" class="hover:text-[var(--color-heading)]">Installation Plans</a>
                <svg class="h-3.5 w-3.5 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
                <span class="text-[var(--color-heading)]">Preview</span>
            </nav>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">{{ $product->name }}</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">How these plans appear on the website.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- Draft view shows everything; live view shows only what the public API returns --}}
            <a href="{{ route('admin.installation-plans.preview', $product) }}"
               class="rounded-lg border px-4 py-2.5 text-sm font-semibold transition {{ $live ? 'border-gray-200 text-[var(--color-muted)] hover:bg-gray-50' : 'border-[var(--color-primary)] bg-[var(--color-primary-soft)] text-[var(--color-primary)]' }}">
                Draft view
            </a>
            <a href="{{ route('admin.installation-plans.preview', $product) }}?live=1"
               class="rounded-lg border px-4 py-2.5 text-sm font-semibold transition {{ $live ? 'border-[var(--color-primary)] bg-[var(--color-primary-soft)] text-[var(--color-primary)]' : 'border-gray-200 text-[var(--color-muted)] hover:bg-gray-50' }}">
                Live view
            </a>
            <a href="{{ route('admin.installation-plans') }}?product={{ $product->id }}"
               class="rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)]">Back to editing</a>
        </div>
    </div>

    @if ($live)
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <strong>Live view</strong> — exactly what visitors see right now. Draft and unpublished plans are hidden.
        </div>
    @else
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <strong>Draft view</strong> — every plan is shown. This product's plans are currently <strong>{{ $blockStatus }}</strong>.
        </div>
    @endif

    @if ($plans->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-200 py-16 text-center">
            <p class="text-sm text-gray-400">{{ $live ? 'No published plans — the website shows nothing for this product.' : 'No plans yet.' }}</p>
        </div>
    @else
        {{-- ===== Pricing cards, laid out like the website ===== --}}
        <div class="rounded-2xl border border-gray-100 bg-gray-50 p-6">
            <div class="grid gap-5" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr))">
                @foreach ($plans as $plan)
                    <div class="relative flex flex-col rounded-2xl border bg-white p-6 shadow-sm {{ $plan->is_popular ? 'border-[var(--color-primary)]' : 'border-gray-100' }}">
                        @if ($plan->is_popular)
                            <span class="absolute left-1/2 -translate-x-1/2 rounded-full bg-[var(--color-primary)] px-3 py-1 text-[11px] font-bold text-white" style="top:-12px">Most Popular</span>
                        @endif

                        <h3 class="text-lg font-bold text-[var(--color-heading)]">{{ $plan->name }}</h3>
                        @if ($plan->tagline)
                            <p class="mt-1 text-sm text-[var(--color-muted)]">{{ $plan->tagline }}</p>
                        @endif

                        <div class="mt-4 flex items-end gap-2">
                            @if ($plan->sale_price !== null)
                                <span class="text-3xl font-bold text-[var(--color-heading)]">{{ $symbol }}{{ number_format((float) $plan->sale_price, 0) }}</span>
                                <span class="pb-1 text-sm text-gray-400 line-through">{{ $symbol }}{{ number_format((float) $plan->price, 0) }}</span>
                            @else
                                <span class="text-3xl font-bold text-[var(--color-heading)]">{{ $symbol }}{{ number_format((float) $plan->price, 0) }}</span>
                            @endif
                        </div>
                        @if ($plan->note)
                            <p class="mt-1 text-xs text-[var(--color-muted)]">{{ $plan->note }}</p>
                        @endif

                        <ul class="mt-5 flex-1 space-y-2.5">
                            @php $included = $plan->features->pluck('id')->all(); @endphp
                            @forelse ($features as $f)
                                @php $on = in_array($f->id, $included, true); @endphp
                                <li class="flex items-start gap-2 text-sm {{ $on ? 'text-[var(--color-heading)]' : 'text-gray-300' }}">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 {{ $on ? 'text-emerald-500' : 'text-gray-200' }}" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $on ? 'm5 13 4 4L19 7' : 'M6 6l12 12M18 6 6 18' }}"/>
                                    </svg>
                                    <span>{{ $f->label }}</span>
                                </li>
                            @empty
                                <li class="text-sm text-gray-400">No features defined yet.</li>
                            @endforelse
                        </ul>

                        <button type="button" disabled
                                class="mt-6 w-full rounded-lg px-4 py-2.5 text-sm font-semibold {{ $plan->is_popular ? 'bg-[var(--color-primary)] text-white' : 'border border-gray-200 text-[var(--color-heading)]' }}">
                            Get Started
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        <p class="mt-3 text-center text-xs text-gray-400">This is a preview inside the admin — buttons are inactive here.</p>
    @endif
@endsection
