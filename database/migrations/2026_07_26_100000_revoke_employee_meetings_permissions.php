<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * The built-in "Employee" role granted meetings.view / meetings.edit (owned), which made the
 * Communication › Meetings (Book Meeting) inbox show for every employee. Meetings is a booking
 * inbox — grant it per-user when an employee actually handles bookings.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::where('name', 'Employee')->first();
        if (! $role) {
            return;
        }
        $perms = (array) $role->permissions;
        unset($perms['meetings.view'], $perms['meetings.edit']);
        $role->permissions = $perms;
        $role->save();
    }

    public function down(): void
    {
        $role = Role::where('name', 'Employee')->first();
        if (! $role) {
            return;
        }
        $perms = (array) $role->permissions;
        $perms['meetings.view'] = 'owned';
        $perms['meetings.edit'] = 'owned';
        $role->permissions = $perms;
        $role->save();
    }
};
