<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        return view('admin.roles.index', [
            'roles' => Role::withCount('users')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.roles.form', ['role' => new Role(['permissions' => []])]);
    }

    public function store(Request $request)
    {
        Role::create($this->validated($request));

        return redirect()->route('admin.roles.index')->with('status', 'Role created.');
    }

    public function edit(Role $role)
    {
        return view('admin.roles.form', compact('role'));
    }

    public function update(Request $request, Role $role)
    {
        $role->update($this->validated($request));

        return redirect()->route('admin.roles.index')->with('status', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return back()->with('error', 'The built-in role can’t be deleted.');
        }
        if ($role->users()->exists()) {
            return back()->with('error', 'Reassign the staff on this role before deleting it.');
        }
        $role->delete();

        return back()->with('status', 'Role deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
        ]);

        // Keep only valid permission keys.
        $data['permissions'] = array_values(array_intersect($request->input('permissions', []), Permissions::keys()));

        return $data;
    }
}
