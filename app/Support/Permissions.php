<?php

namespace App\Support;

/**
 * The panel's grantable permissions. Super admins (role=admin) implicitly have all of them;
 * staff only have what a super admin grants. Keys are stable — used by routes, middleware,
 * the sidebar and the staff form. Grouped only for how they render on the staff form.
 */
class Permissions
{
    public const CATALOG = [
        'CRM' => [
            'leads' => 'Leads & Follow-up',
            'deals' => 'Deals',
            'clients' => 'Clients',
        ],
        'Sales' => [
            'products' => 'Products',
            'orders' => 'Orders',
            'coupons' => 'Coupons',
            'invoices' => 'Invoices & Billing',
        ],
        'Content & Support' => [
            'blog' => 'Blog',
            'subscribers' => 'Subscribers',
            'reviews' => 'Reviews',
            'questions' => 'Questions',
            'messages' => 'Messages',
            'searches' => 'Searches',
        ],
    ];

    /** Sensible starter permissions for a brand-new staff member. */
    public const DEFAULTS = ['leads', 'deals'];

    /** Flat list of every valid permission key. */
    public static function keys(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::CATALOG)));
    }

    public static function label(string $key): string
    {
        foreach (self::CATALOG as $group) {
            if (isset($group[$key])) {
                return $group[$key];
            }
        }

        return ucfirst($key);
    }
}
