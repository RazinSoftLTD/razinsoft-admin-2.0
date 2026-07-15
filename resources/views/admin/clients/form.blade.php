@extends('admin.layouts.app')
@section('title', $client->exists ? 'Edit Client' : 'Add Client')

@php
    $val = fn ($k, $default = null) => old($k, $client->$k ?? $default);
    $countries = config('countries');
    $curCat = old('client_category', $client->client_category);
    $curSub = old('client_sub_category', $client->client_sub_category);
    $loginAllowed = old('login_allowed', $client->exists ? ($client->status !== \App\Models\User::STATUS_BLOCKED ? '1' : '0') : '1');
    $notify = old('receive_email_notifications', $client->exists ? ($client->receive_email_notifications ? '1' : '0') : '1');
    // Whether the company section holds any data (so it opens expanded when editing a filled client).
    $companyFilled = collect(['company', 'website', 'tax_name', 'gst_number', 'office_phone', 'city', 'state', 'zip', 'address', 'shipping_address'])
        ->contains(fn ($k) => filled($client->$k ?? null));
@endphp

@section('content')
    <a href="{{ route('admin.clients.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Clients
    </a>

    <form method="POST" action="{{ $client->exists ? route('admin.clients.update', $client) : route('admin.clients.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @if ($client->exists) @method('PUT') @endif

        {{-- Header: title + actions on the right --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-bold text-[var(--color-heading)]">{{ $client->exists ? 'Edit Client' : 'Add Client' }}</h1>
                @if ($client->exists)<p class="text-sm text-[var(--color-muted)]">{{ $client->client_code }}</p>@endif
            </div>
            <div class="flex gap-3">
                <a href="{{ route('admin.clients.index') }}" class="rounded-lg border border-gray-200 px-6 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
                <button class="rounded-lg bg-[var(--color-primary)] px-6 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">{{ $client->exists ? 'Save changes' : 'Create Client' }}</button>
            </div>
        </div>

        {{-- ===== Account Details ===== --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-5 text-base font-bold text-[var(--color-heading)]">Account Details</h2>

            {{-- Profile Picture — creative avatar uploader with live preview --}}
            @php $photoUrl = $client->photo ? asset('storage/'.$client->photo) : null; @endphp
            <div class="mb-6 flex items-center gap-4">
                <div class="relative shrink-0">
                    <img id="avatar-preview" src="{{ $photoUrl ?? 'data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%23cbd5e1%22><path d=%22M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4 0-9 2-9 6v2h18v-2c0-4-5-6-9-6z%22/></svg>' }}"
                         alt="" class="h-20 w-20 rounded-full border-2 border-gray-100 bg-gray-50 object-cover shadow-sm">
                    <label for="photo-input" title="Upload photo"
                           class="absolute -bottom-1 -right-1 flex h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-[var(--color-primary)] text-white shadow ring-2 ring-white hover:bg-[var(--color-primary-hover)]">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/></svg>
                    </label>
                    <input id="photo-input" type="file" name="photo" accept="image/*" class="hidden">
                </div>
                <div>
                    <p class="text-sm font-semibold text-[var(--color-heading)]">Profile Picture</p>
                    <p class="text-xs text-gray-400">JPG, PNG or GIF — up to 2&nbsp;MB. Click the camera to upload.</p>
                    @error('photo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Row 1: Salutation (half width) · Client Name · Email · Client Category · Client Sub Category · Language --}}
            <div class="flex flex-col gap-5 lg:flex-row">
                <x-admin.field label="Salutation" name="salutation" type="select" :value="$val('salutation')"
                    class="lg:w-24 lg:shrink-0"
                    :options="['' => '--'] + collect($salutations)->mapWithKeys(fn ($s) => [$s => $s])->all()" />
                <x-admin.field label="Client Name" name="name" :value="$val('name')" required placeholder="e.g. John Doe" class="flex-1" />
                <x-admin.field label="Email Address" name="email" type="email" :value="$val('email')" required placeholder="e.g. johndoe@example.com" class="flex-1" />

                {{-- Client Category (existing + add new) --}}
                <div class="flex-1" x-data="{ adding: {{ ($curCat && ! in_array($curCat, $categories, true)) ? 'true' : 'false' }} }">
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Client Category</label>
                    <div class="flex gap-2">
                        <select name="client_category" x-show="!adding" :disabled="adding" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <option value="">--</option>
                            @foreach ($categories as $c)<option value="{{ $c }}" @selected($curCat === $c)>{{ $c }}</option>@endforeach
                        </select>
                        <input type="text" name="client_category" x-show="adding" x-cloak :disabled="!adding"
                               value="{{ ($curCat && ! in_array($curCat, $categories, true)) ? $curCat : '' }}" placeholder="New" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        <button type="button" @click="adding = !adding" class="h-11 shrink-0 rounded-lg border border-gray-200 bg-white px-3 text-sm font-semibold hover:bg-gray-50" x-text="adding ? '✕' : '+'"></button>
                    </div>
                </div>

                {{-- Client Sub Category (existing + add new) --}}
                <div class="flex-1" x-data="{ adding: {{ ($curSub && ! in_array($curSub, $subCategories, true)) ? 'true' : 'false' }} }">
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Client Sub Category</label>
                    <div class="flex gap-2">
                        <select name="client_sub_category" x-show="!adding" :disabled="adding" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <option value="">--</option>
                            @foreach ($subCategories as $c)<option value="{{ $c }}" @selected($curSub === $c)>{{ $c }}</option>@endforeach
                        </select>
                        <input type="text" name="client_sub_category" x-show="adding" x-cloak :disabled="!adding"
                               value="{{ ($curSub && ! in_array($curSub, $subCategories, true)) ? $curSub : '' }}" placeholder="New" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        <button type="button" @click="adding = !adding" class="h-11 shrink-0 rounded-lg border border-gray-200 bg-white px-3 text-sm font-semibold hover:bg-gray-50" x-text="adding ? '✕' : '+'"></button>
                    </div>
                </div>

                <x-admin.field label="Language" name="language" type="select" :value="$val('language', 'English')"
                    class="flex-1"
                    :options="collect($languages)->mapWithKeys(fn ($l) => [$l => $l])->all()" />
            </div>

            {{-- Row 2: Password · Country · Mobile · Gender --}}
            <div class="mt-5 flex flex-col gap-5 lg:flex-row">
                <div class="flex-1">
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">{{ $client->exists ? 'Reset Password' : 'Password' }}</label>
                    <div class="flex gap-2">
                        {{-- Input with the show/hide eye button inside, on the right --}}
                        <div class="relative flex-1">
                            <input id="cl-password" name="password" type="password" value="{{ old('password') }}" placeholder="Min 4 characters"
                                   class="h-11 w-full rounded-lg border border-gray-200 pl-3 pr-10 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                            <button type="button" id="pw-toggle" title="Show / hide"
                                    class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-gray-400 hover:text-[var(--color-heading)]">
                                {{-- eye (shown) --}}
                                <svg id="pw-eye" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                {{-- eye-off (hidden) --}}
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
                    <p id="pw-hint" class="mt-1 text-xs text-gray-400">{{ $client->exists ? 'Leave blank to keep current.' : 'Must have at least 4 characters.' }}</p>
                    @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Country (searchable) + Mobile, kept in sync — takes 2× width so all four cells look equal --}}
                <div x-data="clientLocation({
                        countries: {{ Illuminate\Support\Js::from($countries) }},
                        country: {{ Illuminate\Support\Js::from(old('country', $client->country)) }},
                        dial: {{ Illuminate\Support\Js::from(old('dial_code', $client->dial_code)) }},
                     })" class="flex flex-col gap-5 sm:flex-row lg:flex-[2]">
                    {{-- Country search --}}
                    <div class="relative flex-1" @click.outside="openC = false">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Country</label>
                        <input type="hidden" name="country" :value="country">
                        <input type="text" x-model="countryQuery" @focus="openC = true; $el.select()" @click="openC = true"
                               @keydown.escape="openC = false" autocomplete="off" placeholder="Search country…"
                               class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        <ul x-show="openC" x-cloak class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                            <template x-for="c in filteredCountries" :key="c.code">
                                <li @click="selectCountry(c)" class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-gray-100" :class="c.name === country ? 'bg-gray-50 font-semibold' : ''">
                                    <span x-text="c.flag"></span><span x-text="c.name"></span><span class="ml-auto text-xs text-gray-400" x-text="c.dial"></span>
                                </li>
                            </template>
                            <li x-show="filteredCountries.length === 0" class="px-3 py-2 text-sm text-gray-400">No match</li>
                        </ul>
                    </div>
                    {{-- Mobile: searchable dial + number --}}
                    <div class="flex-1">
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Mobile</label>
                        <div class="flex gap-2">
                            <div class="relative w-28 shrink-0" @click.outside="openD = false">
                                <input type="hidden" name="dial_code" :value="dial">
                                <input type="text" x-model="dialQuery" @focus="openD = true; $el.select()" @click="openD = true"
                                       @keydown.escape="openD = false" autocomplete="off" placeholder="Code"
                                       class="h-11 w-full rounded-lg border border-gray-200 px-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                <ul x-show="openD" x-cloak class="absolute z-20 mt-1 max-h-60 w-72 overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                                    <template x-for="c in filteredDials" :key="c.code">
                                        <li @click="selectDial(c)" class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-gray-100" :class="c.dial === dial ? 'bg-gray-50 font-semibold' : ''">
                                            <span x-text="c.flag"></span><span x-text="c.dial"></span><span class="ml-auto text-xs text-gray-400" x-text="c.name"></span>
                                        </li>
                                    </template>
                                    <li x-show="filteredDials.length === 0" class="px-3 py-2 text-sm text-gray-400">No match</li>
                                </ul>
                            </div>
                            <input name="phone" value="{{ $val('phone') }}" placeholder="e.g. 1234567890" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        </div>
                    </div>
                </div>

                {{-- Gender --}}
                <x-admin.field label="Gender" name="gender" type="select" :value="$val('gender')" class="flex-1"
                    :options="['' => '--'] + collect($genders)->mapWithKeys(fn ($g) => [$g => $g])->all()" />

                {{-- Client Label (loyalty / priority tier) --}}
                <div class="flex-1">
                    <label for="client_label" class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Client Label</label>
                    <select id="client_label" name="client_label" class="h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        <option value="">--</option>
                        @foreach ($clientLabels as $lbl)
                            <option value="{{ $lbl->name }}" @selected($val('client_label') === $lbl->name) @if ($lbl->description) title="{{ $lbl->description }}" @endif>{{ $lbl->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Loyalty / priority tier. <a href="{{ route('admin.crm-settings') }}" target="_blank" class="text-[var(--color-primary)] hover:underline">Manage labels</a></p>
                </div>
            </div>

            {{-- Login allowed? / Receive email notifications? --}}
            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Login Allowed?</label>
                    <div class="flex items-center gap-5 pt-1 text-sm">
                        <label class="inline-flex items-center gap-2"><input type="radio" name="login_allowed" value="1" @checked($loginAllowed === '1') class="accent-[var(--color-primary)]"> Yes</label>
                        <label class="inline-flex items-center gap-2"><input type="radio" name="login_allowed" value="0" @checked($loginAllowed === '0') class="accent-[var(--color-primary)]"> No</label>
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Receive email notifications?</label>
                    <div class="flex items-center gap-5 pt-1 text-sm">
                        <label class="inline-flex items-center gap-2"><input type="radio" name="receive_email_notifications" value="1" @checked($notify === '1') class="accent-[var(--color-primary)]"> Yes</label>
                        <label class="inline-flex items-center gap-2"><input type="radio" name="receive_email_notifications" value="0" @checked($notify === '0') class="accent-[var(--color-primary)]"> No</label>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Company Details (collapsible, hidden by default) ===== --}}
        <div x-data="{ open: {{ $companyFilled ? 'true' : 'false' }} }" class="rounded-xl border border-gray-100 bg-white shadow-sm">
            <button type="button" @click="open = !open" class="flex w-full items-center justify-between p-6 text-left">
                <span class="text-base font-bold text-[var(--color-heading)]">Company Details <span class="text-sm font-normal text-[var(--color-muted)]">(used on invoices)</span></span>
                <svg class="h-5 w-5 text-[var(--color-muted)] transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
            </button>
            <div x-show="open" x-cloak class="border-t border-gray-100 p-6">
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <x-admin.field label="Company Name" name="company" :value="$val('company')" placeholder="Company / organisation" />
                    <x-admin.field label="Official Website" name="website" :value="$val('website')" placeholder="https://example.com" />
                    <x-admin.field label="Tax Name" name="tax_name" :value="$val('tax_name')" />
                    <x-admin.field label="GST / VAT Number" name="gst_number" :value="$val('gst_number')" />
                    <x-admin.field label="Office Phone Number" name="office_phone" :value="$val('office_phone')" />
                    <x-admin.field label="City" name="city" :value="$val('city')" />
                    <x-admin.field label="State" name="state" :value="$val('state')" />
                    <x-admin.field label="Postal Code" name="zip" :value="$val('zip')" />
                </div>
                <div class="mt-5">
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Added By</label>
                    @php $addedBy = $client->exists ? \App\Models\User::find($client->created_by) : auth()->user(); @endphp
                    <div class="inline-flex h-11 items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 text-sm text-[var(--color-muted)]">
                        <span class="font-medium text-[var(--color-heading)]">{{ $addedBy->name ?? '—' }}</span>
                        @if (! $client->exists || (int) $client->created_by === (int) auth()->id())
                            <span class="rounded-full bg-[var(--color-primary)]/10 px-2 py-0.5 text-xs font-semibold text-[var(--color-primary)]">It’s you</span>
                        @endif
                    </div>
                </div>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-admin.field label="Company Address" name="address" type="textarea" :value="$val('address')" placeholder="Street address" />
                    <x-admin.field label="Shipping Address" name="shipping_address" type="textarea" :value="$val('shipping_address')" placeholder="Shipping address" />
                </div>
            </div>
        </div>

        {{-- ===== Note (separate) ===== --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-base font-bold text-[var(--color-heading)]">Note</h2>
            <div class="rounded-lg border border-gray-200 bg-white">
                <div id="note-editor" style="min-height:150px">{!! old('note', $client->note) !!}</div>
            </div>
            <textarea name="note" id="note-input" class="hidden">{{ old('note', $client->note) }}</textarea>
            @error('note')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- ===== Password History (super admin only) ===== --}}
        @if ($client->exists && auth()->user()->isSuperAdmin())
            @php $history = $passwordHistory ?? collect(); @endphp
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
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

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.clients.index') }}" class="rounded-lg border border-gray-200 px-6 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
            <button class="rounded-lg bg-[var(--color-primary)] px-6 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">{{ $client->exists ? 'Save changes' : 'Create Client' }}</button>
        </div>
    </form>

    <style>[x-cloak]{display:none!important}</style>

    {{-- Rich-text Note editor (Quill). --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script>
        (function () {
            const el = document.getElementById('note-editor');
            const input = document.getElementById('note-input');
            if (!el || !input) return;
            if (typeof Quill === 'undefined') {
                input.classList.remove('hidden');
                el.parentElement.classList.add('hidden');
                return;
            }
            const quill = new Quill('#note-editor', {
                theme: 'snow',
                placeholder: 'Write a note…',
                modules: { toolbar: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ align: [] }], [{ color: [] }, { background: [] }],
                    ['link', 'image', 'video'], ['clean'],
                ] },
            });
            const form = el.closest('form');
            if (form) form.addEventListener('submit', function () {
                input.value = quill.getText().trim().length ? quill.root.innerHTML : '';
            });
        })();
    </script>

    {{-- Searchable country + dial-code dropdowns (Alpine), kept in sync. --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('clientLocation', (cfg) => ({
                countries: cfg.countries,
                country: cfg.country || '',
                dial: cfg.dial || '',
                countryQuery: '', dialQuery: '', openC: false, openD: false,
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
                selectCountry(c) { this.country = c.name; this.countryQuery = c.name; this.dial = c.dial; this.dialQuery = c.dial; this.openC = false; },
                selectDial(c) { this.dial = c.dial; this.dialQuery = c.dial; this.country = c.name; this.countryQuery = c.name; this.openD = false; },
            }));
        });
    </script>

    {{-- Password generate + copy + show/hide. --}}
    <script>
        (function () {
            const pwInput = document.getElementById('cl-password');
            const genBtn = document.getElementById('pw-generate');
            const copyBtn = document.getElementById('pw-copy');
            const toggleBtn = document.getElementById('pw-toggle');
            const eye = document.getElementById('pw-eye');
            const eyeOff = document.getElementById('pw-eye-off');
            const hint = document.getElementById('pw-hint');

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
            if (toggleBtn && pwInput) toggleBtn.addEventListener('click', () => setVisible(pwInput.type === 'password'));
            if (genBtn && pwInput) genBtn.addEventListener('click', function () {
                pwInput.value = randomPassword(12);
                setVisible(true); // reveal so the admin can copy/share it
            });

            // Live avatar preview on file select
            const photoInput = document.getElementById('photo-input');
            const avatar = document.getElementById('avatar-preview');
            if (photoInput && avatar) photoInput.addEventListener('change', function () {
                const file = this.files && this.files[0];
                if (file) avatar.src = URL.createObjectURL(file);
            });
            if (copyBtn && pwInput) copyBtn.addEventListener('click', async function () {
                if (!pwInput.value) return;
                try { await navigator.clipboard.writeText(pwInput.value); }
                catch (e) { pwInput.select(); document.execCommand('copy'); }
                if (hint) { const prev = hint.textContent; hint.textContent = 'Copied to clipboard ✓'; hint.classList.add('text-green-600'); setTimeout(() => { hint.textContent = prev; hint.classList.remove('text-green-600'); }, 1500); }
            });
        })();
    </script>
@endsection
