<?php

namespace App\Services\Email;

use App\Models\EmailSuppression;
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
    ];

    /**
     * Resolve a stored audience filter to recipients.
     *
     * @param  array{type: string, values?: array}  $audience
     * @return Collection<int, User>
     */
    public function resolve(array $audience): Collection
    {
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
        ];
    }
}
