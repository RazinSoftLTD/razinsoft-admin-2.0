@extends('admin.layouts.app')
@section('title', 'CRM Settings')

@section('content')
    <div class="mb-6">
        <h1 class="text-lg font-bold text-[var(--color-heading)]">CRM Settings</h1>
        <p class="text-sm text-[var(--color-muted)]">Configure the option lists used across the CRM — Lead forms and the Deals pipeline.</p>
    </div>

    @if (session('status'))
        <div data-toast class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div data-toast class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    @php
        $mayLeads = auth()->user()->hasPermission('leads.settings');
        $mayDeals = auth()->user()->hasPermission('deals.settings');
        $mayClients = auth()->user()->hasPermission('clients.settings');

        $lists = array_values(array_filter([
            $mayLeads ? ['tab' => 'leads', 'type' => 'source', 'title' => 'Lead Sources', 'hint' => 'Where the lead came from.', 'items' => $sources] : null,
            $mayLeads ? ['tab' => 'leads', 'type' => 'department', 'title' => 'Lead Departments', 'hint' => 'Which team handles the lead.', 'items' => $departments] : null,
            $mayLeads ? ['tab' => 'leads', 'type' => 'product', 'title' => 'Products', 'hint' => 'Extra products — added to the Products-module list.', 'items' => $products] : null,
            $mayDeals ? ['tab' => 'deals', 'type' => 'deal_stage', 'title' => 'Deal Stages', 'hint' => 'Pipeline columns on the Deals board (Won / Lost are fixed).', 'items' => $stages] : null,
        ]));
    @endphp

    @php
        $u = auth()->user();
        // A tab appears only for someone who may edit what is on it.
        $tabs = array_filter([
            'leads' => $u->hasPermission('leads.settings') ? 'Leads' : null,
            'deals' => $u->hasPermission('deals.settings') ? 'Deals' : null,
            'clients' => $u->hasPermission('clients.settings') ? 'Clients' : null,
            'catalog' => $u->hasPermission('product_categories.view') ? 'Product Categories' : null,
        ]);
        $openTab = in_array(request('tab'), array_keys($tabs), true) ? request('tab') : array_key_first($tabs);
        $mayAddCat = $u->hasPermission('product_categories.create');
        $mayEditCat = $u->hasPermission('product_categories.edit');
        $mayDeleteCat = $u->hasPermission('product_categories.delete');
    @endphp

    {{-- The open tab lives in the URL, so a save that reloads the page comes back to the tab you
         were on instead of dropping you at the first one. --}}
    <div x-data="{
             tab: @js($openTab),
             go(t) { this.tab = t; const u = new URL(location); u.searchParams.set('tab', t); history.replaceState({}, '', u); },
         }" class="rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="flex gap-1 border-b border-gray-100 px-4 pt-3">
            @foreach ($tabs as $key => $label)
                <button type="button" @click="go('{{ $key }}')"
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

            {{-- Client loyalty / priority labels --}}
            @if ($mayClients)
            <div x-show="tab === 'clients'" x-cloak class="rounded-xl border border-gray-100 bg-gray-50/50 p-5 md:col-span-2">
                <div class="mb-3">
                    <h2 class="text-sm font-bold text-[var(--color-heading)]">Client Labels</h2>
                    <p class="text-xs text-[var(--color-muted)]">Loyalty / priority tiers selectable when adding a client (Regular, Gold, Platinum…). The short description explains what each tier means.</p>
                </div>

                <div class="space-y-2">
                    @forelse ($clientLabels as $label)
                        <div x-data="{ edit: false }" class="rounded-lg border border-gray-100 bg-white px-3 py-2.5">
                            {{-- Display --}}
                            <div x-show="!edit" class="flex items-start justify-between gap-2">
                                <div>
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold {{ $label->badgeClass() }}">{{ $label->name }}</span>
                                    @if ($label->description)<p class="mt-1 text-xs text-[var(--color-muted)]">{{ $label->description }}</p>@endif
                                </div>
                                <div class="flex shrink-0 items-center gap-0.5">
                                    <button type="button" @click="edit = true" title="Edit" class="grid h-7 w-7 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </button>
                                    <form method="POST" action="{{ route('admin.crm-settings.client-labels.destroy', $label) }}" data-turbo="false" onsubmit="return confirm('Remove label “{{ $label->name }}”?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Remove" class="grid h-7 w-7 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-500">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m2 0v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            {{-- Inline edit --}}
                            <form x-show="edit" x-cloak method="POST" action="{{ route('admin.crm-settings.client-labels.update', $label) }}" data-turbo="false" class="space-y-2">
                                @csrf @method('PATCH')
                                <div class="flex gap-2">
                                    <input name="name" value="{{ $label->name }}" required maxlength="40" placeholder="Name" class="h-9 w-40 rounded-lg border border-gray-200 px-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                    <select name="color" class="h-9 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                                        @foreach ($labelColors as $c)<option value="{{ $c }}" @selected($label->color === $c)>{{ ucfirst($c) }}</option>@endforeach
                                    </select>
                                </div>
                                <input name="description" value="{{ $label->description }}" maxlength="255" placeholder="Short meaning…" class="h-9 w-full rounded-lg border border-gray-200 px-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                <div class="flex gap-2">
                                    <button type="submit" class="rounded-lg bg-[var(--color-primary)] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save</button>
                                    <button type="button" @click="edit = false" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                                </div>
                            </form>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-gray-200 px-3 py-4 text-center text-sm text-gray-400">No labels yet.</p>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('admin.crm-settings.client-labels.store') }}" data-turbo="false" class="mt-4 grid gap-2 border-t border-gray-100 pt-4 sm:grid-cols-[10rem_9rem_1fr_auto]">
                    @csrf
                    <input name="name" required maxlength="40" placeholder="Label name" class="h-10 rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    <select name="color" class="h-10 rounded-lg border border-gray-200 bg-white px-2 text-sm">
                        @foreach ($labelColors as $c)<option value="{{ $c }}">{{ ucfirst($c) }}</option>@endforeach
                    </select>
                    <input name="description" maxlength="255" placeholder="Short meaning (optional)" class="h-10 rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    <button type="submit" class="h-10 shrink-0 rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
                </form>
            </div>

            @endif

            {{-- Product categories — edited in place over fetch.
                 Every add used to be a form post: the page reloaded, the tab reset to Leads and
                 the cursor was gone, so adding a handful meant scrolling back four times. Now the
                 list updates where it stands and the cursor stays in the box you are typing in. --}}
            @if (isset($tabs['catalog']))
            <div x-show="tab === 'catalog'" x-cloak class="rounded-xl border border-gray-100 bg-gray-50/50 p-5 md:col-span-2"
                 x-data="productCategories(@js($productCategories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'children' => $c->children->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()])->values()))">
                <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-[var(--color-heading)]">Product Categories</h2>
                        <p class="text-xs text-[var(--color-muted)]">Create a category and its sub-categories once — Leads, Deals and Clients all choose from this same list. Renaming one updates every record already using it.</p>
                    </div>
                    <span x-show="note" x-cloak x-text="note"
                          :class="noteBad ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-700'"
                          class="shrink-0 rounded-lg px-2.5 py-1.5 text-xs font-semibold"></span>
                </div>

                <div class="space-y-3">
                    <template x-for="cat in cats" :key="cat.id">
                        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white">
                            <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 px-4 py-3">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/></svg>
                                </span>

                                <span x-show="editing !== 'c' + cat.id" class="flex min-w-0 flex-1 items-center gap-2">
                                    <span class="truncate text-sm font-bold text-[var(--color-heading)]" x-text="cat.name"></span>
                                    <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-500" x-text="cat.children.length + ' sub'"></span>
                                </span>

                                <form x-data="{}" x-show="editing === 'c' + cat.id" x-cloak
                                      x-effect="if (editing === 'c' + cat.id) $nextTick(() => { $refs.box.focus(); $refs.box.select() })"
                                      @submit.prevent="rename(cat, $refs.box.value)" class="flex min-w-0 flex-1 items-center gap-2">
                                    <input x-ref="box" :value="cat.name" required maxlength="80" @keydown.escape="editing = null"
                                           class="h-9 min-w-0 flex-1 rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                    <button class="shrink-0 rounded-lg bg-[var(--color-primary)] px-3 py-1.5 text-xs font-semibold text-white">Save</button>
                                    <button type="button" @click="editing = null" class="shrink-0 text-xs font-semibold text-gray-400 hover:text-[var(--color-heading)]">Cancel</button>
                                </form>

                                <div x-show="editing !== 'c' + cat.id" class="flex shrink-0 items-center gap-1">
                                    @if ($mayEditCat)
                                        <button type="button" @click="startEdit('c' + cat.id)" title="Rename category" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                        </button>
                                    @endif
                                    @if ($mayDeleteCat)
                                        <button type="button" @click="destroy(cat)" title="Remove category" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <div class="p-4">
                                <div x-show="cat.children.length" class="mb-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    <template x-for="sub in cat.children" :key="sub.id">
                                        <div class="flex items-center gap-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3 py-1.5">
                                            <span x-show="editing !== 's' + sub.id" class="min-w-0 flex-1 truncate text-sm text-[var(--color-heading)]" x-text="sub.name"></span>

                                            <form x-data="{}" x-show="editing === 's' + sub.id" x-cloak
                                                  x-effect="if (editing === 's' + sub.id) $nextTick(() => { $refs.box.focus(); $refs.box.select() })"
                                                  @submit.prevent="rename(sub, $refs.box.value)" class="flex min-w-0 flex-1 items-center gap-1.5">
                                                <input x-ref="box" :value="sub.name" required maxlength="80" @keydown.escape="editing = null"
                                                       class="h-7 min-w-0 flex-1 rounded border border-gray-200 px-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                                <button class="shrink-0 rounded bg-[var(--color-primary)] px-2 py-1 text-[11px] font-semibold text-white">Save</button>
                                                <button type="button" @click="editing = null" title="Cancel" class="shrink-0 text-gray-400 hover:text-[var(--color-heading)]">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                                </button>
                                            </form>

                                            <div x-show="editing !== 's' + sub.id" class="flex shrink-0 items-center gap-0.5">
                                                @if ($mayEditCat)
                                                    <button type="button" @click="startEdit('s' + sub.id)" title="Rename" class="grid h-6 w-6 place-items-center rounded text-gray-400 hover:bg-white hover:text-[var(--color-heading)]">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                                    </button>
                                                @endif
                                                @if ($mayDeleteCat)
                                                    <button type="button" @click="destroy(sub, cat)" title="Remove" class="grid h-6 w-6 place-items-center rounded text-gray-400 hover:bg-white hover:text-red-600">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <p x-show="!cat.children.length" class="mb-3 text-xs text-gray-400">No sub-categories yet — this category can be picked on its own.</p>

                                @if ($mayAddCat)
                                    {{-- Enter adds and clears, cursor stays put: type, Enter, type, Enter. --}}
                                    <form x-data="{}" @submit.prevent="add($refs.box, cat)" class="flex items-center gap-2" style="max-width:22rem">
                                        <input x-ref="box" required maxlength="80" placeholder="Add sub-category…" :disabled="busy"
                                               class="h-9 min-w-0 flex-1 rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                        <button :disabled="busy" class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-[var(--color-heading)] hover:bg-gray-50 hover:text-[var(--color-primary)]">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </template>

                    <p x-show="!cats.length" x-cloak class="rounded-xl border border-dashed border-gray-200 py-8 text-center text-sm text-gray-400">No product categories yet — add the first one below.</p>
                </div>

                @if ($mayAddCat)
                    <form @submit.prevent="add($refs.newCat, null)" class="mt-3 flex items-center gap-2">
                        <input x-ref="newCat" required maxlength="80" placeholder="Add product category…" :disabled="busy"
                               class="h-10 flex-1 rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        <button :disabled="busy" class="shrink-0 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add Category</button>
                    </form>
                @endif
            </div>

            @endif

            {{-- Leads note --}}
            @if ($mayLeads)
            <div x-show="tab === 'leads'" x-cloak class="rounded-xl border border-dashed border-gray-200 p-5 md:col-span-2">
                <p class="text-xs text-[var(--color-muted)]">
                    <strong class="text-[var(--color-heading)]">Lead Quality</strong> (New / Qualified / Unqualified) is fixed by the workflow.
                    The <strong class="text-[var(--color-heading)]">Product</strong> dropdown also shows every product from the
                    <a href="{{ route('admin.products.index') }}" class="font-semibold text-[var(--color-primary)] hover:underline">Products module</a>.
                </p>
            </div>
            @endif
        </div>
    </div>

    <script>
        function productCategories(initial) {
            return {
                cats: initial,
                editing: null,
                busy: false,
                note: '',
                noteBad: false,

                say(text, bad = false) {
                    this.note = text;
                    this.noteBad = bad;
                    clearTimeout(this._noteTimer);
                    this._noteTimer = setTimeout(() => { this.note = ''; }, 4000);
                },

                async call(url, method, body) {
                    this.busy = true;
                    try {
                        const res = await fetch(url, {
                            method: 'POST',   // spoofed below — PATCH/DELETE through a form-safe POST
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ ...(body || {}), ...(method === 'POST' ? {} : { _method: method }) }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok) { this.say(data.message || ('Could not save (HTTP ' + res.status + ').'), true); return null; }
                        if (data.categories) this.cats = data.categories;
                        this.say(data.message || 'Saved.');
                        return data;
                    } catch (e) {
                        this.say('Could not reach the server: ' + e.message, true);
                        return null;
                    } finally {
                        this.busy = false;
                    }
                },

                // Add and stay: the box clears, keeps focus, and the list grows in place.
                async add(input, parent) {
                    const name = (input.value || '').trim();
                    if (!name) return;
                    const ok = await this.call(@js(route('admin.crm-settings.product-categories.store')), 'POST', { name, parent_id: parent?.id ?? null });
                    if (ok) { input.value = ''; }
                    input.focus();
                },

                startEdit(key) { this.editing = key; },

                async rename(node, name) {
                    name = (name || '').trim();
                    if (!name || name === node.name) { this.editing = null; return; }
                    const ok = await this.call(@js(url('admin/crm-settings/product-categories')) + '/' + node.id, 'PATCH', { name });
                    if (ok) this.editing = null;
                },

                async destroy(node, parent) {
                    const what = parent ? node.name : node.name + (node.children?.length ? ' and its sub-categories' : '');
                    if (!confirm('Remove “' + what + '”?\n\nRecords already using it keep the old value.')) return;
                    await this.call(@js(url('admin/crm-settings/product-categories')) + '/' + node.id, 'DELETE');
                },
            };
        }
    </script>
@endsection
