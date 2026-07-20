<?php

namespace App\Support;

/**
 * Granular, SCOPE-based panel permissions.
 *
 * A permission is a `module.action` key whose value is a SCOPE:
 *   none · owned · added · both (added & owned) · all
 *
 * - `owned`  → rows the user owns   (module `owner` column, e.g. assigned_to / user_id)
 * - `added`  → rows the user created (module `creator` column, e.g. added_by / created_by)
 * - `both`   → owned OR added
 * - `all`    → everyone's rows
 *
 * Only view/edit/delete are scopable, and only for modules that expose an owner
 * and/or creator column. `create` and special actions are simple none/all.
 *
 * Stored on Role.permissions / User.permissions as a map {"module.action": "scope"}.
 * Legacy flat-list roles (["leads.view", "leads.view_all", ...]) are read transparently.
 */
class Permissions
{
    /** Scope ladder (narrow → wide) with plain-language UI labels. */
    public const SCOPES = [
        'none' => 'No access',
        'owned' => 'Their own',
        'added' => 'They created',
        'both' => 'Own + created',
        'all' => "Everyone's",
    ];

    /** One-line plain explanation of each scope — shown as a tooltip in the matrix. */
    public const SCOPE_HELP = [
        'none' => 'No access to this at all.',
        'owned' => 'Only records assigned to this person.',
        'added' => 'Only records this person created.',
        'both' => 'Records assigned to OR created by this person.',
        'all' => "All records — from everyone.",
    ];

    /** The CRUD actions that support scoping (when the module has owner/creator columns). */
    public const SCOPABLE_ACTIONS = ['view', 'edit', 'delete'];

    /**
     * Extra (non-CRUD) actions that ALSO support the owned/added scope ladder,
     * per module. For clients, each detail-page section can be limited to
     * owned / added clients rather than a flat none/all.
     */
    public const SCOPABLE_EXTRA = [
        'clients' => ['projects', 'invoices', 'payments', 'documents', 'notes', 'tickets'],
        // Invoice operations that act on a specific invoice → scopable by "added" (created_by).
        'invoices' => ['finance', 'send', 'cancel', 'duplicate'],
    ];

