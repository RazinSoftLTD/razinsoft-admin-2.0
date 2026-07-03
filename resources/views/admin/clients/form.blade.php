@extends('admin.layouts.app')
@section('title', $client->exists ? 'Edit Client' : 'New Client')

@section('content')
    <a href="{{ route('admin.clients.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to clients
    </a>

    <form method="POST" action="{{ $client->exists ? route('admin.clients.update', $client) : route('admin.clients.store') }}" class="max-w-2xl space-y-6">
        @csrf
        @if ($client->exists) @method('PUT') @endif

        <div class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Account</h2>
                @if ($client->exists)<span class="text-xs font-semibold text-[var(--color-muted)]">{{ $client->client_code }}</span>@endif
            </div>
            <x-admin.field label="Full name" name="name" :value="$client->name" required placeholder="Client contact name" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-admin.field label="Email" name="email" type="email" :value="$client->email" required />
                <x-admin.field label="Phone" name="phone" :value="$client->phone" />
            </div>
            <x-admin.field label="Password" name="password" type="password" :required="!$client->exists" :hint="$client->exists ? 'Leave blank to keep current.' : 'Min 8 characters — the client can sign in on the website.'" />
        </div>

        <div class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Billing Details <span class="font-normal text-[var(--color-muted)]">(used on invoices)</span></h2>
            <x-admin.field label="Company" name="company" :value="$client->company" placeholder="Company / organisation" />
            <x-admin.field label="Address" name="address" :value="$client->address" placeholder="Street address" />
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-admin.field label="City" name="city" :value="$client->city" />
                <x-admin.field label="State / Region" name="state" :value="$client->state" />
                <x-admin.field label="Country" name="country" :value="$client->country" />
                <x-admin.field label="ZIP / Postal" name="zip" :value="$client->zip" />
            </div>
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
@endsection
