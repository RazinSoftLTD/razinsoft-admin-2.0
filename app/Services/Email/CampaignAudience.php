<?php

namespace App\Services\Email;

use App\Models\EmailSuppression;
use App\Models\MapsLead;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Works out who a campaign goes to.
 *
 * Kept apart from the controller so the same filter can be counted before sending and resolved
 * again when the campaign actually runs — the audience is stored as the filter, not as a frozen
 * list of addresses, so a campaign scheduled for tomorrow includes anyone who qualifies by then.
 */
class CampaignAudience
{
    /** The ways a campaign can be aimed, in the order the form shows them. */
    public const TYPES = [
        'all' => 'All clients',
        'selected' => 'Specific clients',
        'label' => 'By client label',
        'category' => 'By product category',
        'country' => 'By country',
        'company' => 'By company',
        'product' => 'Bought a specific product',

        // --- collected Google Maps leads, not account holders ---------------
        'maps_category' => 'Maps leads: by business category',
        'maps_city' => 'Maps leads: by city',
        'maps_country' => 'Maps leads: by country',
        'maps_engaged' => 'Maps leads: opened or clicked before',
    ];

    /** Audience types that resolve to maps_leads rows rather than users. */
    public const MAPS_TYPES = ['maps_category', 'maps_city', 'maps_country', 'maps_engaged'];

    public static function isMaps(?string $type): bool
    {
        return in_array($type, self::MAPS_TYPES, true);
    }

    /**
     * Resolve a stored audience filter to recipients.
     *
     * @param  array{type: string, values?: array}  $audience
     * @return Collection<int, User>
     */
    public function resolve(array $audience): Collection
    {
        if (self::isMaps($audience['type'] ?? null)) {
            return $this->resolveMapsLeads($audience);
        }

        $values = array_filter((array) ($audience['values'] ?? []));

        $query = User::clients()
            ->where('status', User::STATUS_ACTIVE)      // never mail a blocked account
            ->whereNotNull('email');

        $query = match ($audience['type'] ?? 'all') {
            'selected' => $query->whereIn('id', $values),
            'label' => $query->whereIn('client_label', $values),
            'category' => $query->where(fn ($q) => $q->whereIn('client_category', $values)
                ->orWhereIn('client_sub_category', $values)),
            'country' => $query->whereIn('country', $values),
            'company' => $query->whereIn('company', $values),
            'product' => $query->whereHas('orders.items', fn ($q) => $q->whereIn('product_id', $values)),
            default => $query,
        };

        $recipients = $query->get(['id', 'name', 'email', 'company']);

        // Drop anything on the suppression list here as well as at send time, so the count an
        // admin sees before pressing Send is the number that will really be mailed.
        $blocked = EmailSuppression::filter($recipients->pluck('email')->all());

        return $recipients->reject(fn (User $u) => in_array(mb_strtolower($u->email), $blocked, true))->values();
    }

    /**
     * Resolve a Maps-lead audience.
     *
     * Only leads with a discovered address are eligible, and only ones not
     * already contacted — the collector's own outreach may have mailed some of
     * them, and a campaign must not be the second unsolicited message.
     *
     * @param  array{type: string, values?: array}  $audience
     * @return Collection<int, MapsLead>
     */
    private function resolveMapsLeads(array $audience): Collection
    {
        $values = array_filter((array) ($audience['values'] ?? []));
        $type = $audience['type'];

        $query = MapsLead::query()
            ->whereNotNull('email')->where('email', '!=', '');

        if ($type === 'maps_engaged') {
            /*
             * Retargeting: leads that opened or clicked something we sent. These
             * have deliberately already been contacted — that is the point — so
             * the first-contact guard below does not apply to them.
             */
            $query->whereHas('emailLogs', function ($q) {
                $q->whereNotNull('first_opened_at')->orWhereNotNull('first_clicked_at');
            });
        } else {
            // First contact: never mail a lead twice without meaning to.
            $query->whereNull('outreach_sent_at');

            $query = match ($type) {
                'maps_category' => $query->whereIn('category', $values),
                'maps_city' => $query->whereIn('search_city', $values),
                'maps_country' => $query->whereIn('search_country', $values),
                default => $query,
            };
        }

        $leads = $query->get(['id', 'name', 'email', 'category', 'search_city', 'search_country']);

        $blocked = EmailSuppression::filter($leads->pluck('email')->all());

        return $leads
            ->reject(fn (MapsLead $l) => in_array(mb_strtolower($l->email), $blocked, true))
            ->values();
    }

    public function count(array $audience): int
    {
        return $this->resolve($audience)->count();
    }

    /** The choices each filter offers, read from the data that actually exists. */
    public function options(): array
    {
        return [
            'label' => User::clients()->whereNotNull('client_label')->where('client_label', '!=', '')
                ->distinct()->orderBy('client_label')->pluck('client_label')->all(),
            'category' => \App\Models\ProductCategory::pickerOptions(),
            'country' => User::clients()->whereNotNull('country')->where('country', '!=', '')
                ->distinct()->orderBy('country')->pluck('country')->all(),
            'company' => User::clients()->whereNotNull('company')->where('company', '!=', '')
                ->distinct()->orderBy('company')->limit(200)->pluck('company')->all(),
            'product' => \App\Models\Product::orderBy('name')->pluck('name', 'id')->all(),

            /*
             * Maps-lead choices are read from leads that actually have an email
             * address, so the form never offers a category whose leads are all
             * unreachable — picking one and getting nobody looks like a bug.
             */
            'maps_category' => $this->mapsOptions('category'),
            'maps_city' => $this->mapsOptions('search_city'),
            'maps_country' => $this->mapsOptions('search_country'),
        ];
    }

    /**
     * Distinct values of one maps_leads column among mailable, uncontacted leads,
     * each with how many leads it would reach.
     *
     * @return array<string, string> value => "Label (n)"
     */
    private function mapsOptions(string $column): array
    {
        return MapsLead::query()
            ->whereNotNull('email')->where('email', '!=', '')
            ->whereNull('outreach_sent_at')
            ->whereNotNull($column)->where($column, '!=', '')
            ->selectRaw("{$column} as value, COUNT(*) as total")
            ->groupBy($column)
            ->orderByDesc('total')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->value => "{$r->value} ({$r->total})"])
            ->all();
    }
}