    /**
     * module => [label, group, actions[], owner column|null, creator column|null].
     * `owner`/`creator` unlock the owned/added scopes for that module.
     */
    public const MODULES = [
        'leads' => ['label' => 'Leads', 'group' => 'CRM', 'actions' => ['view', 'create', 'edit', 'delete', 'settings'], 'owner' => 'assigned_to', 'creator' => 'added_by'],
        'deals' => ['label' => 'Deals', 'group' => 'CRM', 'actions' => ['view', 'create', 'edit', 'delete'], 'owner' => 'assigned_to'],
        // clients: view/create/edit/delete are the base CRUD (scoped via account_manager_id
        // = owned / created_by = added). Profile rides on view; the other section actions each
        // gate the matching detail-page tab and are individually scopable (owned/added/all).
        'clients' => ['label' => 'Clients', 'group' => 'CRM', 'actions' => ['view', 'create', 'edit', 'delete', 'import_export', 'projects', 'invoices', 'payments', 'documents', 'notes', 'tickets'], 'owner' => 'account_manager_id', 'creator' => 'created_by'],
        'analytics' => ['label' => 'Analytics', 'group' => 'CRM', 'actions' => ['view']],
        // Activity logs — each page is its own toggle (Employee actions, Client visits, Blogs & Products reports).
        'activity' => ['label' => 'Activity Logs', 'group' => 'Activity', 'actions' => ['employee', 'client', 'blogs', 'products']],
        // CodeCanyon market analysis (Activity → CodeCanyon) + its API config.
        'codecanyon' => ['label' => 'CodeCanyon', 'group' => 'Activity', 'actions' => ['view', 'manage', 'settings']],
        'projects' => ['label' => 'Projects', 'group' => 'Workspace', 'actions' => ['view', 'create', 'edit', 'delete', 'members', 'milestones', 'files', 'prd', 'settings', 'columns'], 'owner' => 'project_manager_id', 'creator' => 'created_by'],
        // Tasks live inside projects but each part of a task is its own toggle.
        'tasks' => ['label' => 'Tasks', 'group' => 'Workspace', 'actions' => ['view', 'create', 'edit', 'delete', 'status', 'comments', 'attachments', 'time', 'subtasks'], 'owner' => 'assigned_to', 'creator' => 'created_by'],
        // invoices: base CRUD scoped via created_by (= added). The extra ops (payments/send/
        // cancel/duplicate) are individually scopable; configuration + bin are global none/all.
        'invoices' => ['label' => 'Invoices', 'group' => 'Sales', 'actions' => ['view', 'create', 'edit', 'delete', 'finance', 'send', 'cancel', 'duplicate', 'configure', 'bin'], 'owner' => 'owner_id', 'creator' => 'created_by'],
        'products' => ['label' => 'Products', 'group' => 'Sales', 'actions' => ['view', 'create', 'edit', 'delete', 'publish', 'clone', 'relations']],
        // Installation Plans has its own screens (features, plans, copy-from), so it gets its own toggles.
        'installation_plans' => ['label' => 'Installation Plans', 'group' => 'Sales', 'actions' => ['view', 'create', 'edit', 'delete', 'copy']],
        'orders' => ['label' => 'Orders', 'group' => 'Sales', 'actions' => ['view', 'create']],
        'coupons' => ['label' => 'Coupons', 'group' => 'Sales', 'actions' => ['view', 'create', 'edit', 'delete']],
        'blog' => ['label' => 'Blog', 'group' => 'Content', 'actions' => ['view', 'create', 'edit', 'delete']],
        'subscribers' => ['label' => 'Subscribers', 'group' => 'Content', 'actions' => ['view', 'create', 'delete']],
        'reviews' => ['label' => 'Reviews', 'group' => 'Content', 'actions' => ['view', 'edit', 'delete']],
        'questions' => ['label' => 'Questions', 'group' => 'Content', 'actions' => ['view', 'answer', 'delete']],
        'messages' => ['label' => 'Contact Us', 'group' => 'Content', 'actions' => ['view', 'edit', 'delete']],
        'searches' => ['label' => 'Searches', 'group' => 'Content', 'actions' => ['view', 'delete']],
        // owner = client_id: the ticket's requester. "Owned" scope = tickets the user raised themselves.
        'tickets' => ['label' => 'Tickets', 'group' => 'Support', 'actions' => ['view', 'create', 'edit', 'reply', 'delete', 'settings'], 'owner' => 'client_id'],
        'chat' => ['label' => 'Team Chat', 'group' => 'Support', 'actions' => ['create_group', 'clients']],
        'whatsapp' => ['label' => 'WhatsApp', 'group' => 'Support', 'actions' => ['view', 'reply', 'assign', 'activity', 'settings', 'connection', 'numbers', 'labels', 'webhook', 'quick_replies']],
        'meetings' => ['label' => 'Meetings', 'group' => 'Booking', 'actions' => ['view', 'assign', 'edit', 'delete', 'settings'], 'owner' => 'assigned_to'],
        // owner = 'id': the employee record IS the user, so "Owned" scope means their own (self) record.
        'employees' => ['label' => 'Employees', 'group' => 'HR', 'actions' => ['view', 'create', 'edit', 'delete'], 'owner' => 'id'],
        // Careers openings — `publish` gates who can push a draft live to the public site.
        'careers' => ['label' => 'Careers', 'group' => 'HR', 'actions' => ['view', 'create', 'edit', 'delete', 'publish']],
        'designations' => ['label' => 'Designations', 'group' => 'HR', 'actions' => ['view', 'create', 'edit', 'delete']],
        'departments' => ['label' => 'Departments', 'group' => 'HR', 'actions' => ['view', 'create', 'edit', 'delete']],
        'leave' => ['label' => 'Leave', 'group' => 'HR', 'actions' => ['view', 'create', 'approve', 'delete'], 'owner' => 'user_id'],
    ];

    /** Human labels for each action. */
    public const ACTION_LABELS = [
        'view' => 'View', 'create' => 'Add', 'edit' => 'Update', 'delete' => 'Delete',
        'finance' => 'Payments', 'answer' => 'Answer', 'reply' => 'Reply', 'approve' => 'Approve',
        'create_group' => 'Create groups', 'assign' => 'Assign', 'settings' => 'Config page', 'publish' => 'Publish', 'quick_replies' => 'Quick Replies',
        'connection' => 'Connection Method', 'numbers' => 'WhatsApp Numbers', 'labels' => 'Labels', 'webhook' => 'Webhook',
        'clients' => 'Client messages',
        // Invoice operations.
        'send' => 'Send', 'cancel' => 'Cancel', 'duplicate' => 'Duplicate', 'configure' => 'Configuration', 'bin' => 'Trash',
        'import_export' => 'Import / Export',
        // Product & installation-plan operations.
        'clone' => 'Duplicate product', 'relations' => 'Manage related content', 'copy' => 'Copy from another product',
        // Project & task sections.
        'members' => 'Members', 'milestones' => 'Milestones', 'files' => 'Files', 'prd' => 'PDR (Project Development Requirements)',
        'columns' => 'Board columns', 'status' => 'Move on board', 'comments' => 'Comments',
        'attachments' => 'Attachments', 'time' => 'Time tracking', 'subtasks' => 'Subtasks',
        // Activity log pages.
        'employee' => 'Employee', 'client' => 'Client', 'blogs' => 'Blogs', 'products' => 'Products',
        // Client detail-page sections (Profile rides on view — no separate action).
        'projects' => 'Projects', 'invoices' => 'Invoices',
        'payments' => 'Payments', 'documents' => 'Documents', 'notes' => 'Notes', 'tickets' => 'Tickets',
    ];

    /** A brand-new staff/role starts with owned read access to the CRM basics. */
    public const DEFAULTS = ['leads.view' => 'owned', 'deals.view' => 'owned'];

