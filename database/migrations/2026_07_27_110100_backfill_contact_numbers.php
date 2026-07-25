<?php

use App\Models\ContactNumber;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/** Seed contact_numbers from the phone columns that already exist on leads and clients. */
return new class extends Migration
{
    public function up(): void
    {
        // Leads: phone (primary) + mobile + office_phone.
        Lead::query()->select(['id', 'phone', 'dial_code', 'mobile', 'office_phone', 'country', 'is_whatsapp'])
            ->chunkById(200, function ($leads) {
                foreach ($leads as $lead) {
                    $this->seed($lead, [
                        ['mobile', $lead->phone, $lead->dial_code, (bool) $lead->is_whatsapp],
                        ['mobile', $lead->mobile, $lead->dial_code, false],
                        ['office', $lead->office_phone, $lead->dial_code, false],
                    ]);
                }
            });

        // Clients: phone (primary) + office_phone.
        User::clients()->select(['id', 'phone', 'dial_code', 'office_phone', 'country'])
            ->chunkById(200, function ($clients) {
                foreach ($clients as $client) {
                    $this->seed($client, [
                        ['mobile', $client->phone, $client->dial_code, false],
                        ['office', $client->office_phone, $client->dial_code, false],
                    ]);
                }
            });
    }

    /** Create one row per non-empty number, skipping duplicates within the same owner. */
    private function seed($owner, array $rows): void
    {
        $seen = [];
        $position = 0;
        foreach ($rows as [$label, $number, $dial, $isWa]) {
            $number = trim((string) $number);
            if ($number === '') {
                continue;
            }
            $e164 = ContactNumber::toE164($number, $dial, $owner->country ?? null);
            $key = $e164 ?: mb_strtolower($number);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $owner->contactNumbers()->create([
                'label' => $label,
                'dial_code' => $dial,
                'number' => $number,
                'e164' => $e164,
                'is_primary' => $position === 0,
                'is_whatsapp' => $isWa,
                'position' => $position++,
            ]);
        }
    }

    public function down(): void
    {
        ContactNumber::query()->delete();
    }
};
