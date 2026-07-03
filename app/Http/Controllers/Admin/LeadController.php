<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function create()
    {
        return view('admin.leads.form', [
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'lead_source' => ['required', Rule::in(Lead::SOURCES)],
            'industry' => ['nullable', Rule::in(Lead::INDUSTRIES)],
            'lead_status' => ['required', Rule::in(array_keys(Lead::STATUSES))],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'zip' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
            'assigned_to' => ['required', 'exists:users,id'],
            'team' => ['nullable', Rule::in(Lead::TEAMS)],
            'priority' => ['required', Rule::in(array_keys(Lead::PRIORITIES))],
        ]);

        Lead::create($data);

        return redirect()
            ->route('admin.leads.create')
            ->with('status', "Lead \"{$data['full_name']}\" saved.");
    }
}
