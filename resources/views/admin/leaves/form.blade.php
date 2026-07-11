@extends('admin.layouts.app')
@section('title', 'Request Leave')

@section('content')
    <a href="{{ route('admin.leaves.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Leave
    </a>

    <form method="POST" action="{{ route('admin.leaves.store') }}" class="max-w-xl space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        @csrf
        <h1 class="text-lg font-bold text-[var(--color-heading)]">Request Leave</h1>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Leave Type <span class="text-red-500">*</span></label>
            <select name="leave_type" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                @foreach (\App\Models\Leave::TYPES as $val => $label)<option value="{{ $val }}" @selected(old('leave_type') === $val)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">From <span class="text-red-500">*</span></label>
                <input type="date" name="from_date" value="{{ old('from_date', now()->toDateString()) }}" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                @error('from_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">To <span class="text-red-500">*</span></label>
                <input type="date" name="to_date" value="{{ old('to_date', now()->toDateString()) }}" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                @error('to_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Reason</label>
            <textarea name="reason" rows="3" placeholder="Reason for leave…" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none">{{ old('reason') }}</textarea>
        </div>
        <div class="flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-6 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Submit request</button>
            <a href="{{ route('admin.leaves.index') }}" class="rounded-lg border border-gray-200 px-6 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>
@endsection
