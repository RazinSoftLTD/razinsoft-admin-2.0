@extends('admin.layouts.app')
@section('title', 'Biometric Devices')

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--color-heading)]">HR Settings &rsaquo; Biometric Devices</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">ZKTeco readers, the employee IDs enrolled on them, and the punch logs they produced.</p>
        </div>
        <button type="button" @click="$dispatch('open-device')" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> Add Device
        </button>
    </div>

    @include('admin.attendance._settings-nav')

    @unless ($settings->biometric_enabled)
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Biometric attendance is switched off in <a href="{{ route('admin.attendance.settings') }}" class="font-semibold underline">Settings</a> — imported punches will be stored but won't count.
        </div>
    @endunless

    {{-- Devices --}}
    <div class="mb-6 grid gap-4 lg:grid-cols-2" x-data="{ editing: null, importing: null }">
        @forelse ($devices as $device)
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-bold text-[var(--color-heading)]">{{ $device->name }}</p>
                        <p class="text-xs text-[var(--color-muted)]">
                            {{ $device->brand }}@if ($device->device_id) · ID {{ $device->device_id }}@endif
                            @if ($device->ip_address) · {{ $device->ip_address }}:{{ $device->port }}@endif
                        </p>
                        <p class="mt-1 text-xs text-gray-400">
                            Last sync: {{ $device->last_sync_at?->diffForHumans() ?? 'never' }} · {{ $device->logs()->count() }} punch(es)
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $device->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $device->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                {{-- The token an on-site bridge uses to POST logs. --}}
                <div class="mt-3 rounded-lg bg-gray-50 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Push endpoint</p>
                    <p class="mt-1 break-all font-mono text-[11px] text-[var(--color-heading)]">POST {{ url('/api/attendance/device-logs') }}</p>
                    <div class="mt-1 flex items-center gap-2" x-data="{ shown: false }">
                        <span class="break-all font-mono text-[11px] text-[var(--color-muted)]" x-text="shown ? @js($device->api_token) : '••••••••••••••••••••'"></span>
                        <button type="button" @click="shown = !shown" class="shrink-0 text-[11px] font-semibold text-[var(--color-primary)]" x-text="shown ? 'Hide' : 'Show token'"></button>
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-2 border-t border-gray-50 pt-3">
                    <button type="button" @click="importing = importing === {{ $device->id }} ? null : {{ $device->id }}" class="rounded-lg bg-[var(--color-primary-soft)] px-3 py-1.5 text-xs font-semibold text-[var(--color-primary)] hover:opacity-80">Import logs</button>
                    <button type="button" @click="editing = editing === {{ $device->id }} ? null : {{ $device->id }}" class="ml-auto rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-[var(--color-heading)]" title="Edit">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    </button>
                    <form method="POST" action="{{ route('admin.attendance.devices.destroy', $device) }}" onsubmit="return confirm('Remove “{{ $device->name }}”? Its punch logs are kept.')">
                        @csrf @method('DELETE')
                        <button class="rounded-lg p-1.5 text-red-400 hover:bg-red-50 hover:text-red-600" title="Remove">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </form>
                </div>

                {{-- Paste or upload the device's export --}}
                <form x-show="importing === {{ $device->id }}" x-cloak method="POST" action="{{ route('admin.attendance.devices.import', $device) }}" enctype="multipart/form-data" class="mt-3 space-y-2 border-t border-gray-50 pt-3">
                    @csrf
                    <p class="text-xs text-[var(--color-muted)]">
                        Paste CSV lines <span class="font-mono">biometric_id,YYYY-MM-DD HH:MM:SS,in|out</span> (direction optional), or upload the device's export. JSON arrays work too.
                    </p>
                    <textarea name="payload" rows="4" class="w-full rounded-lg border-gray-200 font-mono text-xs" placeholder="1001,2026-07-29 09:58:12,in&#10;1001,2026-07-29 18:31:44,out"></textarea>
                    <input type="file" name="file" accept=".csv,.txt,.json" class="block w-full text-xs text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-3 file:py-2 file:text-xs file:font-semibold file:text-[var(--color-primary)]">
                    <div class="flex gap-2">
                        <button class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-white">Import</button>
                        <button type="button" @click="importing = null" class="px-2 text-xs text-gray-400">Cancel</button>
                    </div>
                </form>

                <form x-show="editing === {{ $device->id }}" x-cloak method="POST" action="{{ route('admin.attendance.devices.update', $device) }}" class="mt-3 space-y-2 border-t border-gray-50 pt-3">
                    @csrf @method('PUT')
                    <input name="name" required maxlength="100" value="{{ $device->name }}" class="h-9 w-full rounded-lg border-gray-200 text-sm" placeholder="Device name">
                    <div class="grid grid-cols-2 gap-2">
                        <input name="device_id" maxlength="100" value="{{ $device->device_id }}" class="h-9 w-full rounded-lg border-gray-200 text-sm" placeholder="Device ID / serial">
                        <input name="brand" maxlength="50" value="{{ $device->brand }}" class="h-9 w-full rounded-lg border-gray-200 text-sm" placeholder="Brand">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input name="ip_address" maxlength="45" value="{{ $device->ip_address }}" class="h-9 w-full rounded-lg border-gray-200 text-sm" placeholder="IP address">
                        <input name="port" type="number" value="{{ $device->port }}" class="h-9 w-full rounded-lg border-gray-200 text-sm" placeholder="Port">
                    </div>
                    <label class="flex items-center gap-2 text-xs text-[var(--color-muted)]">
                        <input type="checkbox" name="is_active" value="1" @checked($device->is_active) class="h-4 w-4 rounded accent-[var(--color-primary)]"> Active
                    </label>
                    <div class="flex gap-2">
                        <button class="rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white">Save</button>
                        <button type="button" @click="editing = null" class="px-2 text-xs text-gray-400">Cancel</button>
                    </div>
                </form>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-200 py-14 text-center lg:col-span-2">
                <p class="text-sm text-gray-400">No devices yet — add your ZKTeco reader to start importing punches.</p>
            </div>
        @endforelse
    </div>

    {{-- Employee ↔ biometric enrolment id --}}
    <div class="mb-6 rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-4">
            <div>
                <h2 class="text-sm font-bold text-[var(--color-heading)]">Employee Biometric IDs</h2>
                <p class="text-xs text-[var(--color-muted)]">The enrolment number each person has on the device. Punches only match once this is filled in.</p>
            </div>
            @if ($unmatched->count())
                <p class="text-xs text-amber-700">Unmatched IDs seen: <span class="font-mono">{{ $unmatched->implode(', ') }}</span></p>
            @endif
        </div>
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="px-5 py-2.5 font-semibold">Employee</th>
                    <th class="px-5 py-2.5 font-semibold">Biometric ID</th>
                    <th class="px-5 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($staff as $person)
                    <tr>
                        <td class="px-5 py-2.5 font-medium text-[var(--color-heading)]">{{ $person->name }}</td>
                        <td class="px-5 py-2.5" colspan="2">
                            <form method="POST" action="{{ route('admin.attendance.biometric-id') }}" class="flex items-center gap-2">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $person->id }}">
                                <input name="biometric_id" maxlength="40" value="{{ $person->biometric_id }}" placeholder="e.g. 1001" class="h-9 w-40 rounded-lg border-gray-200 font-mono text-sm">
                                <button class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-[var(--color-heading)] hover:bg-gray-50">Save</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Raw device logs --}}
    <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <h2 class="text-sm font-bold text-[var(--color-heading)]">Recent Device Logs</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm" style="min-width:760px">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-5 py-2.5 font-semibold">Punched at</th>
                        <th class="px-5 py-2.5 font-semibold">Employee</th>
                        <th class="px-5 py-2.5 font-semibold">Biometric ID</th>
                        <th class="px-5 py-2.5 font-semibold">Direction</th>
                        <th class="px-5 py-2.5 font-semibold">Device</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recentLogs as $log)
                        <tr>
                            <td class="px-5 py-2.5 text-[var(--color-heading)]">{{ $log->punched_at?->format('d M Y, g:i A') }}</td>
                            <td class="px-5 py-2.5">
                                @if ($log->user)
                                    {{ $log->user->name }}
                                @else
                                    <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-semibold text-amber-700">Unmatched</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 font-mono text-xs text-[var(--color-muted)]">{{ $log->biometric_id ?: '—' }}</td>
                            <td class="px-5 py-2.5 text-[var(--color-muted)]">{{ $log->direction ? strtoupper($log->direction) : 'auto' }}</td>
                            <td class="px-5 py-2.5 text-[var(--color-muted)]">{{ $log->device?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-gray-300">No device punches yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Add device --}}
    <div x-data="{ open: false }" @open-device.window="open = true" @keydown.escape.window="open = false">
        <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="open = false"></div>
        <div x-show="open" x-cloak x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-20" @click.self="open = false">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-base font-bold text-[var(--color-heading)]">Add Device</h3>
                    <button type="button" @click="open = false" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('admin.attendance.devices.store') }}" class="space-y-4 p-5">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Device name <span class="text-red-500">*</span></label>
                        <input name="name" required maxlength="100" placeholder="e.g. Main Gate" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Device ID / serial</label>
                            <input name="device_id" maxlength="100" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Brand</label>
                            <input name="brand" maxlength="50" value="ZKTeco" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">IP address</label>
                            <input name="ip_address" maxlength="45" placeholder="192.168.0.201" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-[var(--color-heading)]">Port</label>
                            <input name="port" type="number" value="4370" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-[var(--color-muted)]">
                        <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded accent-[var(--color-primary)]"> Active
                    </label>
                    <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-[var(--color-muted)]">
                        An API token is generated for this device — an on-site sync agent posts the reader's punches to the endpoint shown on its card.
                    </p>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</button>
                        <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
