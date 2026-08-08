{{--
    The permission matrix, shared by a ROLE and by one person's overrides.

    Both pages answer the same question — how much of each module may someone touch — so they
    read the same way. The only difference is what a blank choice means: for a role it is plain
    "No access"; for a person it is "Inherit", following whatever the role says today.

    Expects:
      $mode     'role' | 'staff'
      $current  map of key => stored value ('' / missing = inherit, for staff)
      $roleFor  callable(key): string   the role's scope, staff mode only (for the Inherit label)
      $field    form field name prefix — 'permissions' or 'override'
--}}
@php
    use App\Support\Permissions;
    $crud = ['view' => 'View', 'create' => 'Add', 'edit' => 'Update', 'delete' => 'Delete'];
    $crudHelp = ['view' => 'Can open and see this module', 'create' => 'Can add new records', 'edit' => 'Can change existing records', 'delete' => 'Can remove records'];
    $staffMode = ($mode ?? 'role') === 'staff';
    $scopeOf = fn ($key) => $current[$key] ?? ($staffMode ? '' : 'none');
    // What the row itself can be found by. Section names are deliberately NOT in here — they
    // live on the section chips, so a search for one can tell it must open that panel.
    $haystack = function (string $group, string $mod, array $cfg) use ($crud) {
        $words = [$group, $cfg['label'], str_replace('_', ' ', $mod)];
        foreach (array_intersect($cfg['actions'], array_keys($crud)) as $a) {
            $words[] = Permissions::actionLabel($a);
        }

        return strtolower(implode(' ', $words));
    };
@endphp

