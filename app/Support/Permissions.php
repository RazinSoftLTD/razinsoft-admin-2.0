<?php

namespace App\Support;

/**
 * Granular panel permissions as `module.action` keys (e.g. `clients.view`, `invoices.finance`).
 * Single source of truth for roles, the middleware, the sidebar and the management UI.
 *
 * Scopable modules (those with an owner column) also get a virtual `module.view_all` key:
 * without it, `view` is limited to the user's OWN rows; with it, they see everyone's.
 */
class Permissions
{
    /** module => [label, group, actions[], scope owner-column|null] */
    public const MODULES = [
        'leads' => ['label' => 'Leads', 'group' => 'CRM', 'actions' => ['view', 'create', 'edit', 'delete'], 'scope' => 'assigned_to'],
        'deals' => ['label' => 'Deals', 'group' => 'CRM', 'actions' => ['view', 'create', 'edit', 'delete'], 'scope' => 'assigned_to'],
        'clients' => ['label' => 'Clients', 'group' => 'CRM', 'actions' => ['view', 'create', 'edit', 'delete']],
        'invoices' => ['label' => 'Invoices', 'group' => 'Sales', 'actions' => ['view', 'create', 'edit', 'delete', 'finance'], 'scope' => 'created_by'],
        'products' => ['label' => 'Products', 'group' => 'Sales', 'actions' => ['view', 'create', 'edit', 'delete']],
        'orders' => ['label' => 'Orders', 'group' => 'Sales', 'actions' => ['view', 'create']],
        'coupons' => ['label' => 'Coupons', 'group' => 'Sales', 'actions' => ['view', 'create', 'edit', 'delete']],
        'blog' => ['label' => 'Blog', 'group' => 'Content', 'actions' => ['view', 'create', 'edit', 'delete']],
        'subscribers' => ['label' => 'Subscribers', 'group' => 'Content', 'actions' => ['view', 'create', 'delete']],
        'reviews' => ['label' => 'Reviews', 'group' => 'Content', 'actions' => ['view', 'edit', 'delete']],
        'questions' => ['label' => 'Questions', 'group' => 'Content', 'actions' => ['view', 'answer', 'delete']],
        'messages' => ['label' => 'Messages', 'group' => 'Content', 'actions' => ['view', 'delete']],
        'searches' => ['label' => 'Searches', 'group' => 'Content', 'actions' => ['view', 'delete']],
        // Support
        'tickets' => ['label' => 'Tickets', 'group' => 'Support', 'actions' => ['view', 'create', 'edit', 'reply', 'delete']],
        // Team chat — direct messaging is open to every panel user; only group creation is gated.
        'chat' => ['label' => 'Team Chat', 'group' => 'Support', 'actions' => ['create_group']],
        // HR
        'employees' => ['label' => 'Employees', 'group' => 'HR', 'actions' => ['view', 'create', 'edit', 'delete']],
        'designations' => ['label' => 'Designations', 'group' => 'HR', 'actions' => ['view', 'create', 'edit', 'delete']],
        'departments' => ['label' => 'Departments', 'group' => 'HR', 'actions' => ['view', 'create', 'edit', 'delete']],
        'leave' => ['label' => 'Leave', 'group' => 'HR', 'actions' => ['view', 'create', 'approve', 'delete'], 'scope' => 'user_id'],
    ];

    /** Human labels for each action (used in the matrix UI). */
    public const ACTION_LABELS = [
        'view' => 'View', 'view_all' => 'View all', 'create' => 'Create', 'edit' => 'Edit',
        'delete' => 'Delete', 'finance' => 'Finance', 'answer' => 'Answer', 'reply' => 'Reply', 'approve' => 'Approve',
        'create_group' => 'Create groups',
    ];

    /** A brand-new staff/role starts with read access to the CRM basics. */
    public const DEFAULTS = ['leads.view', 'deals.view'];

    /** Every valid permission key (`module.action` + `module.view_all` for scopable modules). */
    public static function keys(): array
    {
        $keys = [];
        foreach (self::MODULES as $mod => $cfg) {
            foreach ($cfg['actions'] as $act) {
                $keys[] = "{$mod}.{$act}";
            }
            if (! empty($cfg['scope'])) {
                $keys[] = "{$mod}.view_all";
            }
        }

        return $keys;
    }

    /** Actions to render for a module in the matrix, including the virtual view_all for scopables. */
    public static function actionsFor(string $module): array
    {
        $cfg = self::MODULES[$module] ?? null;
        if (! $cfg) {
            return [];
        }
        $actions = $cfg['actions'];
        if (! empty($cfg['scope'])) {
            // insert view_all right after view
            $out = [];
            foreach ($actions as $a) {
                $out[] = $a;
                if ($a === 'view') {
                    $out[] = 'view_all';
                }
            }

            return $out;
        }

        return $actions;
    }

    /** Owner column for a scopable module (null if it shows everyone's rows to anyone with view). */
    public static function scopeColumn(string $module): ?string
    {
        return self::MODULES[$module]['scope'] ?? null;
    }

    public static function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? ucfirst($action);
    }

    public static function moduleLabel(string $module): string
    {
        return self::MODULES[$module]['label'] ?? ucfirst($module);
    }

    /** Modules grouped for the matrix UI: ['CRM' => ['leads'=>cfg, ...], ...]. */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::MODULES as $mod => $cfg) {
            $groups[$cfg['group']][$mod] = $cfg;
        }

        return $groups;
    }
}
