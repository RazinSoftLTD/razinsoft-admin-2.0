@extends('admin.layouts.app')
@section('title', 'Attendance Settings')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">HR &rsaquo; Attendance Settings</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">Turn methods on or off and set the office hours everything is measured against.</p>
    </div>

    @include('admin.attendance._nav')

    <form method="POST" action="{{ route('admin.attendance.settings.update') }}" class="max-w-3xl space-y-6">
        @csrf @method('PUT')

        <section class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-1 text-sm font-bold text-[var(--color-heading)]">Attendance Mode</h2>
            <p class="mb-4 text-xs text-[var(--color-muted)]">Enable any combination. With more than one on, whichever records first wins and the rest report “Attendance already recorded today.”</p>

            <div class="space-y-3">
                <x-admin.toggle name="attendance_enabled" :checked="$settings->attendance_enabled" label="Enable Attendance" hint="Master switch for the whole module." />
                <div class="border-t border-gray-50 pt-3 space-y-3">
                    <x-admin.toggle name="biometric_enabled" :checked="$settings->biometric_enabled" label="Biometric Device" hint="ZKTeco fingerprint / face readers, synced from the Devices tab." />
                    <x-admin.toggle name="web_enabled" :checked="$settings->web_enabled" label="Web Check-In / Check-Out" hint="Employees punch from the admin panel. Records IP, browser and device." />
                    <x-admin.toggle name="login_attendance_enabled" :checked="$settings->login_attendance_enabled" label="Use First Login as Check-In" hint="The first login inside office hours becomes the check-in. Check-out still has to be done manually or on the device." />
                    <x-admin.toggle name="mobile_enabled" :checked="$settings->mobile_enabled" label="Mobile App Check-In" hint="Reserved for the mobile app — the API accepts it once the app ships." />
                    <x-admin.toggle name="manual_enabled" :checked="$settings->manual_enabled" label="Manual Attendance (HR only)" hint="Lets HR enter or correct a day by hand." />
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-bold text-[var(--color-heading)]">Office Hours &amp; Rules</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Office start <span class="text-red-500">*</span></label>
                    <input type="time" name="office_start" required value="{{ \Carbon\Carbon::parse($settings->office_start)->format('H:i') }}" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Office end <span class="text-red-500">*</span></label>
                    <input type="time" name="office_end" required value="{{ \Carbon\Carbon::parse($settings->office_end)->format('H:i') }}" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Late grace period (minutes)</label>
                    <input type="number" name="grace_minutes" min="0" max="240" required value="{{ $settings->grace_minutes }}" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                    <p class="mt-1 text-xs text-[var(--color-muted)]">Checking in after start + grace counts as late.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Minimum working minutes (full day)</label>
                    <input type="number" name="min_work_minutes" min="0" max="1440" required value="{{ $settings->min_work_minutes }}" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Half-day threshold (minutes)</label>
                    <input type="number" name="half_day_minutes" min="0" max="1440" required value="{{ $settings->half_day_minutes }}" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                    <p class="mt-1 text-xs text-[var(--color-muted)]">A closed day under this is marked Half Day.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--color-heading)]">Overtime after (minutes)</label>
                    <input type="number" name="overtime_after_minutes" min="0" max="1440" required value="{{ $settings->overtime_after_minutes }}" class="h-11 w-full rounded-lg border-gray-200 text-sm">
                </div>
            </div>
            <div class="mt-4 border-t border-gray-50 pt-4">
                <x-admin.toggle name="overtime_enabled" :checked="$settings->overtime_enabled" label="Track overtime" hint="Counts minutes worked beyond the threshold above." />
            </div>
        </section>

        <div class="flex justify-end">
            <button class="rounded-lg bg-[var(--color-primary)] px-6 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Save Settings</button>
        </div>
    </form>
@endsection
