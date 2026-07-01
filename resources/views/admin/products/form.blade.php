@extends('admin.layouts.app')
@section('title', 'New Product')

@section('content')
    <a href="{{ route('admin.products.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to products
    </a>

    <p class="mb-4 rounded-lg bg-[var(--color-primary-soft)] px-4 py-3 text-sm text-[var(--color-primary)]">Fill in the basics and media. The product is saved as a <strong>draft</strong> — add plans, gallery, demos, etc. and publish it later from the product view.</p>

    <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data" class="max-w-4xl">
        @csrf
        @include('admin.products._general')

        <div class="mt-5 flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Create draft &amp; continue</button>
            <a href="{{ route('admin.products.index') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>
@endsection
