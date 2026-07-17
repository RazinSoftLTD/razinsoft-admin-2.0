{{-- Shared plan fields (create + edit). $plan may be null. --}}
<input type="text" name="name" value="{{ $plan->name ?? '' }}" required placeholder="Plan name (Basic / Pro / Enterprise)" class="h-9 w-full rounded-lg border-gray-200 text-sm">
<input type="text" name="tagline" value="{{ $plan->tagline ?? '' }}" placeholder="Tagline (e.g. Elevate Your Business)" class="h-9 w-full rounded-lg border-gray-200 text-sm">
<div class="flex gap-2">
    <input type="number" name="price" step="0.01" min="0" value="{{ $plan->price ?? '' }}" required placeholder="Regular $" class="h-9 w-full rounded-lg border-gray-200 text-sm">
    <input type="number" name="sale_price" step="0.01" min="0" value="{{ $plan->sale_price ?? '' }}" placeholder="Sale $ (optional)" class="h-9 w-full rounded-lg border-gray-200 text-sm">
</div>
<input type="text" name="note" value="{{ $plan->note ?? '' }}" placeholder="Note (e.g. 2 Revisions, +$200 per app)" class="h-9 w-full rounded-lg border-gray-200 text-sm">
<label class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-muted)]"><input type="checkbox" name="is_popular" value="1" @checked($plan->is_popular ?? false) class="rounded accent-[var(--color-primary)]"> Mark as Most Popular</label>
