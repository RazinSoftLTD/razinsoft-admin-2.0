@extends('admin.layouts.app')
@section('title', $client->exists ? 'Edit Client' : 'New Client')

@section('content')
    <a href="{{ route('admin.clients.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to clients
    </a>

    @php $countries = config('countries'); @endphp
    <form method="POST" action="{{ $client->exists ? route('admin.clients.update', $client) : route('admin.clients.store') }}" class="max-w-2xl space-y-6" enctype="multipart/form-data">
        @csrf
        @if ($client->exists) @method('PUT') @endif

        <div class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Client Details</h2>
                @if ($client->exists)<span class="text-xs font-semibold text-[var(--color-muted)]">{{ $client->client_code }}</span>@endif
            </div>

            {{-- Client image --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Client image</label>
                <div class="flex items-center gap-4">
                    @php $photoUrl = $client->photo ? asset('storage/'.$client->photo) : null; @endphp
                    <img src="{{ $photoUrl ?? 'data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%23cbd5e1%22><path d=%22M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4 0-9 2-9 6v2h18v-2c0-4-5-6-9-6z%22/></svg>' }}"
                         alt="" class="h-16 w-16 rounded-full border border-gray-200 object-cover bg-gray-50">
                    <input type="file" name="photo" accept="image/*"
                           class="block w-full text-sm text-[var(--color-muted)] file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-heading)] hover:file:bg-gray-200">
                </div>
                @error('photo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <x-admin.field label="Client full name" name="name" :value="$client->name" placeholder="Client full name" />
            <x-admin.field label="Email address" name="email" type="email" :value="$client->email" required />

            {{-- Country + phone: searchable dropdowns, kept in sync --}}
            <div x-data="clientLocation({
                    countries: {{ Illuminate\Support\Js::from($countries) }},
                    country: {{ Illuminate\Support\Js::from(old('country', $client->country)) }},
                    dial: {{ Illuminate\Support\Js::from(old('dial_code', $client->dial_code)) }},
                 })" class="space-y-5">

                {{-- Country (type to search, click to select) --}}
                <div class="relative" @click.outside="openC = false">
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Country</label>
                    <input type="hidden" name="country" :value="country">
                    <input type="text" x-model="countryQuery" @focus="openC = true; $el.select()" @click="openC = true"
                           @keydown.escape="openC = false" autocomplete="off" placeholder="Search country…"
                           class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                    <ul x-show="openC" x-cloak
                        class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                        <template x-for="c in filteredCountries" :key="c.code">
                            <li @click="selectCountry(c)"
                                class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-gray-100"
                                :class="c.name === country ? 'bg-gray-50 font-semibold' : ''">
                                <span x-text="c.flag"></span><span x-text="c.name"></span>
                                <span class="ml-auto text-xs text-gray-400" x-text="c.dial"></span>
                            </li>
                        </template>
                        <li x-show="filteredCountries.length === 0" class="px-3 py-2 text-sm text-gray-400">No match</li>
                    </ul>
                    @error('country')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Phone: searchable dial code + number --}}
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Phone number</label>
                    <div class="flex gap-2">
                        <div class="relative w-40 shrink-0" @click.outside="openD = false">
                            <input type="hidden" name="dial_code" :value="dial">
                            <input type="text" x-model="dialQuery" @focus="openD = true; $el.select()" @click="openD = true"
                                   @keydown.escape="openD = false" autocomplete="off" placeholder="Code"
                                   class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                            <ul x-show="openD" x-cloak
                                class="absolute z-20 mt-1 max-h-60 w-72 overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                                <template x-for="c in filteredDials" :key="c.code">
                                    <li @click="selectDial(c)"
                                        class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-gray-100"
                                        :class="c.dial === dial ? 'bg-gray-50 font-semibold' : ''">
                                        <span x-text="c.flag"></span><span x-text="c.dial"></span>
                                        <span class="ml-auto text-xs text-gray-400" x-text="c.name"></span>
                                    </li>
                                </template>
                                <li x-show="filteredDials.length === 0" class="px-3 py-2 text-sm text-gray-400">No match</li>
                            </ul>
                        </div>
                        <input name="phone" type="text" value="{{ old('phone', $client->phone) }}" placeholder="1234567890"
                               class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                    </div>
                    @error('dial_code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Account status + password --}}
            <div class="space-y-4 rounded-lg border border-gray-100 bg-gray-50/60 p-4">
                <div>
                    <label for="status" class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Account status</label>
                    <select id="status" name="status" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                        @foreach (\App\Models\User::STATUSES as $val => $label)
                            <option value="{{ $val }}" @selected(old('status', $client->exists ? $client->status : 'active') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400"><strong>Active</strong> — full access · <strong>Inactive</strong> — can sign in but support only · <strong>Blocked</strong> — cannot sign in.</p>
                    @error('status')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">{{ $client->exists ? 'Reset password' : 'Password' }}</label>
                    <div class="flex gap-2">
                        <input id="password" name="password" type="password" value="{{ old('password') }}" placeholder="Min 8 characters"
                               class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                        <button type="button" id="pw-generate" class="h-11 shrink-0 rounded-lg border border-gray-200 bg-white px-3 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">Generate</button>
                        <button type="button" id="pw-toggle" class="h-11 shrink-0 rounded-lg border border-gray-200 bg-white px-3 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Show</button>
                    </div>
                    <p class="mt-1 text-xs text-gray-400">{{ $client->exists ? 'Leave blank to keep the current password. Use “Generate” for a strong random one.' : 'Leave blank to auto-generate. Use “Generate” to create and reveal a strong password.' }}</p>
                    @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Billing Details <span class="font-normal text-[var(--color-muted)]">(used on invoices)</span></h2>
            <x-admin.field label="Company" name="company" :value="$client->company" placeholder="Company / organisation" />
            <x-admin.field label="Address" name="address" :value="$client->address" placeholder="Street address" />
            <div class="grid gap-5 sm:grid-cols-3">
                <x-admin.field label="City" name="city" :value="$client->city" />
                <x-admin.field label="State / Region" name="state" :value="$client->state" />
                <x-admin.field label="ZIP / Postal" name="zip" :value="$client->zip" />
            </div>
        </div>

        {{-- Note (rich text) --}}
        <div class="space-y-3 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Note</h2>
            <div class="rounded-lg border border-gray-200 bg-white">
                <div id="note-editor" style="min-height:160px">{!! old('note', $client->note) !!}</div>
            </div>
            {{-- Quill writes its HTML here on submit; also the no-JS fallback value. --}}
            <textarea name="note" id="note-input" class="hidden">{{ old('note', $client->note) }}</textarea>
            @error('note')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">{{ $client->exists ? 'Save changes' : 'Create client' }}</button>
            <a href="{{ route('admin.clients.index') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>

    <style>[x-cloak]{display:none!important}</style>

    {{-- Rich-text Note editor (Quill). Loaded from CDN like Alpine. --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script>
        (function () {
            const el = document.getElementById('note-editor');
            const input = document.getElementById('note-input');
            if (!el || !input) return;

            if (typeof Quill === 'undefined') {
                // CDN failed — fall back to a plain textarea so the note is still editable.
                input.classList.remove('hidden');
                el.parentElement.classList.add('hidden');
                return;
            }

            const quill = new Quill('#note-editor', {
                theme: 'snow',
                placeholder: 'Write a note…',
                modules: {
                    toolbar: [
                        [{ header: [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        [{ align: [] }],
                        [{ color: [] }, { background: [] }],
                        ['link', 'image', 'video'],
                        ['clean'],
                    ],
                },
            });

            const form = el.closest('form');
            if (form) {
                form.addEventListener('submit', function () {
                    const html = quill.getText().trim().length ? quill.root.innerHTML : '';
                    input.value = html;
                });
            }
        })();
    </script>

    {{-- Searchable country + dial-code dropdowns (Alpine), kept in sync with each other. --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('clientLocation', (cfg) => ({
                countries: cfg.countries,
                country: cfg.country || '',
                dial: cfg.dial || '',
                countryQuery: '',
                dialQuery: '',
                openC: false,
                openD: false,
                init() {
                    const c = this.countries.find((x) => x.name === this.country);
                    this.countryQuery = c ? c.name : (this.country || '');
                    this.dialQuery = this.dial || '';
                },
                get filteredCountries() {
                    const q = this.countryQuery.trim().toLowerCase();
                    if (!q) return this.countries;
                    return this.countries.filter((c) => c.name.toLowerCase().includes(q) || c.dial.includes(q));
                },
                get filteredDials() {
                    const q = this.dialQuery.trim().toLowerCase();
                    if (!q) return this.countries;
                    return this.countries.filter((c) => c.dial.includes(q) || c.name.toLowerCase().includes(q));
                },
                selectCountry(c) {
                    this.country = c.name; this.countryQuery = c.name;
                    this.dial = c.dial; this.dialQuery = c.dial; // auto-sync dial code
                    this.openC = false;
                },
                selectDial(c) {
                    this.dial = c.dial; this.dialQuery = c.dial;
                    this.country = c.name; this.countryQuery = c.name; // auto-sync country
                    this.openD = false;
                },
            }));
        });
    </script>

    {{-- Password auto-generate + show/hide. --}}
    <script>
        (function () {
            const pwInput = document.getElementById('password');
            const genBtn = document.getElementById('pw-generate');
            const toggleBtn = document.getElementById('pw-toggle');

            function randomPassword(len) {
                const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
                const buf = new Uint32Array(len);
                (window.crypto || window.msCrypto).getRandomValues(buf);
                return Array.from(buf, (n) => chars[n % chars.length]).join('');
            }
            if (genBtn && pwInput) {
                genBtn.addEventListener('click', function () {
                    pwInput.value = randomPassword(14);
                    pwInput.type = 'text'; // reveal so the admin can copy/share it
                    if (toggleBtn) toggleBtn.textContent = 'Hide';
                });
            }
            if (toggleBtn && pwInput) {
                toggleBtn.addEventListener('click', function () {
                    const show = pwInput.type === 'password';
                    pwInput.type = show ? 'text' : 'password';
                    toggleBtn.textContent = show ? 'Hide' : 'Show';
                });
            }
        })();
    </script>
@endsection
