<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Employees — admin-panel users (admins + staff) with full HR profile. */
class StaffController extends Controller
{
    public const LANGUAGES = ['en' => 'English', 'bn' => 'Bangla', 'hi' => 'Hindi', 'ar' => 'Arabic', 'es' => 'Spanish', 'fr' => 'French'];

    public function index(Request $request)
    {
        $q = User::assignable()
            ->with('assignedRole', 'designation', 'department', 'reportsTo')
            ->withCount('assignedLeads')
            ->latest();

        // Employees "view" scope — "Owned" (owner column = id) limits the list to the user's own record (self).
        $request->user()->applyScope($q, 'employees', 'view');

        if (array_key_exists($request->query('role'), [User::ROLE_ADMIN => 1, User::ROLE_STAFF => 1])) {
            $q->where('role', $request->query('role'));
        }
        if ($designation = $request->query('designation')) {
            $q->where('designation_id', $designation);
        }
        if ($search = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('employee_code', 'like', "%{$search}%"));
        }

        return view('admin.staff.index', [
            'staff' => $q->paginate((int) $request->query('per_page', 10) ?: 10)->withQueryString(),
            'search' => $search ?? '',
            'role' => $request->query('role', ''),
            'designationId' => $request->query('designation', ''),
            'designations' => Designation::orderBy('name')->get(),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    /** Read-only employee profile — respects the "view" scope so staff can open their own record. */
    public function show(Request $request, User $staff)
    {
        abort_unless($staff->isStaff() || $staff->isAdmin(), 404);
        abort_unless($request->user()->canAct('employees', 'view', $staff), 403);

        return view('admin.staff.show', [
            'staff' => $staff->load('assignedRole', 'designation', 'department', 'reportsTo'),
            'canEdit' => $request->user()->canAct('employees', 'edit', $staff),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->hasPermission('employees.create'), 403);

        return view('admin.staff.form', array_merge($this->formData(), [
            'staff' => new User([
                'role' => User::ROLE_STAFF,
                'role_id' => optional(Role::where('name', 'Employee')->first())->id,
                'status' => User::STATUS_ACTIVE,
                'language' => 'en',
                'joining_date' => now(),
            ]),
        ]));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('employees.create'), 403);
        $data = $this->validated($request);
        $data['role'] = User::ROLE_STAFF;
        // Default new employees to the "Employee" role unless a role was explicitly chosen.
        $data['role_id'] = $data['role_id'] ?: optional(\App\Models\Role::where('name', 'Employee')->first())->id;
        $data['password'] = $request->input('password'); // hashed by cast
        if ($photo = $this->storePhoto($request)) {
            $data['photo'] = $photo;
        }

        $employee = User::create($data);
        if (blank($employee->employee_code)) {
            $employee->update(['employee_code' => 'RS-'.str_pad((string) $employee->id, 3, '0', STR_PAD_LEFT)]);
        }
        $this->recordPassword($employee, $request->input('password'), $request->user()->id);

        return redirect()->route('admin.staff.index')->with('status', 'Employee added.');
    }

    public function edit(Request $request, User $staff)
    {
        abort_unless($staff->isStaff(), 404);
        abort_unless($request->user()->canAct('employees', 'edit', $staff), 403);

        return view('admin.staff.form', array_merge($this->formData(), [
            'staff' => $staff,
            // Recorded passwords are super-admin-only; hand an empty set to everyone else.
            'passwordHistory' => $request->user()->isSuperAdmin() ? $staff->passwordHistories()->with('setter')->get() : collect(),
        ]));
    }

    public function update(Request $request, User $staff)
    {
        abort_unless($staff->isStaff(), 404);
        abort_unless($request->user()->canAct('employees', 'edit', $staff), 403);

        $data = $this->validated($request, $staff);
        // User Role is super-admin territory — never let a non-admin change it.
        if (! $request->user()->isAdmin()) {
            unset($data['role_id']);
        }
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
        if ($request->filled('password')) {
            $this->recordPassword($staff, $request->input('password'), $request->user()->id);
        }

        return redirect()->route('admin.staff.index')->with('status', 'Employee updated.');
    }

    public function destroy(Request $request, User $staff)
    {
        abort_unless($staff->isStaff(), 404);
        // Delete scope: "Owned" (id = self) lets a user remove only their own employee record.
        abort_unless($request->user()->canAct('employees', 'delete', $staff), 403);

        if ($staff->photo) {
            Storage::disk('public')->delete($staff->photo);
        }
        $staff->delete();

        return back()->with('status', 'Employee removed.');
    }

    /** Inline user-role change from the list. */
    public function updateRole(Request $request, User $staff)
    {
        abort_unless($staff->isStaff(), 404);
        $data = $request->validate(['role_id' => ['nullable', 'exists:roles,id']]);
        $staff->update(['role_id' => $data['role_id'] ?: null]);

        return back()->with('status', 'User role updated.');
    }

    /** Quick-add a designation (from the form's "Add" button). Returns JSON. */
    public function storeDesignation(Request $request)
    {
        $v = validator($request->all(), ['name' => ['required', 'string', 'max:120', Rule::unique('designations', 'name')]]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $d = Designation::create($v->validated());

        return response()->json(['id' => $d->id, 'name' => $d->name]);
    }

    /** Quick-add a department. Returns JSON. */
    public function storeDepartment(Request $request)
    {
        $v = validator($request->all(), ['name' => ['required', 'string', 'max:120', Rule::unique('departments', 'name')]]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $d = Department::create($v->validated());

        return response()->json(['id' => $d->id, 'name' => $d->name]);
    }

    private function formData(): array
    {
        return [
            'roles' => Role::orderBy('name')->get(),
            'designations' => Designation::orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
            'reportable' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'languages' => self::LANGUAGES,
            'employmentTypes' => User::EMPLOYMENT_TYPES,
            'countries' => config('countries'),
            'nextCode' => 'RS-'.str_pad((string) (((int) User::max('id')) + 1), 3, '0', STR_PAD_LEFT),
        ];
    }

    private function validated(Request $request, ?User $staff = null): array
    {
        $data = $request->validate([
            'employee_code' => ['nullable', 'string', 'max:40'],
            'salutation' => ['nullable', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff)],
            'phone' => ['nullable', 'string', 'max:40'],
            'dial_code' => ['nullable', 'string', 'max:8'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'password' => [$staff ? 'nullable' : 'required', 'string', 'min:4'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'country' => ['nullable', 'string', 'max:120'],
            'joining_date' => ['nullable', 'date'],
            'date_of_birth' => ['nullable', 'date'],
            'reporting_to' => ['nullable', 'exists:users,id'],
            'language' => ['nullable', 'string', 'max:10'],
            'role_id' => ['nullable', 'exists:roles,id'],
            'address' => ['nullable', 'string', 'max:500'],
            'about' => ['nullable', 'string', 'max:2000'],
            'employment_type' => ['nullable', Rule::in(array_keys(User::EMPLOYMENT_TYPES))],
            'probation_end_date' => ['nullable', 'date'],
            'notice_start_date' => ['nullable', 'date'],
            'notice_end_date' => ['nullable', 'date'],
            'login_allowed' => ['boolean'],
            'receive_email_notifications' => ['boolean'],
        ]);

        $data['status'] = ($data['login_allowed'] ?? true) ? User::STATUS_ACTIVE : User::STATUS_BLOCKED;
        $data['role_id'] = $request->input('role_id') ?: null;
        // Password is handled explicitly by store()/update() so an empty field on
        // edit never nulls the existing hash — drop it from the validated payload.
        unset($data['photo'], $data['login_allowed'], $data['password']);

        return $data;
    }

    /** Log the plaintext password (encrypted at rest) so a super admin can review it later. */
    private function recordPassword(User $employee, string $plain, ?int $setBy): void
    {
        $employee->passwordHistories()->create([
            'password' => $plain,
            'set_by' => $setBy,
            'created_at' => now(),
        ]);
    }

    public function permissions(User $staff)
    {
        abort_unless($staff->isStaff(), 404);

        return view('admin.staff.permissions', ['staff' => $staff->load('assignedRole')]);
    }

    public function updatePermissions(Request $request, User $staff)
    {
        abort_unless($staff->isStaff(), 404);
        $request->validate(['override' => ['nullable', 'array']]);

        // Store explicit per-user scope overrides. '' = inherit (skip). Others are scope keys.
        $override = [];
        foreach ((array) $request->input('override', []) as $key => $val) {
            if ($val === '' || $val === null || ! in_array($key, \App\Support\Permissions::keys(), true)) {
                continue;
            }
            [$mod, $act] = explode('.', $key, 2);
            $allowed = in_array($act, ['view', 'create', 'edit', 'delete'], true)
                ? \App\Support\Permissions::scopesFor($mod, $act)
                : ['none', 'all'];
            if (in_array($val, $allowed, true)) {
                $override[$key] = $val;
            }
        }
        $staff->update(['permissions' => $override]);

        return redirect()->route('admin.staff.index')->with('status', "Permissions updated for {$staff->name}.");
    }

    private function storePhoto(Request $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }
        $file = $request->file('photo');

        return $file->storeAs('staff', $file->getClientOriginalName(), 'public');
    }
}
