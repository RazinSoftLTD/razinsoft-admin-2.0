@extends('admin.layouts.app')
@section('title', 'CRM Settings')

@section('content')
    <div class="mb-6">
        <h1 class="text-lg font-bold text-[var(--color-heading)]">CRM Settings</h1>
        <p class="text-sm text-[var(--color-muted)]">Configure the option lists used across the CRM — Lead forms and the Deals pipeline.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    @php
        $lists = [
            ['tab' => 'leads', 'type' => 'source', 'title' => 'Lead Sources', 'hint' => 'Where the lead came from.', 'items' => $sources],
            ['tab' => 'leads', 'type' => 'department', 'title' => 'Lead Departments', 'hint' => 'Which team handles the lead.', 'items' => $departments],
            ['tab' => 'leads', 'type' => 'product', 'title' => 'Products', 'hint' => 'Extra products — added to the Products-module list.', 'items' => $products],
            ['tab' => 'deals', 'type' => 'deal_stage', 'title' => 'Deal Stages', 'hint' => 'Pipeline columns on the Deals board (Won / Lost are fixed).', 'items' => $stages],
        ];
    @endphp

    <div x-data="{ tab: 'leads' }" class="rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="flex gap-1 border-b border-gray-100 px-4 pt-3">
            @foreach (['leads' => 'Leads', 'deals' => 'Deals'] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]'"
                        class="border-b-2 px-4 py-2.5 text-sm font-semibold">{{ $label }}</button>
            @endforeach
        </div>

        <div class="grid gap-6 p-6 md:grid-cols-2">
            @foreach ($lists as $list)
                <div x-show="tab === '{{ $list['tab'] }}'" x-cloak class="rounded-xl border border-gray-100 bg-gray-50/50 p-5">
                    <div class="mb-3">
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">{{ $list['title'] }}</h2>
                        <p class="text-xs text-[var(--color-muted)]">{{ $list['hint'] }}</p>
                    </div>

                    <div class="space-y-2">
                        @forelse ($list['items'] as $item)
                            <div x-data="{ edit: false }" class="rounded-lg border border-gray-100 bg-white px-3 py-2">
                                {{-- Display --}}
                                <div x-show="!edit" class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-medium text-[var(--color-heading)]">{{ $item->label }}</span>
                                    <div class="flex items-center gap-0.5">
                                        <button type="button" @click="edit = true" title="Rename" class="grid h-7 w-7 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                        </button>
                                        <form method="POST" action="{{ route('admin.crm-settings.options.destroy', $item) }}" data-turbo="false" onsubmit="return confirm('Remove “{{ $item->label }}”?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" title="Remove" class="grid h-7 w-7 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-500">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m2 0v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                {{-- Inline rename --}}
                                <form x-show="edit" x-cloak method="POST" action="{{ route('admin.crm-settings.options.update', $item) }}" data-turbo="false" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <input name="label" value="{{ $item->label }}" required maxlength="60" class="h-9 w-full rounded-lg border border-gray-200 px-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                    <button type="submit" title="Save" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[var(--color-primary)] text-white hover:bg-[var(--color-primary-hover)]">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                    </button>
                                    <button type="button" @click="edit = false" title="Cancel" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                    </button>
                                </form>
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-gray-200 px-3 py-4 text-center text-sm text-gray-400">Nothing here yet.</p>
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

            {{-- Leads note --}}
            <div x-show="tab === 'leads'" x-cloak class="rounded-xl border border-dashed border-gray-200 p-5 md:col-span-2">
                <p class="text-xs text-[var(--color-muted)]">
                    <strong class="text-[var(--color-heading)]">Lead Quality</strong> (New / Qualified / Unqualified) is fixed by the workflow.
                    The <strong class="text-[var(--color-heading)]">Product</strong> dropdown also shows every product from the
                    <a href="{{ route('admin.products.index') }}" class="font-semibold text-[var(--color-primary)] hover:underline">Products module</a>.
                </p>
            </div>
        </div>
    </div>
@endsection
