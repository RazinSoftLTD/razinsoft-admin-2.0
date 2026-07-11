<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DesignationController extends Controller
{
    public function index(Request $request)
    {
        $items = Designation::when($request->query('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->get()
            ->map(function ($d) {
                $d->employees_count = User::where('designation_id', $d->id)->count();
                return $d;
            });

        return view('admin.designations.index', ['items' => $items, 'search' => $request->query('search', '')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('designations', 'name')]]);
        Designation::create($data);

        return back()->with('status', 'Designation added.');
    }

    public function update(Request $request, Designation $designation)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('designations', 'name')->ignore($designation)]]);
        $designation->update($data);

        return back()->with('status', 'Designation updated.');
    }

    public function destroy(Designation $designation)
    {
        User::where('designation_id', $designation->id)->update(['designation_id' => null]);
        $designation->delete();

        return back()->with('status', 'Designation deleted.');
    }
}
