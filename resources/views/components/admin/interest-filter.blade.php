@props(['name' => 'interest', 'label' => 'Interested in'])

{{-- Filter by product category. Picking a category also matches its sub-categories. --}}
<div>
    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">{{ $label }}</label>
    <select name="{{ $name }}" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
        <option value="">All categories</option>
        @foreach (\App\Models\ProductCategory::pickerOptions() as $opt)
            <option value="{{ $opt['id'] }}" @selected((string) request($name) === (string) $opt['id'])>{{ $opt['is_sub'] ? '   — '.$opt['label'] : $opt['label'] }}</option>
        @endforeach
    </select>
</div>
