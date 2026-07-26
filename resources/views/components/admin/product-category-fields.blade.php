@props([
    'category' => null,
    'subCategory' => null,
    'categoryName' => 'product_category',
    'subName' => 'product_sub_category',
    'label' => 'Product Category',
    // Narrow hosts (the lead sidebar) stack the two selects; wide ones put them side by side.
    'stacked' => false,
])

@php
    $tree = \App\Models\ProductCategory::subMap();
    // Values saved before a category was removed still need to show, so seed the lists with them.
    $categories = collect(array_keys($tree))->when($category, fn ($c) => $c->push($category))->unique()->values();
    // The same classes x-admin.field builds its inputs from, so these two match every other field
    // on the form — the old ones set a border colour without `border`, so they had no visible box.
    $base = 'w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]';
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
     class="grid gap-5 {{ $stacked ? '' : 'sm:grid-cols-2' }}">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">{{ $label }}</label>
        <select name="{{ $categoryName }}" x-model="cat" @change="sub = ''" class="{{ $base }} h-11 bg-white">
            <option value="">Select category</option>
            @foreach ($categories as $c)
                <option value="{{ $c }}">{{ $c }}</option>
            @endforeach
        </select>
        @if ($categories->isEmpty())
            <p class="mt-1 text-xs text-gray-400">None yet — add them in Settings &rsaquo; CRM Settings &rsaquo; Product Categories.</p>
        @endif
    </div>

    {{-- Only some categories have sub-categories, so the field appears only when there is
         something to pick; a permanently disabled select was just noise. --}}
    <div x-show="subs.length" x-cloak>
        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Sub-category</label>
        <select name="{{ $subName }}" x-model="sub" class="{{ $base }} h-11 bg-white">
            <option value="">Select sub-category</option>
            <template x-for="s in subs" :key="s">
                <option :value="s" x-text="s"></option>
            </template>
        </select>
    </div>
</div>
