@php $me = auth()->user(); @endphp
<div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
    @if ($items->isEmpty())
        <div class="grid place-items-center px-6 py-12 text-center">
            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1Z"/></svg>
            </span>
            <p class="mt-3 text-sm font-semibold text-[var(--color-heading)]">{{ $emptyTitle }}</p>
            <p class="mt-1 text-sm text-[var(--color-muted)]">{{ $emptyHint }}</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                        <th class="px-5 py-3 font-semibold">Image</th>
                        <th class="px-5 py-3 font-semibold">Schedule</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $promotion)
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/60">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <img src="{{ \App\Http\Resources\ProductResource::media($promotion->image) }}" class="h-10 w-24 rounded-lg border border-gray-100 object-cover" alt="">
                                    <p class="text-xs text-gray-400">by {{ optional($promotion->creator)->name ?? '—' }} · {{ $promotion->created_at->format('d M Y') }}</p>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-[var(--color-muted)]">
                                {{ optional($promotion->starts_at)->format('d M Y') ?? 'Anytime' }}
                                &rarr;
                                {{ optional($promotion->ends_at)->format('d M Y') ?? 'No end date' }}
                            </td>
                            <td class="px-5 py-3">
                                @if ($promotion->isLive())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Live now</span>
                                @elseif ($promotion->isPublished())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-600"><span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>Scheduled</span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-600"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Draft</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    @if ($me->hasPermission('promotion.publish'))
                                        <form method="POST" action="{{ route('admin.promotions.publish', $promotion) }}">
                                            @csrf
                                            <select onchange="this.form.submit()" class="h-9 rounded-lg border-gray-200 text-xs font-medium focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                                                <option value="draft" @selected(! $promotion->isPublished())>Draft</option>
                                                <option value="published" @selected($promotion->isPublished())>Published</option>
                                            </select>
                                        </form>
                                    @endif
                                    @if ($me->hasPermission('promotion.edit'))
                                        <a href="{{ route('admin.promotions.edit', $promotion) }}" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="Edit">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                        </a>
                                    @endif
                                    @if ($me->hasPermission('promotion.delete'))
                                        <x-admin.del-button :action="route('admin.promotions.destroy', $promotion)" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
