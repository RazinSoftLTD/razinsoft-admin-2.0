<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $items = Department::when($request->query('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->get()
            ->map(function ($d) {
                $d->employees_count = User::where('department_id', $d->id)->count();
                return $d;
            });

        return view('admin.departments.index', ['items' => $items, 'search' => $request->query('search', '')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('departments', 'name')]]);
        Department::create($data);

        return back()->with('status', 'Department added.');
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('departments', 'name')->ignore($department)]]);
        $department->update($data);

        return back()->with('status', 'Department updated.');
    }

    public function destroy(Department $department)
    {
        User::where('department_id', $department->id)->update(['department_id' => null]);
        $department->delete();

        return back()->with('status', 'Department deleted.');
    }
}