<div class="mt-6 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm" id="perm-matrix" @class(['is-staff' => $staffMode])>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
        <div>
            <p class="text-sm font-bold text-[var(--color-heading)]">Permissions</p>
            <p class="mt-0.5 text-xs text-[var(--color-muted)]">
                @if ($staffMode)
                    Each permission follows the role unless you set it here for this person only. Hover any box for a plain explanation.
                @else
                    For each module, choose <b>how much</b> this role can see and do. Hover any box for a plain explanation.
                @endif
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-[var(--color-muted)]">
                @if ($staffMode)
                    <span class="inline-flex items-center gap-1.5" title="Follows the role — change the role and this person follows."><span class="h-2.5 w-2.5 rounded-full bg-gray-300"></span><b class="font-semibold text-gray-500">Inherit</b> · follows the role</span>
                @else
                    <span class="inline-flex items-center gap-1.5" title="No access to this at all."><span class="h-2.5 w-2.5 rounded-full bg-gray-300"></span><b class="font-semibold text-gray-500">No access</b></span>
                @endif
                <span class="inline-flex items-center gap-1.5" title="Only records assigned to this person."><span class="h-2.5 w-2.5 rounded-full bg-blue-400"></span><b class="font-semibold text-blue-600">Their own</b> · assigned to them</span>
                <span class="inline-flex items-center gap-1.5" title="Only records this person created."><span class="h-2.5 w-2.5 rounded-full bg-indigo-400"></span><b class="font-semibold text-indigo-600">They created</b> · they added</span>
                <span class="inline-flex items-center gap-1.5" title="All records, from everyone."><span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span><b class="font-semibold text-emerald-600">Everyone's</b> · all records</span>
            </div>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="mr-1 text-xs text-[var(--color-muted)]">Set all</span>
            <button type="button" data-bulk="all" title="Give full access to everything" class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Everyone's</button>
            <button type="button" data-bulk="owned" title="Limit to their own records everywhere" class="rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">Their own</button>
            @if ($staffMode)
                <button type="button" data-bulk="" title="Follow the role everywhere — clears every override" class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-500 hover:bg-gray-50">Follow role</button>
            @else
                <button type="button" data-bulk="none" title="Remove all access" class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-500 hover:bg-gray-50">No access</button>
            @endif
        </div>
    </div>

    {{-- Search. Typing narrows the matrix to matching modules; a match inside a module's
         sections opens that panel, so the thing you searched for is actually on screen. --}}
    <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 bg-gray-50/70 px-5 py-3">
        <div class="relative min-w-[240px] flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
            <input id="perm-search" type="search" autocomplete="off" placeholder="Search a module or permission — “razin”, “invoice delete”, “whatsapp”…"
                   class="h-10 w-full rounded-lg border border-gray-200 pl-9 pr-3 text-sm">
        </div>
        <span id="perm-search-count" class="text-xs text-[var(--color-muted)]"></span>
        <button type="button" id="perm-search-clear" class="hidden rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-500 hover:bg-gray-50">Clear</button>
    </div>
    <p id="perm-search-empty" class="hidden px-5 py-8 text-center text-sm text-gray-400">Nothing matches that. Try a module name like “clients”, or an action like “delete”.</p>

    @foreach (Permissions::grouped() as $group => $modules)
        <div class="border-b border-gray-100 last:border-0" data-perm-group>
            <p class="bg-gray-50/70 px-5 py-2 text-[11px] font-bold uppercase tracking-wide text-gray-400">{{ $group }}</p>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <colgroup>
                        <col class="w-[26%]">
                        @foreach ($crud as $l)<col class="w-[15%]">@endforeach
                        <col class="w-[14%]">
                    </colgroup>
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wide text-gray-400">
                            <th class="px-5 py-2 font-semibold">Module</th>
                            @foreach ($crud as $act => $label)<th class="px-3 py-2 font-semibold"><span class="cursor-help border-b border-dotted border-gray-300" title="{{ $crudHelp[$act] ?? '' }}">{{ $label }}</span></th>@endforeach
                            <th class="px-3 py-2 text-right font-semibold"><span class="cursor-help border-b border-dotted border-gray-300" title="Extra sub-areas inside this module (e.g. a client's Invoices or Notes tab)">Sections</span></th>
                        </tr>
                    </thead>
                    @foreach ($modules as $mod => $cfg)
                        @php $extras = Permissions::extraActions($mod); @endphp
                        <tbody x-data="{ more: false }" class="border-t border-gray-50 align-middle"
                               data-perm-row data-search="{{ $haystack($group, $mod, $cfg) }}">
                        <tr class="transition hover:bg-gray-50/60">
                            <td class="px-5 py-2.5 font-semibold text-[var(--color-heading)]">{{ $cfg['label'] }}</td>
                            @foreach (array_keys($crud) as $act)
                                <td class="px-3 py-2.5">
                                    @if (in_array($act, $cfg['actions'], true))
                                        @php $key = "$mod.$act"; $cur = $scopeOf($key); @endphp
                                        <select name="{{ $field }}[{{ $key }}]" data-perm
                                                title="{{ $staffMode && $cur === '' ? 'Follows the role setting.' : Permissions::optionHelp($mod, $act, $cur ?: 'none') }}" class="perm-select">
                                            @if ($staffMode)
                                                <option value="" @selected($cur === '')>Inherit ({{ Permissions::optionLabel($mod, $act, $roleFor($key)) }})</option>
                                            @endif
                                            @foreach (Permissions::scopesFor($mod, $act) as $scope)
                                                <option value="{{ $scope }}" @selected($cur === $scope)>{{ Permissions::optionLabel($mod, $act, $scope) }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="text-gray-200">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-3 py-2.5 text-right">
                                @if ($extras)
                                    <button type="button" @click="more = !more" data-sections-toggle
                                            class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:bg-indigo-50"
                                            :class="more && 'bg-indigo-50'">
                                        <span x-text="more ? 'Hide' : '{{ count($extras) }}'"></span>
                                        <span x-show="!more">section{{ count($extras) === 1 ? '' : 's' }}</span>
                                        <svg class="h-3.5 w-3.5 transition" :class="more && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                                    </button>
                                @else
                                    <span class="text-gray-200">—</span>
                                @endif
                            </td>
                        </tr>
                        @if ($extras)
                            <tr x-show="more" x-cloak>
                                <td colspan="6" class="px-5 pb-4 pt-0">
                                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4">
                                        <p class="mb-3 text-[11px] font-bold uppercase tracking-wide text-gray-400">{{ $cfg['label'] }} · sections</p>
                                        <div class="grid grid-cols-[repeat(auto-fill,minmax(200px,1fr))] gap-3">
                                            @foreach ($extras as $act)
                                                @php $key = "$mod.$act"; $cur = $scopeOf($key); @endphp
                                                <label class="flex items-center justify-between gap-2 rounded-lg border border-gray-100 bg-white px-3 py-2" data-perm-extra data-search="{{ strtolower(Permissions::actionLabel($act).' '.$cfg['label']) }}">
                                                    <span class="text-xs font-semibold text-[var(--color-heading)]">{{ Permissions::actionLabel($act) }}</span>
                                                    <select name="{{ $field }}[{{ $key }}]" data-perm
                                                            title="{{ $staffMode && $cur === '' ? 'Follows the role setting.' : Permissions::optionHelp($mod, $act, $cur ?: 'none') }}" class="perm-select">
                                                        @if ($staffMode)
                                                            <option value="" @selected($cur === '')>Inherit ({{ Permissions::optionLabel($mod, $act, $roleFor($key)) }})</option>
                                                        @endif
                                                        @foreach (Permissions::scopesFor($mod, $act) as $scope)
                                                            <option value="{{ $scope }}" @selected($cur === $scope)>{{ Permissions::optionLabel($mod, $act, $scope) }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                        </tbody>
                    @endforeach
                </table>
            </div>
        </div>
    @endforeach
</div>

<style>
    /* Compact, consistent scope dropdowns — colour reflects the selected scope
       so the whole matrix reads at a glance. Painted on load/change via JS. */
    .perm-select {
        height: 2.15rem;
        width: 100%;
        max-width: 9.5rem;
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
        padding: 0 1.8rem 0 0.6rem;
        font-size: 0.75rem;
        font-weight: 600;
        background-color: #fff;
        color: #6b7280;
        transition: background-color .15s, color .15s, border-color .15s;
    }
    .perm-select:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 1px var(--color-primary); }
    .perm-select.s-owned { background-color: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    .perm-select.s-added { background-color: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
    .perm-select.s-both  { background-color: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
    .perm-select.s-all   { background-color: #ecfdf5; color: #047857; border-color: #a7f3d0; }
    .perm-select.s-none  { color: #9ca3af; }
    /* "Inherit (Everyone's)" needs the room the role page's plain scope names do not. */
    #perm-matrix.is-staff .perm-select { max-width: 13rem; }
    #perm-matrix.is-staff table { min-width: 940px; }
    /* Inherit (staff only) reads as "not set here" — quiet, dotted, clearly not a decision. */
    .perm-select.s-inherit { color: #9ca3af; border-style: dashed; }
    mark.perm-hit { background: #fef08a; border-radius: .2rem; padding: 0 .1rem; }
</style>

<script>
    (function () {
        const order = ['none', 'owned', 'added', 'both', 'all'];
        const HELP = { none: 'No access to this at all.', owned: 'Only records assigned to this person.', added: 'Only records this person created.', both: 'Records assigned to OR created by this person.', all: "All records — from everyone." };
        const HELP_SIMPLE = { none: 'This role cannot do this.', all: 'This role can do this.' };
        const paint = (sel) => {
            sel.classList.remove('s-none', 's-owned', 's-added', 's-both', 's-all', 's-inherit');
            sel.classList.add('s-' + (sel.value || 'inherit'));
            if (sel.value === '') { sel.title = 'Follows the role setting.'; return; }
            const simple = !Array.from(sel.options).some(o => o.value === 'owned' || o.value === 'added');
            sel.title = (simple ? HELP_SIMPLE : HELP)[sel.value] || '';
        };
        const selects = document.querySelectorAll('select[data-perm]');
        selects.forEach((sel) => { paint(sel); sel.addEventListener('change', () => paint(sel)); });

        document.querySelectorAll('[data-bulk]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const want = btn.dataset.bulk;
                // Only what is currently on screen, so a search narrows the bulk action too.
                document.querySelectorAll('[data-perm-row]:not([hidden]) select[data-perm]').forEach((sel) => {
                    const opts = Array.from(sel.options).map((o) => o.value);
                    let choice = opts.includes(want) ? want : null;
                    if (choice === null) {
                        const wi = order.indexOf(want);
                        for (let i = wi; i >= 0; i--) { if (opts.includes(order[i])) { choice = order[i]; break; } }
                        if (choice === null) choice = opts.includes('') ? '' : 'none';
                    }
                    sel.value = choice;
                    paint(sel);
                });
            });
        });

        // ---- Search: every word must appear somewhere in the row (module, group or action),
        // so "invoice delete" narrows to Invoices and "razin" finds Razin AI.
        const input = document.getElementById('perm-search');
        const count = document.getElementById('perm-search-count');
        const clear = document.getElementById('perm-search-clear');
        const empty = document.getElementById('perm-search-empty');
        const rows = Array.from(document.querySelectorAll('[data-perm-row]'));
        const groups = Array.from(document.querySelectorAll('[data-perm-group]'));

        const run = () => {
            const q = (input.value || '').trim().toLowerCase();
            const terms = q.split(/\s+/).filter(Boolean);
            let shown = 0;

            rows.forEach((row) => {
                const hay = row.dataset.search || '';
                const extras = Array.from(row.querySelectorAll('[data-perm-extra]'));
                const extraHay = extras.map((e) => e.dataset.search || '').join(' ');
                const hit = terms.every((t) => hay.includes(t) || extraHay.includes(t));
                row.hidden = !hit;
                if (hit) shown++;

                // If the match is only inside the sections panel, open it so it can be seen.
                if (hit && terms.length && extras.length) {
                    const onlyInside = terms.some((t) => !hay.includes(t) && extraHay.includes(t));
                    const toggle = row.querySelector('[data-sections-toggle]');
                    if (onlyInside && toggle && !row.dataset.autoOpened) {
                        toggle.click();
                        row.dataset.autoOpened = '1';
                    }
                }
            });

            // A group with nothing left in it should not sit there as a lone heading.
            groups.forEach((g) => {
                g.hidden = !Array.from(g.querySelectorAll('[data-perm-row]')).some((r) => !r.hidden);
            });

            const searching = terms.length > 0;
            clear.classList.toggle('hidden', !searching);
            empty.classList.toggle('hidden', !(searching && shown === 0));
            count.textContent = searching ? `${shown} module${shown === 1 ? '' : 's'}` : '';
        };

        input.addEventListener('input', run);
        input.addEventListener('keydown', (e) => { if (e.key === 'Escape') { input.value = ''; run(); } });
        clear.addEventListener('click', () => { input.value = ''; run(); input.focus(); });
    })();
</script>
