@extends('admin.layouts.app')
@section('title', 'Invoice Configuration')

@php $input = 'h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none'; @endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">Invoice Configuration</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">Settings &rsaquo; Invoice Configuration — manage the invoice logo, unit types and tax / charge types.</p>
    </div>

    @if (session('status'))<div class="mb-5 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- ===== Branding / Logo ===== --}}
        <section class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-2">
            <h2 class="mb-5 text-sm font-bold text-[var(--color-heading)]">Invoice Logo &amp; Branding</h2>
            <form method="POST" action="{{ route('admin.invoice-config.branding') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-6">
                @csrf
                <div class="flex items-center gap-4">
                    <div class="grid h-20 w-20 place-items-center overflow-hidden rounded-xl border border-gray-100 bg-gray-50">
                        @if ($settings->logo_url)
                            <img id="logo-preview" src="{{ $settings->logo_url }}" alt="Logo" class="h-full w-full object-contain">
                        @else
                            <img id="logo-preview" src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 24 24' fill='none' stroke='%23cbd5e1' stroke-width='1.5'><rect x='3' y='3' width='18' height='18' rx='2'/><circle cx='9' cy='9' r='2'/><path d='m21 15-5-5L5 21'/></svg>" alt="" class="h-10 w-10">
                        @endif
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Logo</label>
                        <input type="file" name="logo" accept="image/*" onchange="const f=this.files[0]; if(f) document.getElementById('logo-preview').src=URL.createObjectURL(f)"
                               class="block text-sm text-[var(--color-muted)] file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-heading)] hover:file:bg-gray-200">
                        <p class="mt-1 text-xs text-gray-400">PNG/JPG, up to 2&nbsp;MB. Shown on invoices &amp; PDFs.</p>
                    </div>
                </div>
                <div class="min-w-[200px] flex-1">
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Brand name</label>
                    <input type="text" name="brand_name" value="{{ old('brand_name', $settings->brand_name) }}" placeholder="RazinSoft" class="{{ $input }}">
                </div>
                <button class="h-11 rounded-lg bg-[var(--color-primary)] px-5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save branding</button>
            </form>
        </section>

        {{-- ===== Units ===== --}}
        <section class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-1 text-sm font-bold text-[var(--color-heading)]">Unit Types</h2>
            <p class="mb-4 text-xs text-[var(--color-muted)]">Quantity units for invoice lines (Items, Hours, Pcs…). The default is pre-selected on new lines.</p>

            <div class="space-y-2">
                @forelse ($units as $u)
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('admin.invoice-config.units.update', $u) }}" class="flex flex-1 items-center gap-2">
                            @csrf @method('PATCH')
                            <input type="text" name="name" value="{{ $u->name }}" class="h-10 flex-1 rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <label class="flex h-10 shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 px-3 text-xs font-medium text-[var(--color-muted)]">
                                <input type="checkbox" name="is_default" value="1" @checked($u->is_default) class="accent-[var(--color-primary)]"> Default
                            </label>
                            <button class="grid h-10 w-10 shrink-0 place-items-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50" title="Save">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.invoice-config.units.destroy', $u) }}" onsubmit="return confirm('Delete unit “{{ $u->name }}”?')">
                            @csrf @method('DELETE')
                            <button class="grid h-10 w-10 place-items-center rounded-lg text-gray-300 hover:bg-red-50 hover:text-red-600" title="Delete">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No units yet.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('admin.invoice-config.units.store') }}" class="mt-4 flex gap-2 border-t border-gray-100 pt-4">
                @csrf
                <input type="text" name="name" placeholder="New unit (e.g. Days)" required class="h-10 flex-1 rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                <button class="h-10 rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
            </form>
        </section>

        {{-- ===== Taxes / Charges ===== --}}
        <section class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-1 text-sm font-bold text-[var(--color-heading)]">Tax / Charge Types</h2>
            <p class="mb-4 text-xs text-[var(--color-muted)]">Named percentages you can apply to invoice lines (Vat/Tax, Paypal Charge…).</p>

            <div class="space-y-2">
                @forelse ($taxes as $t)
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('admin.invoice-config.taxes.update', $t) }}" class="flex flex-1 items-center gap-2">
                            @csrf @method('PATCH')
                            <input type="text" name="name" value="{{ $t->name }}" class="h-10 flex-1 rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <div class="relative w-28 shrink-0">
                                <input type="number" step="0.001" min="0" max="100" name="rate" value="{{ rtrim(rtrim(number_format($t->rate, 3, '.', ''), '0'), '.') }}" class="h-10 w-full rounded-lg border border-gray-200 pl-3 pr-7 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                <span class="absolute inset-y-0 right-3 flex items-center text-sm text-gray-400">%</span>
                            </div>
                            <button class="grid h-10 w-10 shrink-0 place-items-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50" title="Save">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.invoice-config.taxes.destroy', $t) }}" onsubmit="return confirm('Delete tax “{{ $t->name }}”?')">
                            @csrf @method('DELETE')
                            <button class="grid h-10 w-10 place-items-center rounded-lg text-gray-300 hover:bg-red-50 hover:text-red-600" title="Delete">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No taxes yet.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('admin.invoice-config.taxes.store') }}" class="mt-4 flex gap-2 border-t border-gray-100 pt-4">
                @csrf
                <input type="text" name="name" placeholder="Name (e.g. Service Charge)" required class="h-10 flex-1 rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                <div class="relative w-28 shrink-0">
                    <input type="number" step="0.001" min="0" max="100" name="rate" placeholder="0" required class="h-10 w-full rounded-lg border border-gray-200 pl-3 pr-7 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    <span class="absolute inset-y-0 right-3 flex items-center text-sm text-gray-400">%</span>
                </div>
                <button class="h-10 rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
            </form>
        </section>
    </div>
@endsection