    public static function owner(string $module): ?string
    {
        return self::MODULES[$module]['owner'] ?? null;
    }

    public static function creator(string $module): ?string
    {
        return self::MODULES[$module]['creator'] ?? null;
    }

    public static function isScopable(string $module): bool
    {
        return self::owner($module) !== null || self::creator($module) !== null;
    }

    /** The scope options offered for a given module+action, in ladder order. */
    public static function scopesFor(string $module, string $action): array
    {
        $owner = self::owner($module);
        $creator = self::creator($module);

        $scopable = in_array($action, self::SCOPABLE_ACTIONS, true)
            || in_array($action, self::SCOPABLE_EXTRA[$module] ?? [], true);

        if (! $scopable || (! $owner && ! $creator)) {
            return ['none', 'all'];
        }

        $scopes = ['none'];
        if ($owner) {
            $scopes[] = 'owned';
        }
        if ($creator) {
            $scopes[] = 'added';
        }
        if ($owner && $creator) {
            $scopes[] = 'both';
        }
        $scopes[] = 'all';

        return $scopes;
    }

    /** The four CRUD columns shown in the matrix (present only if the module has them). */
    public static function crudActions(string $module): array
    {
        return array_values(array_intersect(['view', 'create', 'edit', 'delete'], self::MODULES[$module]['actions'] ?? []));
    }

    /** Non-CRUD "more" actions (finance, reply, approve, …) — simple none/all. */
    public static function extraActions(string $module): array
    {
        return array_values(array_diff(self::MODULES[$module]['actions'] ?? [], ['view', 'create', 'edit', 'delete']));
    }

    /** Every valid `module.action` key. */
    public static function keys(): array
    {
        $keys = [];
        foreach (self::MODULES as $mod => $cfg) {
            foreach ($cfg['actions'] as $act) {
                $keys[] = "{$mod}.{$act}";
            }
        }

        return $keys;
    }

    /** Normalise a single stored value (bool | scope string) to a scope key. */
    public static function scopeValue($raw): string
    {
        if ($raw === true) {
            return 'all';
        }
        if ($raw === false || $raw === null) {
            return 'none';
        }

        return array_key_exists((string) $raw, self::SCOPES) ? (string) $raw : 'none';
    }

    /**
     * Turn any stored permissions blob (new map OR legacy flat list) into a
     * clean {"module.action": "scope"} map, dropping unknown keys / 'none'.
     */
    public static function normalize($perms): array
    {
        $perms = (array) $perms;
        if (empty($perms)) {
            return [];
        }

        $map = [];

        if (array_is_list($perms)) {
            // Legacy: a flat list of granted keys, with optional `module.view_all`.
            $viewAll = [];
            foreach ($perms as $key) {
                if (! is_string($key)) {
                    continue;
                }
                if (str_ends_with($key, '.view_all')) {
                    $viewAll[substr($key, 0, -9)] = true;

                    continue;
                }
                $map[$key] = 'all';
            }
            foreach (array_keys($map) as $key) {
                if (str_ends_with($key, '.view')) {
                    $module = substr($key, 0, -5);
                    $map[$key] = ! empty($viewAll[$module])
                        ? 'all'
                        : (self::isScopable($module) ? 'owned' : 'all');
                }
            }
        } else {
            foreach ($perms as $key => $value) {
                $map[$key] = self::scopeValue($value);
            }
        }

        // Keep only known keys with a real (non-none) scope, clamped to allowed options.
        $valid = self::keys();
        $out = [];
        foreach ($map as $key => $scope) {
            if ($scope === 'none' || ! in_array($key, $valid, true)) {
                continue;
            }
            [$mod, $act] = explode('.', $key, 2);
            $allowed = self::scopesFor($mod, $act);
            $out[$key] = in_array($scope, $allowed, true) ? $scope : 'all';
        }

        return $out;
    }

    public static function scopeLabel(string $scope): string
    {
        return self::SCOPES[$scope] ?? ucfirst($scope);
    }

    /** True when an action is a plain yes/no (only none/all — no owned/added nuance). */
    public static function isSimple(string $module, string $action): bool
    {
        return self::scopesFor($module, $action) === ['none', 'all'];
    }

    /**
     * Context-aware option label. Yes/no actions (create, settings, …) read
     * "No access / Allowed"; scopable ones read "Their own / Everyone's" etc.
     */
    public static function optionLabel(string $module, string $action, string $scope): string
    {
        if (self::isSimple($module, $action)) {
            return $scope === 'all' ? 'Allowed' : 'No access';
        }

        return self::scopeLabel($scope);
    }

    /** Tooltip text for an option, matching optionLabel's context. */
    public static function optionHelp(string $module, string $action, string $scope): string
    {
        if (self::isSimple($module, $action)) {
            return $scope === 'all' ? 'This role can do this.' : 'This role cannot do this.';
        }

        return self::SCOPE_HELP[$scope] ?? '';
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
