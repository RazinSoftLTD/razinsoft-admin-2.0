@extends('admin.layouts.app')
@section('title', 'CRM Settings')

@section('content')
    <div class="mb-6">
        <h1 class="text-lg font-bold text-[var(--color-heading)]">CRM Settings</h1>
        <p class="text-sm text-[var(--color-muted)]">Configure the option lists used across the CRM. These drive the Add / Edit Lead form.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    {{-- Tabs (Leads for now; room for more CRM areas later) --}}
    <div x-data="{ tab: 'leads' }" class="rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="flex gap-1 border-b border-gray-100 px-4 pt-3">
            <button type="button" @click="tab = 'leads'"
                    :class="tab === 'leads' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]'"
                    class="border-b-2 px-4 py-2.5 text-sm font-semibold">Leads</button>
        </div>

        <div x-show="tab === 'leads'" class="grid gap-6 p-6 md:grid-cols-2">
            @php
                $lists = [
                    ['type' => 'source', 'title' => 'Lead Sources', 'hint' => 'Where the lead came from.', 'items' => $sources],
                    ['type' => 'department', 'title' => 'Lead Departments', 'hint' => 'Which team handles the lead.', 'items' => $departments],
                    ['type' => 'product', 'title' => 'Products', 'hint' => 'Extra products — added to the ones from the Products module.', 'items' => $products],
                ];
            @endphp

            @foreach ($lists as $list)
                <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-5">
                    <div class="mb-3">
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">{{ $list['title'] }}</h2>
                        <p class="text-xs text-[var(--color-muted)]">{{ $list['hint'] }}</p>
                    </div>

                    <div class="space-y-2">
                        @forelse ($list['items'] as $item)
                            <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-100 bg-white px-3 py-2">
                                <span class="text-sm font-medium text-[var(--color-heading)]">{{ $item->label }}</span>
                                <form method="POST" action="{{ route('admin.crm-settings.options.destroy', $item) }}" data-turbo="false"
                                      onsubmit="return confirm('Remove “{{ $item->label }}”?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Remove" class="grid h-7 w-7 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-500">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m2 0v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7"/></svg>
                                    </button>
                                </form>
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-gray-200 px-3 py-4 text-center text-sm text-gray-400">No options yet.</p>
                        @endforelse
                    </div>

                    <form method="POST" action="{{ route('admin.crm-settings.options.store') }}" data-turbo="false" class="mt-3 flex gap-2">
                        @csrf
                        <input type="hidden" name="type" value="{{ $list['type'] }}">
                        <input name="label" required maxlength="60" placeholder="Add {{ strtolower($list['title']) }}…"
                               class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        <button type="submit" class="shrink-0 rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
                    </form>
                </div>
            @endforeach

            {{-- Note --}}
            <div class="rounded-xl border border-dashed border-gray-200 p-5 md:col-span-2">
                <p class="text-xs text-[var(--color-muted)]">
                    <strong class="text-[var(--color-heading)]">Lead Quality</strong> (New / Qualified / Unqualified) is fixed by the workflow.
                    The <strong class="text-[var(--color-heading)]">Product</strong> dropdown shows every product from the
                    <a href="{{ route('admin.products.index') }}" class="font-semibold text-[var(--color-primary)] hover:underline">Products module</a>
                    plus the extra ones you add above.
                </p>
            </div>
        </div>
    </div>
@endsection
