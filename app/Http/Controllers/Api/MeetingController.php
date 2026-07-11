<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingSetting;
use App\Models\Meeting;
use App\Support\TemplateMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class MeetingController extends Controller
{
    /** Calendar configuration for the website (working days, hours, bookable range). */
    public function config()
    {
        $s = BookingSetting::current();

        return response()->json([
            'enabled' => $s->is_enabled,
            'working_days' => $s->workingDays(),
            'start_time' => substr($s->start_time, 0, 5),
            'end_time' => substr($s->end_time, 0, 5),
            'slot_minutes' => (int) $s->slot_minutes,
            'advance_days' => (int) $s->advance_days,
            'lead_hours' => (int) $s->lead_hours,
            'min_date' => today()->toDateString(),
            'max_date' => today()->addDays((int) $s->advance_days)->toDateString(),
        ]);
    }

    /** Slots for one date, each flagged available / taken / past. */
    public function availability(Request $request)
    {
        $data = $request->validate(['date' => ['required', 'date']]);
        $date = Carbon::parse($data['date'])->startOfDay();
        $s = BookingSetting::current();

        return response()->json([
            'date' => $date->toDateString(),
            'is_working_day' => $s->is_enabled && $s->isWorkingDay($date),
            'slots' => $s->slotsFor($date),
        ]);
    }

    /** Book a slot from the public site. */
    public function book(Request $request)
    {
        $s = BookingSetting::current();
        abort_unless($s->is_enabled, 403, 'Booking is currently disabled.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'dial_code' => ['nullable', 'string', 'max:8'],
            'company' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'date' => ['required', 'date'],
            'start' => ['required', 'date_format:H:i'],
        ]);

        $date = Carbon::parse($data['date'])->startOfDay();

        // The requested slot must be a real, currently-available window.
        $slot = collect($s->slotsFor($date))->firstWhere('start', $data['start']);
        if (! $slot) {
            throw ValidationException::withMessages(['start' => 'That time is not a valid slot for this date.']);
        }
        if (! $slot['available']) {
            throw ValidationException::withMessages(['start' => 'Sorry, that slot was just taken. Please pick another.']);
        }

        // Link to an existing account, or create a fresh client (they set a password later via "forgot password").
        [$client, $isNew] = $this->resolveClient($data);

        try {
            $meeting = Meeting::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'client_id' => $client->id,
                'phone' => $data['phone'] ?? null,
                'dial_code' => $data['dial_code'] ?? null,
                'company' => $data['company'] ?? null,
                'notes' => ! empty($data['notes']) ? clean($data['notes']) : null,   // rich text, sanitized
                'date' => $date->toDateString(),
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'status' => 'pending',
                'assigned_to' => $s->default_assignee_id,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique (date, start_time) — someone booked it a moment ago.
            throw ValidationException::withMessages(['start' => 'Sorry, that slot was just taken. Please pick another.']);
        }

        // Confirmation email (never let a mail failure break the booking).
        try {
            $frontend = rtrim(config('app.frontend_url', config('services.frontend_url', 'http://localhost:3000')), '/');
            $setUrl = $isNew
                ? $frontend.'/reset-password?token='.Password::broker()->createToken($client).'&email='.urlencode($client->email)
                : $frontend.'/login';

            TemplateMailer::send($meeting->email, 'meeting_booked', [
                'name' => $meeting->name,
                'email' => $meeting->email,
                'day' => $meeting->date->format('l, F j, Y'),
                'slot' => $meeting->slot_label,
                'set_password_url' => $setUrl,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'ok' => true,
            'is_new_client' => $isNew,
            'meeting' => [
                'date' => $meeting->date->toDateString(),
                'day' => $meeting->date->format('l, F j, Y'),
                'slot' => $meeting->slot_label,
                'name' => $meeting->name,
                'email' => $meeting->email,
            ],
        ], 201);
    }

    /** Find the client by email or create a new customer account for them. */
    private function resolveClient(array $data): array
    {
        $client = \App\Models\User::where('email', $data['email'])->first();
        if ($client) {
            // Backfill a phone if we didn't have one and they gave one now.
            if (! $client->phone && ! empty($data['phone'])) {
                $client->forceFill(['phone' => $data['phone'], 'dial_code' => $data['dial_code'] ?? $client->dial_code])->save();
            }

            return [$client, false];
        }

        $client = \App\Models\User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'dial_code' => $data['dial_code'] ?? null,
            'company' => $data['company'] ?? null,
            'role' => \App\Models\User::ROLE_CUSTOMER,
            'status' => 'active',
            // Random password — the client sets their own via "forgot password".
            'password' => \Illuminate\Support\Str::password(20),
        ]);

        return [$client, true];
    }
}
