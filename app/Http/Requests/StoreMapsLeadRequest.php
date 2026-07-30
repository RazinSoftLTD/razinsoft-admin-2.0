<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for POST /api/v1/leads/store.
 *
 * The extension already normalises every field, but nothing from a browser is
 * trusted: the rules below are the real contract. A 422 from here is what the
 * extension's "Test connection" button uses to confirm the route and token are
 * both good, so an empty body failing validation is expected behaviour.
 */
class StoreMapsLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is already behind auth:sanctum. Add an ability check here if you
        // start issuing narrower tokens, e.g.:
        // return $this->user()?->tokenCan('leads:write') ?? false;
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'run_id' => ['required', 'string', 'max:64'],
            'source' => ['nullable', 'string', 'max:32'],
            'collected_at' => ['nullable', 'date'],
            'position' => ['nullable', 'integer', 'min:0', 'max:100000'],

            'search' => ['nullable', 'array'],
            'search.country' => ['nullable', 'string', 'max:120'],
            'search.city' => ['nullable', 'string', 'max:120'],
            'search.category' => ['nullable', 'string', 'max:190'],
            'search.query' => ['nullable', 'string', 'max:255'],

            'lead' => ['required', 'array'],
            'lead.place_key' => ['required', 'string', 'max:191'],
            'lead.name' => ['required', 'string', 'max:255'],
            'lead.maps_url' => ['required', 'string', 'max:2048'],
            'lead.category' => ['nullable', 'string', 'max:190'],
            'lead.address' => ['nullable', 'string', 'max:512'],
            'lead.phone' => ['nullable', 'string', 'max:64'],
            'lead.website' => ['nullable', 'string', 'max:512'],
            'lead.rating' => ['nullable', 'numeric', 'between:0,5'],
            'lead.review_count' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'lead.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'lead.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'lead.plus_code' => ['nullable', 'string', 'max:64'],
            'lead.price_level' => ['nullable', 'string', 'max:16'],
            'lead.business_status' => ['nullable', 'string', 'max:64'],
            'lead.opening_hours' => ['nullable', 'array', 'max:14'],
            'lead.opening_hours.*' => ['nullable', 'string', 'max:190'],
        ];
    }

    /**
     * Empty strings arrive from the browser for "not shown on the page"; treat
     * them as nulls so the database stores absence rather than blanks.
     */
    protected function prepareForValidation(): void
    {
        $lead = $this->input('lead');
        if (! is_array($lead)) {
            return;
        }

        foreach ($lead as $key => $value) {
            if ($value === '') {
                $lead[$key] = null;
            }
        }

        $this->merge(['lead' => $lead]);
    }
}
