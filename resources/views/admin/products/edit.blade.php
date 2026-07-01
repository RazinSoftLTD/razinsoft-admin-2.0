@extends('admin.layouts.app')
@section('title', 'Edit · ' . $product->name)

@section('content')
    <a href="{{ route('admin.products.show', $product) }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to product
    </a>

    <h1 class="mb-4 text-xl font-bold text-[var(--color-heading)]">Edit general &amp; media</h1>

    <form method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data" class="max-w-4xl">
        @csrf @method('PUT')
        @include('admin.products._general')

        <div class="mt-5 flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save changes</button>
            <a href="{{ route('admin.products.show', $product) }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>
@endsection
