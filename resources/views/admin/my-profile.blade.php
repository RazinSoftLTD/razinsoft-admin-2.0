@extends('admin.layouts.app')
@section('title', 'My Profile')

@php $countries = config('countries'); @endphp

@section('content')
    <h1 class="mb-5 text-xl font-bold text-[var(--color-heading)]">My Profile</h1>

    <form method="POST" action="{{ route('admin.my-profile.update') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
        @csrf

        {{-- Left: photo + read-only employment info --}}
        <div class="space-y-6">
            <div class="rounded-xl border border-gray-100 bg-white p-6 text-center shadow-sm">
                @if ($me->photo_url)
                    <img src="{{ $me->photo_url }}" alt="" class="mx-auto h-24 w-24 rounded-full border border-gray-200 object-cover">
                @else
                    <span class="mx-auto grid h-24 w-24 place-items-center rounded-full bg-[var(--color-primary-soft)] text-3xl font-bold text-[var(--color-primary)]">{{ strtoupper(substr($me->name, 0, 1)) }}</span>
                @endif
                <p class="mt-3 font-bold text-[var(--color-heading)]">{{ $me->name }}</p>
                <p class="text-sm text-[var(--color-muted)]">{{ $me->designation->name ?? ucfirst($me->role) }}</p>
                <label class="mt-4 block cursor-pointer">
                    <span class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[var(--color-heading)] hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15V3m0 0L8 7m4-4 4 4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                        Change picture
                    </span>
                    <input type="file" name="photo" accept="image/*" class="hidden" onchange="this.closest('label').querySelector('span').lastChild.textContent = this.files[0]?.name ? ' ' + this.files[0].name : ' Change picture'">
                </label>
                @error('photo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-5 text-sm shadow-sm">
                <h2 class="mb-3 text-sm font-bold text-[var(--color-heading)]">Employment</h2>
                <div class="space-y-2 text-[var(--color-muted)]">
                    <div class="flex justify-between gap-3"><span>Employee ID</span><span class="font-medium text-[var(--color-heading)]">{{ $me->employee_code ?? '—' }}</span></div>
                    <div class="flex justify-between gap-3"><span>Department</span><span class="font-medium text-[var(--color-heading)]">{{ $me->department->name ?? '—' }}</span></div>
                    <div class="flex justify-between gap-3"><span>Reporting To</span><span class="font-medium text-[var(--color-heading)]">{{ $me->reportsTo->name ?? '—' }}</span></div>
                    <div class="flex justify-between gap-3"><span>Joined</span><span class="font-medium text-[var(--color-heading)]">{{ optional($me->joining_date)->format('d M, Y') ?? '—' }}</span></div>
                </div>
                <p class="mt-3 text-xs text-gray-400">These are managed by HR.</p>
            </div>
        </div>

        {{-- Right: editable fields --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-5 text-base font-bold text-[var(--color-heading)]">Personal Information</h2>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-admin.field label="Full Name" name="name" :value="old('name', $me->name)" required />
                    <x-admin.field label="Email" name="email" type="email" :value="old('email', $me->email)" required />
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Mobile</label>
                        <div class="flex gap-2">
                            <select name="dial_code" class="h-11 w-28 shrink-0 rounded-lg border border-gray-200 bg-white px-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                <option value="">Code</option>
                                @foreach ($countries as $c)<option value="{{ $c['dial'] }}" @selected(old('dial_code', $me->dial_code) === $c['dial'])>{{ $c['flag'] }} {{ $c['dial'] }}</option>@endforeach
                            </select>
                            <input name="phone" value="{{ old('phone', $me->phone) }}" placeholder="1712345678" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                        </div>
                    </div>
                    <x-admin.field label="Date of Birth" name="date_of_birth" type="date" :value="old('date_of_birth', optional($me->date_of_birth)->format('Y-m-d'))" />
                </div>
                <div class="mt-5 grid gap-5">
                    <x-admin.field label="Address" name="address" type="textarea" :value="old('address', $me->address)" />
                    <x-admin.field label="About" name="about" type="textarea" :value="old('about', $me->about)" placeholder="A short bio" />
                </div>
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-5 text-base font-bold text-[var(--color-heading)]">Change Password <span class="text-sm font-normal text-[var(--color-muted)]">(optional)</span></h2>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-admin.field label="New Password" name="password" type="password" hint="Min 8 characters. Leave blank to keep current." />
                    <x-admin.field label="Confirm Password" name="password_confirmation" type="password" />
                </div>
                @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex gap-3">
                <button class="rounded-lg bg-[var(--color-primary)] px-6 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save changes</button>
                <a href="{{ url('admin') }}" class="rounded-lg border border-gray-200 px-6 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
            </div>
        </div>
    </form>
@endsection
