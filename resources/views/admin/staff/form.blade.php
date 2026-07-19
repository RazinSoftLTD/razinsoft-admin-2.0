@extends('admin.layouts.app')
@section('title', $staff->exists ? 'Edit Employee' : 'Add Employee')

@php
    $salutations = ['Mr', 'Mrs', 'Ms', 'Dr', 'Miss'];
    $val = fn ($k, $default = null) => old($k, $staff->$k ?? $default);
    $dateVal = fn ($k) => old($k, optional($staff->$k)->format('Y-m-d'));
@endphp

@section('content')
    <a href="{{ route('admin.staff.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Employees
    </a>

    <form method="POST" action="{{ $staff->exists ? route('admin.staff.update', $staff) : route('admin.staff.store') }}" enctype="multipart/form-data"
          x-data="employeeForm({ desigUrl: '{{ route('admin.staff.designations.store') }}', deptUrl: '{{ route('admin.staff.departments.store') }}', csrf: '{{ csrf_token() }}' })" class="space-y-6">
        @csrf
        @if ($staff->exists) @method('PUT') @endif

        {{-- ===== Account Details ===== --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-5 text-base font-bold text-[var(--color-heading)]">Account Details</h2>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-admin.field label="Employee ID" name="employee_code" :value="$val('employee_code', $nextCode)" />
                <x-admin.field label="Salutation" name="salutation" type="select" :value="$val('salutation')" :options="['' => '--'] + collect($salutations)->mapWithKeys(fn ($s) => [$s => $s])->all()" />
                <x-admin.field label="Employee Name" name="name" :value="$val('name')" required placeholder="e.g. John Doe" />
                <x-admin.field label="Employee Email" name="email" type="email" :value="$val('email')" required placeholder="e.g. johndoe@example.com" />
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">{{ $staff->exists ? 'Reset Password' : 'Password' }} @if (! $staff->exists)<span class="text-red-500">*</span>@endif</label>
                    <div class="flex gap-2">
                        {{-- Input with the show/hide eye button inside, on the right --}}
                        <div class="relative flex-1">
                            <input id="emp-password" name="password" type="password" value="{{ old('password') }}" placeholder="Min 4 characters"
                                   class="h-11 w-full rounded-lg border border-gray-200 pl-3 pr-10 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <button type="button" id="pw-toggle" title="Show / hide"
                                    class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-gray-400 hover:text-[var(--color-heading)]">
                                <svg id="pw-eye" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                <svg id="pw-eye-off" class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                            </button>
                        </div>
                        <button type="button" id="pw-generate" title="Generate strong password"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-[var(--color-heading)]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                        </button>
                        <button type="button" id="pw-copy" title="Copy password"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-[var(--color-heading)]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"/></svg>
                        </button>
                    </div>
                    <p id="pw-hint" class="mt-1 text-xs text-gray-400">{{ $staff->exists ? 'Leave blank to keep current.' : 'Must have at least 4 characters.' }}</p>
                    @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Designation</label>
                    <div class="flex gap-2">
                        <select name="designation_id" x-ref="desigSelect" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <option value="">--</option>
                            @foreach ($designations as $d)<option value="{{ $d->id }}" @selected($val('designation_id') == $d->id)>{{ $d->name }}</option>@endforeach
                        </select>
                        <button type="button" @click="quick = { open: true, type: 'designation', name: '', error: '' }" class="h-11 shrink-0 rounded-lg border border-gray-200 bg-white px-4 text-sm font-semibold hover:bg-gray-50">Add</button>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Department</label>
                    <div class="flex gap-2">
                        <select name="department_id" x-ref="deptSelect" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <option value="">--</option>
                            @foreach ($departments as $d)<option value="{{ $d->id }}" @selected($val('department_id') == $d->id)>{{ $d->name }}</option>@endforeach
                        </select>
                        <button type="button" @click="quick = { open: true, type: 'department', name: '', error: '' }" class="h-11 shrink-0 rounded-lg border border-gray-200 bg-white px-4 text-sm font-semibold hover:bg-gray-50">Add</button>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Profile Picture</label>
                    <input type="file" name="photo" accept="image/*" class="block w-full text-sm text-[var(--color-muted)] file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-heading)] hover:file:bg-gray-200">
                    @error('photo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-admin.field label="Country" name="country" type="select" :value="$val('country')" :options="['' => '--'] + collect($countries)->pluck('name', 'name')->all()" />
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Mobile</label>
                    <div class="flex gap-2">
                        <select name="dial_code" class="h-11 w-28 shrink-0 rounded-lg border border-gray-200 bg-white px-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <option value="">Code</option>
                            @foreach ($countries as $c)<option value="{{ $c['dial'] }}" @selected($val('dial_code') === $c['dial'])>{{ $c['flag'] }} {{ $c['dial'] }}</option>@endforeach
                        </select>
                        <input name="phone" value="{{ $val('phone') }}" placeholder="e.g. 1234567890" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                    </div>
                </div>
                <x-admin.field label="Joining Date" name="joining_date" type="date" :value="$dateVal('joining_date')" required />
                <x-admin.field label="Date of Birth" name="date_of_birth" type="date" :value="$dateVal('date_of_birth')" />
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-admin.field label="Reporting To" name="reporting_to" type="select" :value="$val('reporting_to')"
                    :options="['' => '--'] + $reportable->when($staff->exists, fn ($c) => $c->where('id', '!=', $staff->id))->pluck('name', 'id')->all()" />
                <x-admin.field label="Language" name="language" type="select" :value="$val('language', 'en')" :options="$languages" />
                @if (auth()->user()->isAdmin())
                    <x-admin.field label="User Role" name="role_id" type="select" :value="$val('role_id')" :options="['' => 'No role'] + $roles->pluck('name', 'id')->all()" />
                @endif
            </div>

            <div class="mt-5 grid gap-5">
                <x-admin.field label="Address" name="address" type="textarea" :value="$val('address')" placeholder="e.g. 132, My Street, Kingston, New York 12401" />
                <x-admin.field label="About" name="about" type="textarea" :value="$val('about')" placeholder="Short bio / notes about the employee" />
            </div>
        </div>

        {{-- ===== Other Details ===== --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-5 text-base font-bold text-[var(--color-heading)]">Other Details</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Login Allowed?</label>
                    @php $loginAllowed = old('login_allowed', $staff->exists ? ($staff->status === \App\Models\User::STATUS_ACTIVE ? '1' : '0') : '1'); @endphp
                    <div class="flex items-center gap-5 pt-1 text-sm">
                        <label class="inline-flex items-center gap-2"><input type="radio" name="login_allowed" value="1" @checked($loginAllowed === '1') class="accent-[var(--color-primary)]"> Yes</label>
                        <label class="inline-flex items-center gap-2"><input type="radio" name="login_allowed" value="0" @checked($loginAllowed === '0') class="accent-[var(--color-primary)]"> No</label>
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Receive email notifications?</label>
                    @php $recv = old('receive_email_notifications', $staff->exists ? ($staff->receive_email_notifications ? '1' : '0') : '1'); @endphp
                    <div class="flex items-center gap-5 pt-1 text-sm">
                        <label class="inline-flex items-center gap-2"><input type="radio" name="receive_email_notifications" value="1" @checked($recv === '1') class="accent-[var(--color-primary)]"> Yes</label>
                        <label class="inline-flex items-center gap-2"><input type="radio" name="receive_email_notifications" value="0" @checked($recv === '0') class="accent-[var(--color-primary)]"> No</label>
                    </div>
                </div>
                <x-admin.field label="Employment Type" name="employment_type" type="select" :value="$val('employment_type')" :options="['' => '--'] + $employmentTypes" />
                <x-admin.field label="Probation End Date" name="probation_end_date" type="date" :value="$dateVal('probation_end_date')" />
                <x-admin.field label="Notice Period Start Date" name="notice_start_date" type="date" :value="$dateVal('notice_start_date')" />
                <x-admin.field label="Notice Period End Date" name="notice_end_date" type="date" :value="$dateVal('notice_end_date')" />
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-6 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">{{ $staff->exists ? 'Save changes' : 'Add Employee' }}</button>
            <a href="{{ route('admin.staff.index') }}" class="rounded-lg border border-gray-200 px-6 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>

        {{-- Quick add designation/department modal --}}
        <div x-show="quick.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="quick.open = false">
            <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-base font-bold capitalize text-[var(--color-heading)]">Add <span x-text="quick.type"></span></h3>
                <input x-model="quick.name" placeholder="Name" class="mt-4 h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none" @keydown.enter.prevent="saveQuick">
                <p x-show="quick.error" x-cloak class="mt-1 text-xs text-red-600" x-text="quick.error"></p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="quick.open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                    <button type="button" @click="saveQuick" class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
                </div>
            </div>
        </div>
    </form>

    {{-- ===== Password History (super admin only) ===== --}}
    @if ($staff->exists && auth()->user()->isSuperAdmin())
        @php $history = $passwordHistory ?? collect(); @endphp
        <div class="mt-6 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center gap-2">
                <svg class="h-5 w-5 text-[var(--color-muted)]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                <h2 class="text-base font-bold text-[var(--color-heading)]">Password History</h2>
                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-600">Super admin only</span>
            </div>
            @if ($history->isEmpty())
                <p class="text-sm text-gray-400">No recorded passwords yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                <th class="py-2 pr-4 font-semibold">Password</th>
                                <th class="py-2 pr-4 font-semibold">Set by</th>
                                <th class="py-2 font-semibold">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($history as $h)
                                <tr x-data="{ show: false, pw: @js($h->password) }" class="border-b border-gray-50 last:border-0">
                                    <td class="py-3 pr-4">
                                        <div class="flex items-center gap-2">
                                            <code class="rounded bg-gray-50 px-2 py-1 font-mono text-[var(--color-heading)]" x-text="show ? pw : '••••••••••'"></code>
                                            <button type="button" @click="show = !show" class="text-gray-400 hover:text-[var(--color-heading)]" x-text="show ? 'Hide' : 'Show'"></button>
                                            <button type="button" @click="window.navigator.clipboard && window.navigator.clipboard.writeText(pw); $el.textContent = 'Copied'; setTimeout(() => $el.textContent = 'Copy', 1200)" class="text-[var(--color-primary)] hover:underline">Copy</button>
                                        </div>
                                    </td>
                                    <td class="py-3 pr-4 text-[var(--color-muted)]">{{ optional($h->setter)->name ?? '—' }}</td>
                                    <td class="py-3 text-[var(--color-muted)]">{{ optional($h->created_at)->format('d M Y, h:i A') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- Password generate + copy + show/hide. --}}
    <script>
        (function () {
            const pwInput = document.getElementById('emp-password');
            const genBtn = document.getElementById('pw-generate');
            const copyBtn = document.getElementById('pw-copy');
            const toggleBtn = document.getElementById('pw-toggle');
            const eye = document.getElementById('pw-eye');
            const eyeOff = document.getElementById('pw-eye-off');
            const hint = document.getElementById('pw-hint');
            if (!pwInput) return;

            function randomPassword(len) {
                const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
                const buf = new Uint32Array(len);
                (window.crypto || window.msCrypto).getRandomValues(buf);
                return Array.from(buf, (n) => chars[n % chars.length]).join('');
            }
            function setVisible(show) {
                pwInput.type = show ? 'text' : 'password';
                if (eye) eye.classList.toggle('hidden', show);
                if (eyeOff) eyeOff.classList.toggle('hidden', !show);
            }
            if (toggleBtn) toggleBtn.addEventListener('click', () => setVisible(pwInput.type === 'password'));
            if (genBtn) genBtn.addEventListener('click', function () {
                pwInput.value = randomPassword(12);
                setVisible(true); // reveal so the admin can copy/share it
            });
            if (copyBtn) copyBtn.addEventListener('click', async function () {
                if (!pwInput.value) return;
                try { await navigator.clipboard.writeText(pwInput.value); }
                catch (e) { pwInput.select(); document.execCommand('copy'); }
                if (hint) { const prev = hint.textContent; hint.textContent = 'Copied to clipboard ✓'; hint.classList.add('text-green-600'); setTimeout(() => { hint.textContent = prev; hint.classList.remove('text-green-600'); }, 1500); }
            });
        })();
    </script>

    <script>
        function employeeForm(cfg) {
            return {
                quick: { open: false, type: '', name: '', error: '' },
                async saveQuick() {
                    if (!this.quick.name.trim()) { this.quick.error = 'Name is required.'; return; }
                    const url = this.quick.type === 'designation' ? cfg.desigUrl : cfg.deptUrl;
                    try {
                        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf }, body: JSON.stringify({ name: this.quick.name }) });
                        if (!res.ok) { const e = await res.json().catch(() => ({})); this.quick.error = e.errors ? Object.values(e.errors).flat().join(' ') : 'Could not add.'; return; }
                        const item = await res.json();
                        const sel = this.quick.type === 'designation' ? this.$refs.desigSelect : this.$refs.deptSelect;
                        const opt = document.createElement('option'); opt.value = item.id; opt.textContent = item.name; sel.appendChild(opt); sel.value = item.id;
                        this.quick = { open: false, type: '', name: '', error: '' };
                    } catch (e) { this.quick.error = 'Something went wrong.'; }
                },
            };
        }
    </script>
@endsection
