<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Default permissions for the built-in "Employee" role.
 *
 * Employees are internal staff, not sales — so no CRM (leads / clients) and no billing (invoices).
 * They only reach projects they're assigned to, and in the Employees directory they can only
 * edit / delete their own (self) record.
 */
class EmployeeRoleSeeder extends Seeder
{
    public const PERMISSIONS = [
        // Workspace — assigned projects only (owner = project_manager_id; membership also counts).
        'projects.view' => 'owned',
        'projects.edit' => 'owned',

        // Employees directory — view own record only (self). No add / update / delete;
        // profile changes go through "My Profile" instead.
        'employees.view' => 'owned',

        // Tickets — own only: they raise their own tickets and view / update just those.
        // (reply is granted but record-gated to tickets they can view, i.e. their own.)
        'tickets.view' => 'owned',
        'tickets.create' => 'all',
        'tickets.edit' => 'owned',
        'tickets.reply' => 'all',
        'leave.view' => 'owned',
        'leave.create' => 'all',

        // Explicitly NOT granted: leads, deals, clients, invoices, meetings (booking inbox — grant per-user if needed).
    ];

    public function run(): void
    {
        $role = Role::firstOrNew(['name' => 'Employee']);
        $role->is_system = true;
        $role->description = $role->description ?: 'Internal staff — assigned projects, self profile, support.';
        $role->permissions = self::PERMISSIONS;
        $role->save();
    }
}
