@extends('admin.layouts.app')
@section('title', $coupon->exists ? 'Edit Coupon' : 'New Coupon')

@section('content')
    <a href="{{ route('admin.coupons.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to coupons
    </a>

    <form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" class="max-w-2xl">
        @csrf
        @if ($coupon->exists) @method('PUT') @endif

        <div class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <x-admin.field label="Code" name="code" :value="$coupon->code" required placeholder="RAZIN10" hint="Stored uppercase." />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-admin.field label="Type" name="type" type="select" :value="$coupon->type" :options="['percent' => 'Percentage (%)', 'flat' => 'Flat ($)']" required />
                <x-admin.field label="Value" name="value" type="number" :value="$coupon->value" required />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-admin.field label="Max uses" name="max_uses" type="number" :value="$coupon->max_uses" hint="Leave blank for unlimited." />
                <x-admin.field label="Expires at" name="expires_at" type="date" :value="$coupon->expires_at?->format('Y-m-d')" />
            </div>
            <x-admin.field name="is_active" type="checkbox" label="Active" :value="$coupon->is_active ?? true" />
        </div>

        <div class="mt-5 flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">{{ $coupon->exists ? 'Save changes' : 'Create coupon' }}</button>
            <a href="{{ route('admin.coupons.index') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>
@endsection
