<?php

namespace App\Services;

use App\Models\MapsLead;
use App\Models\MapsImportRun;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Duplicate detection and persistence for one incoming lead.
 *
 * Why this runs synchronously rather than on the queue: the extension needs a
 * truthful created / duplicate answer in the response so its counters and its
 * own skip-list stay correct. That decision is a single indexed lookup, so it is
 * cheap. Everything that is *not* needed for the answer (log rows, run counters)
 * is pushed onto the queue by the controller.
 */
class MapsLeadIngestService
{
    /**
     * Store or merge a lead.
     *
     * @param  array<string, mixed>  $payload  the validated request body
     * @return array{duplicate: bool, lead: MapsLead}
     */
    public function ingest(array $payload): array
    {
        $leadData = $payload['lead'];
        $search = Arr::get($payload, 'search', []);
        $runId = $payload['run_id'];

        $attributes = [
            'name' => $leadData['name'],
            'maps_url' => $leadData['maps_url'],
            'category' => $leadData['category'] ?? null,
            'address' => $leadData['address'] ?? null,
            'phone' => $leadData['phone'] ?? null,
            'website' => $leadData['website'] ?? null,
            'rating' => $leadData['rating'] ?? null,
            'review_count' => $leadData['review_count'] ?? null,
            'latitude' => $leadData['latitude'] ?? null,
            'longitude' => $leadData['longitude'] ?? null,
            'plus_code' => $leadData['plus_code'] ?? null,
            'price_level' => $leadData['price_level'] ?? null,
            'business_status' => $leadData['business_status'] ?? null,
            'opening_hours' => $leadData['opening_hours'] ?? null,
            'source' => $payload['source'] ?? 'google_maps',
            'search_country' => Arr::get($search, 'country'),
            'search_city' => Arr::get($search, 'city'),
            'search_category' => Arr::get($search, 'category'),
            'search_query' => Arr::get($search, 'query'),
            'position' => $payload['position'] ?? null,
            'collected_at' => $payload['collected_at'] ?? now(),
        ];

        // The unique index on place_key is the real guard; the transaction plus
        // lockForUpdate keeps two concurrent runs from racing on the same row.
        //
        // withTrashed() on purpose: a soft-deleted lead still counts as a
        // duplicate and is refreshed, but stays deleted. An operator who removed
        // a business does not want it back on the next run.
        return DB::transaction(function () use ($leadData, $attributes, $runId) {
            $existing = MapsLead::withTrashed()
                ->where('place_key', $leadData['place_key'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->fill($this->mergeableFields($existing, $attributes));
                $existing->last_run_id = $runId;
                $existing->times_seen = $existing->times_seen + 1;
                $existing->save();

                return ['duplicate' => true, 'lead' => $existing];
            }

            try {
                $lead = MapsLead::create($attributes + [
                    'place_key' => $leadData['place_key'],
                    'first_run_id' => $runId,
                    'last_run_id' => $runId,
                    'times_seen' => 1,
                    'status' => 'new',
                ]);
            } catch (UniqueConstraintViolationException) {
                // Two runs inserted the same place_key at once. The index did its
                // job; report the row that won.
                $lead = MapsLead::withTrashed()->where('place_key', $leadData['place_key'])->firstOrFail();

                return ['duplicate' => true, 'lead' => $lead];
            }

            return ['duplicate' => false, 'lead' => $lead];
        });
    }

    /**
     * On a repeat sighting, only fill in what we did not have before.
     * Operator-owned fields (status, notes, assignment) are never touched, and
     * an existing value is never overwritten with a null.
     *
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeableFields(MapsLead $existing, array $incoming): array
    {
        $patch = [];

        foreach ($incoming as $field => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            // Volatile values worth refreshing on every sighting.
            if (in_array($field, ['rating', 'review_count', 'business_status', 'opening_hours', 'collected_at'], true)) {
                $patch[$field] = $value;

                continue;
            }

            // Everything else is only filled when it is currently missing.
            if (blank($existing->getAttribute($field))) {
                $patch[$field] = $value;
            }
        }

        return $patch;
    }

    /**
     * Create or touch the import-history row for a run.
     */
    public function touchRun(array $payload, ?int $userId = null): MapsImportRun
    {
        $search = Arr::get($payload, 'search', []);

        return MapsImportRun::firstOrCreate(
            ['run_id' => $payload['run_id']],
            [
                'user_id' => $userId,
                'source' => $payload['source'] ?? 'google_maps',
                'country' => Arr::get($search, 'country'),
                'city' => Arr::get($search, 'city'),
                'category' => Arr::get($search, 'category'),
                'query' => Arr::get($search, 'query'),
                'started_at' => now(),
            ]
        );
    }
}
