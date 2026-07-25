@props([
    'category' => null,
    'subCategory' => null,
    'categoryName' => 'product_category',
    'subName' => 'product_sub_category',
    'label' => 'Product Category',
])

@php
    $tree = \App\Models\ProductCategory::subMap();
    // Values saved before a category was removed still need to show, so seed the lists with them.
    $categories = collect(array_keys($tree))->when($category, fn ($c) => $c->push($category))->unique()->values();
@endphp

<div x-data="{
        map: @js($tree),
        cat: @js((string) $category),
        sub: @js((string) $subCategory),
        get subs() {
            const list = this.map[this.cat] ? [...this.map[this.cat]] : [];
            if (this.sub && !list.includes(this.sub)) list.push(this.sub);   // keep a legacy value visible
            return list;
        },
     }"
     class="grid gap-5 sm:grid-cols-2">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">{{ $label }}</label>
        <select name="{{ $categoryName }}" x-model="cat" @change="sub = ''"
                class="h-11 w-full rounded-lg border-gray-200 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
            <option value="">Select category</option>
            @foreach ($categories as $c)
                <option value="{{ $c }}">{{ $c }}</option>
            @endforeach
        </select>
        @if ($categories->isEmpty())
            <p class="mt-1 text-xs text-[var(--color-muted)]">None yet — add them in Settings &rsaquo; CRM Settings &rsaquo; Product Categories.</p>
        @endif
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Sub-category</label>
        <select name="{{ $subName }}" x-model="sub" :disabled="!subs.length"
                class="h-11 w-full rounded-lg border-gray-200 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)] disabled:bg-gray-50 disabled:text-gray-400">
            <option value="">Select sub-category</option>
            <template x-for="s in subs" :key="s">
                <option :value="s" x-text="s"></option>
            </template>
        </select>
        <p class="mt-1 text-xs text-[var(--color-muted)]" x-show="cat && !subs.length" x-cloak>This category has no sub-categories.</p>
    </div>
</div>
