@extends('admin.layouts.app')
@section('title', 'Ticket Settings')

@php
    $tabs = ['agents' => 'Ticket Agents', 'types' => 'Ticket Category', 'templates' => 'Reply Templates'];
    $me = auth()->id();
@endphp

@section('content')
    <div x-data="{ tab: '{{ $tab }}', tpl: { open: false, id: null, title: '', body: '' } }">

        {{-- Add New Agents (agents tab) --}}
        <div x-show="tab === 'agents'" class="mb-4">
            <form method="POST" action="{{ route('admin.tickets.settings.agents.store') }}" class="inline-flex items-center gap-2">
                @csrf
                <select name="user_id" required class="h-10 w-56 rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    <option value="">Select employee…</option>
                    @foreach ($addableEmployees as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach
                </select>
                <button class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add New Agent
                </button>
            </form>
        </div>

        <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
            {{-- Tab nav --}}
            <div class="flex gap-1 overflow-x-auto border-b border-gray-200 px-4">
                @foreach ($tabs as $key => $label)
                    <button @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]'" class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-semibold">{{ $label }}</button>
                @endforeach
            </div>

            {{-- ===== Ticket Agents ===== --}}
            <div x-show="tab === 'agents'" class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Name</th>
                            <th class="px-5 py-3 font-semibold">Group</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($agents as $agent)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        @if ($agent->user->photo_url)
                                            <img src="{{ $agent->user->photo_url }}" alt="" class="h-9 w-9 rounded-full object-cover">
                                        @else
                                            <span class="grid h-9 w-9 place-items-center rounded-full bg-[var(--color-primary-soft)] text-xs font-bold text-[var(--color-primary)]">{{ strtoupper(substr($agent->user->name, 0, 1)) }}</span>
                                        @endif
                                        <span class="leading-tight">
                                            <span class="flex items-center gap-2 font-semibold text-[var(--color-heading)]">{{ $agent->user->name }}@if ($agent->user_id === $me)<span class="rounded bg-[var(--color-primary-soft)] px-1.5 text-[10px] font-semibold text-[var(--color-primary)]">It's you</span>@endif</span>
                                            @if ($agent->user->designation)<span class="block text-xs text-[var(--color-muted)]">{{ $agent->user->designation->name }}</span>@endif
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    @include('admin.tickets._checkbox-select', [
                                        'action' => route('admin.tickets.settings.agents.update', $agent),
                                        'name' => 'group_ids',
                                        'syncFlag' => 'sync_groups',
                                        'placeholder' => 'Select groups…',
                                        'summary' => $agent->groups->pluck('name')->join(', '),
                                        'empty' => 'No groups yet.',
                                        'options' => $groups->map(fn ($g) => ['value' => $g->id, 'label' => $g->name, 'checked' => $agent->groups->contains($g->id)]),
                                    ])
                                </td>
                                <td class="px-5 py-3">
                                    <form method="POST" action="{{ route('admin.tickets.settings.agents.update', $agent) }}">
                                        @csrf @method('PATCH')
                                        <select name="status" onchange="this.form.submit()" class="h-9 w-32 rounded-lg border border-gray-200 bg-white px-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                            <option value="enabled" @selected($agent->status === 'enabled')>Enabled</option>
                                            <option value="disabled" @selected($agent->status === 'disabled')>Disabled</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.tickets.settings.agents.destroy', $agent) }}" onsubmit="return confirm('Remove this agent?')">
                                        @csrf @method('DELETE')
                                        <button class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-semibold text-red-600 hover:bg-red-50">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-12 text-center text-gray-400">No agents yet — add one above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <p class="px-5 py-3 text-xs text-gray-400">Tip: click the Group box and check as many as you need — it saves when you click away.</p>
            </div>

            {{-- ===== Ticket Category ===== --}}
            <div x-show="tab === 'types'" x-cloak class="p-5">
                <form method="POST" action="{{ route('admin.tickets.settings.types.store') }}" class="mb-4 flex max-w-md gap-2">
                    @csrf
                    <input name="name" required placeholder="e.g. Technical Support" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    <button class="shrink-0 rounded-lg bg-[var(--color-primary)] px-5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
                </form>
                @error('name')<p class="mb-2 text-xs text-red-600">{{ $message }}</p>@enderror
                <div class="overflow-hidden rounded-lg border border-gray-100">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400"><tr><th class="px-5 py-3 font-semibold">Category</th><th class="px-5 py-3 font-semibold">Ticket Agents</th><th class="px-5 py-3 text-right font-semibold">Action</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($types as $t)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3">
                                        <form method="POST" action="{{ route('admin.tickets.settings.types.update', $t) }}">
                                            @csrf @method('PATCH')
                                            <input name="name" value="{{ $t->name }}" onblur="this.value !== this.defaultValue && this.value.trim() && this.form.submit()" class="w-48 rounded-lg border border-transparent bg-transparent px-2 py-1 font-medium text-[var(--color-heading)] hover:border-gray-200 focus:border-[var(--color-primary)] focus:bg-white focus:outline-none">
                                        </form>
                                    </td>
                                    <td class="px-5 py-3">
                                        @include('admin.tickets._checkbox-select', [
                                            'action' => route('admin.tickets.settings.types.update', $t),
                                            'name' => 'agent_ids',
                                            'syncFlag' => 'sync_agents',
                                            'placeholder' => 'Select agents…',
                                            'summary' => $t->agents->map(fn ($a) => $a->user->name)->join(', '),
                                            'empty' => 'No agents yet — add one in the Ticket Agents tab.',
                                            'options' => $agents->map(fn ($a) => ['value' => $a->id, 'label' => $a->user->name, 'checked' => $t->agents->contains($a->id)]),
                                        ])
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <form method="POST" action="{{ route('admin.tickets.settings.types.destroy', $t) }}" onsubmit="return confirm('Delete?')">
                                            @csrf @method('DELETE')<button class="text-sm font-semibold text-red-600 hover:underline">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-5 py-8 text-center text-gray-400">No categories yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="px-1 py-3 text-xs text-gray-400">Only agents assigned to a category can see its tickets. A category with no agents assigned follows the normal Tickets permission instead.</p>
            </div>

            {{-- ===== Reply Templates ===== --}}
            <div x-show="tab === 'templates'" x-cloak class="p-5">
                <form method="POST" action="{{ route('admin.tickets.settings.templates.store') }}" class="mb-5 space-y-3 rounded-lg border border-gray-100 bg-gray-50/50 p-4">
                    @csrf
                    <input name="title" required placeholder="Template title (e.g. Greeting)" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    <textarea name="body" rows="3" required placeholder="Template message…" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none"></textarea>
                    @error('title')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('body')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add Template</button>
                </form>

                <div class="space-y-3">
                    @forelse ($templates as $tpl)
                        <div class="rounded-lg border border-gray-100 bg-white p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-[var(--color-heading)]">{{ $tpl->title }}</p>
                                    <div class="prose prose-sm mt-1 max-w-none text-[var(--color-muted)]">{!! $tpl->body !!}</div>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <button type="button" @click="tpl = { open: true, id: {{ $tpl->id }}, title: @js($tpl->title), body: @js(strip_tags($tpl->body)) }" class="text-sm font-semibold text-[var(--color-primary)] hover:underline">Edit</button>
                                    <form method="POST" action="{{ route('admin.tickets.settings.templates.destroy', $tpl) }}" onsubmit="return confirm('Delete this template?')">
                                        @csrf @method('DELETE')<button class="text-sm font-semibold text-red-600 hover:underline">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="py-8 text-center text-gray-400">No reply templates yet.</p>
                    @endforelse
                </div>

                {{-- Edit template modal --}}
                <div x-show="tpl.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="tpl.open = false">
                    <form method="POST" :action="'{{ url('admin/ticket-settings/templates') }}/' + tpl.id" class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                        @csrf @method('PATCH')
                        <h3 class="text-base font-bold text-[var(--color-heading)]">Edit template</h3>
                        <input name="title" x-model="tpl.title" required class="mt-4 h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        <textarea name="body" x-model="tpl.body" rows="4" required class="mt-3 w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none"></textarea>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" @click="tpl.open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                            <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
