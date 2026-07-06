<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Staff / employees — admin-panel users (role=staff) that leads get assigned to. */
class StaffController extends Controller
{
    public function index()
    {
        return view('admin.staff.index', [
            'staff' => User::staff()->with('assignedRole')->withCount('assignedLeads')->latest()->paginate(15),
        ]);
    }

    public function create()
    {
        return view('admin.staff.form', ['staff' => new User(['role' => User::ROLE_STAFF]), 'roles' => \App\Models\Role::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['role'] = User::ROLE_STAFF;
        $data['password'] = $request->input('password'); // hashed by cast
        $data['photo'] = $this->storePhoto($request);

        User::create($data);

        return redirect()->route('admin.staff.index')->with('status', 'Staff member added.');
    }

    public function edit(User $staff)
    {
        abort_unless($staff->isStaff(), 404);

        return view('admin.staff.form', ['staff' => $staff, 'roles' => \App\Models\Role::orderBy('name')->get()]);
    }

    public function update(Request $request, User $staff)
    {
        abort_unless($staff->isStaff(), 404);

        $data = $this->validated($request, $staff);
        if ($request->filled('password')) {
            $data['password'] = $request->input('password'); // hashed by cast
        }
        if ($photo = $this->storePhoto($request)) {
            if ($staff->photo) {
                Storage::disk('public')->delete($staff->photo);
            }
            $data['photo'] = $photo;
        }

        $staff->update($data);

        return redirect()->route('admin.staff.index')->with('status', 'Staff member updated.');
    }

    public function destroy(User $staff)
    {
        abort_unless($staff->isStaff(), 404);

        if ($staff->photo) {
            Storage::disk('public')->delete($staff->photo);
        }
        $staff->delete();

        return back()->with('status', 'Staff member removed.');
    }

    private function validated(Request $request, ?User $staff = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff)],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'photo' => ['nullable', 'image', 'max:5120', \App\Support\ImageSpecs::rule('avatar')],
            'password' => [$staff ? 'nullable' : 'required', 'string', 'min:8'],
            'role_id' => ['nullable', 'exists:roles,id'],
            'override' => ['nullable', 'array'],
        ], [
            'photo.dimensions' => \App\Support\ImageSpecs::message('avatar', 'photo'),
        ]);

        // Per-user override map {key:bool}: '1' = allow, '0' = deny, '' = inherit from role (skipped).
        $override = [];
        foreach ((array) $request->input('override', []) as $key => $val) {
            if (! in_array($key, \App\Support\Permissions::keys(), true)) {
                continue;
            }
            if ($val === '1') {
                $override[$key] = true;
            } elseif ($val === '0') {
                $override[$key] = false;
            }
        }
        $data['role_id'] = $request->input('role_id') ?: null;
        $data['permissions'] = $override;
        unset($data['photo']); // handled separately

        return $data;
    }

    /** Store the uploaded photo keeping its ORIGINAL filename (per project rule). */
    private function storePhoto(Request $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        $file = $request->file('photo');

        return $file->storeAs('staff', $file->getClientOriginalName(), 'public');
    }
}
