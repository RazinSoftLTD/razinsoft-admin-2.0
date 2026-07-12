@extends('admin.layouts.app')
@section('title', 'Create Ticket')

@php
    $clientsJson = $clients->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'company' => $c->company]);
    $employeesJson = $employees->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'company' => null]);
@endphp

@section('content')
    <a href="{{ route('admin.tickets.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Tickets
    </a>

    <form method="POST" action="{{ route('admin.tickets.store') }}" enctype="multipart/form-data" id="ticket-form"
          x-data="ticketForm({ clients: {{ Illuminate\Support\Js::from($clientsJson) }}, employees: {{ Illuminate\Support\Js::from($employeesJson) }}, groupUrl: '{{ route('admin.tickets.groups.store') }}', typeUrl: '{{ route('admin.tickets.types.store') }}', csrf: '{{ csrf_token() }}' })" class="space-y-6">
        @csrf
        <input type="hidden" name="requester_type" value="client">

        {{-- ===== Ticket Details ===== --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-5 text-base font-bold text-[var(--color-heading)]">Ticket Details</h2>

            <div class="grid gap-5 lg:grid-cols-3">
                @if (auth()->user()->seesAll('tickets'))
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Requester Name <span class="text-red-500">*</span></label>
                    <select name="client_id" required class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        <option value="">--</option>
                        @foreach ($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id') == $c->id)>{{ $c->name }}{{ $c->company ? ' — '.$c->company : '' }}</option>@endforeach
                    </select>
                    @error('client_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                @endif
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Assign Group</label>
                    <div class="flex gap-2">
                        <select name="group_id" x-ref="groupSelect" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <option value="">--</option>
                            @foreach ($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                        </select>
                        <button type="button" @click="quick = { open: true, type: 'group', name: '', error: '' }" class="h-11 shrink-0 rounded-lg border border-gray-200 bg-white px-4 text-sm font-semibold hover:bg-gray-50">Add</button>
                    </div>
                </div>
            </div>

            <div class="mt-5 grid gap-5 lg:grid-cols-3">
                @if (auth()->user()->seesAll('tickets'))
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Agent</label>
                    <select name="assigned_to" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        <option value="">--</option>
                        @foreach ($agents as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                    </select>
                </div>
                @endif
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Type</label>
                    <div class="flex gap-2">
                        <select name="type_id" x-ref="typeSelect" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <option value="">--</option>
                            @foreach ($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                        </select>
                        <button type="button" @click="quick = { open: true, type: 'type', name: '', error: '' }" class="h-11 shrink-0 rounded-lg border border-gray-200 bg-white px-4 text-sm font-semibold hover:bg-gray-50">Add</button>
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Ticket Subject <span class="text-red-500">*</span></label>
                <input name="subject" value="{{ old('subject') }}" required class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                @error('subject')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="mt-5">
                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Description <span class="text-red-500">*</span></label>
                <div class="rounded-lg border border-gray-200">
                    <div id="ticket-desc" style="min-height:140px"></div>
                </div>
                <textarea name="message" id="ticket-desc-input" class="hidden">{{ old('message') }}</textarea>
                @error('message')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                <label class="mt-3 inline-flex cursor-pointer items-center gap-1.5 text-sm font-semibold text-[var(--color-primary)] hover:underline">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21.4 11.1-8.5 8.5a5 5 0 0 1-7-7l8.5-8.5a3 3 0 0 1 4.3 4.3l-8.6 8.5a1 1 0 0 1-1.4-1.4l7.8-7.8"/></svg>
                    <span id="ticket-file-name">Upload File</span>
                    <input type="file" name="attachment" class="hidden" onchange="document.getElementById('ticket-file-name').textContent = this.files[0]?.name || 'Upload File'">
                </label>
            </div>
        </div>

        {{-- ===== Other Details (collapsible) ===== --}}
        <div x-data="{ open: false }" class="rounded-xl border border-gray-100 bg-white shadow-sm">
            <button type="button" @click="open = !open" class="flex w-full items-center gap-2 px-6 py-4 text-base font-bold text-[var(--color-heading)]">
                <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                Other Details
            </button>
            <div x-show="open" x-cloak class="grid gap-5 border-t border-gray-100 px-6 py-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Priority</label>
                    <select name="priority" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        @foreach (\App\Models\Ticket::PRIORITIES as $val => $label)<option value="{{ $val }}" @selected(old('priority', 'medium') === $val)>{{ $label }}</option>@endforeach
                    </select>
                </div>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="flex gap-3">
            <button class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-6 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m5 12 4 4L19 7"/></svg> Save
            </button>
            <a href="{{ route('admin.tickets.index') }}" class="rounded-lg border border-gray-200 px-6 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>

        {{-- Quick add group/type modal --}}
        <div x-show="quick.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="quick.open = false">
            <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-base font-bold text-[var(--color-heading)]">Add <span x-text="quick.type === 'group' ? 'Assign Group' : 'Type'"></span></h3>
                <input x-model="quick.name" placeholder="Name" class="mt-4 h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none" @keydown.enter.prevent="saveQuick">
                <p x-show="quick.error" x-cloak class="mt-1 text-xs text-red-600" x-text="quick.error"></p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="quick.open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                    <button type="button" @click="saveQuick" class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
                </div>
            </div>
        </div>
    </form>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script>
        function ticketForm(cfg) {
            return {
                clients: cfg.clients, employees: cfg.employees,
                requester: 'client', clientId: '',
                quick: { open: false, type: '', name: '', error: '' },
                get people() { return this.requester === 'client' ? this.clients : this.employees; },
                init() {
                    this.$watch('requester', () => { this.clientId = ''; });
                    const el = document.getElementById('ticket-desc');
                    const input = document.getElementById('ticket-desc-input');
                    const form = document.getElementById('ticket-form');
                    if (el && typeof Quill !== 'undefined') {
                        const quill = new Quill('#ticket-desc', { theme: 'snow', placeholder: 'Describe the issue…', modules: { toolbar: [[{ header: [1, 2, 3, false] }], ['bold', 'italic', 'underline', 'strike'], [{ list: 'ordered' }, { list: 'bullet' }], [{ align: [] }], [{ color: [] }, { background: [] }], ['link', 'image', 'video'], ['clean']] } });
                        form.addEventListener('submit', () => { input.value = quill.getText().trim().length ? quill.root.innerHTML : ''; });
                    } else if (el) { input.classList.remove('hidden'); input.rows = 4; el.parentElement.classList.add('hidden'); }
                },
                async saveQuick() {
                    if (!this.quick.name.trim()) { this.quick.error = 'Name is required.'; return; }
                    const url = this.quick.type === 'group' ? cfg.groupUrl : cfg.typeUrl;
                    try {
                        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf }, body: JSON.stringify({ name: this.quick.name }) });
                        if (!res.ok) { const e = await res.json().catch(() => ({})); this.quick.error = e.errors ? Object.values(e.errors).flat().join(' ') : 'Could not add.'; return; }
                        const item = await res.json();
                        const sel = this.quick.type === 'group' ? this.$refs.groupSelect : this.$refs.typeSelect;
                        const opt = document.createElement('option'); opt.value = item.id; opt.textContent = item.name; sel.appendChild(opt); sel.value = item.id;
                        this.quick = { open: false, type: '', name: '', error: '' };
                    } catch (e) { this.quick.error = 'Something went wrong.'; }
                },
            };
        }
    </script>
@endsection
