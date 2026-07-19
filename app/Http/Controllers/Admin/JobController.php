<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobOpening;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Careers openings — the draft/publish workflow behind the public careers page. */
class JobController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('careers.view'), 403);

        $jobs = JobOpening::with('creator')->latest()->get();

        return view('admin.jobs.index', compact('jobs'));
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->hasPermission('careers.create'), 403);

        return view('admin.jobs.form', ['job' => new JobOpening(['type' => 'Full-time', 'status' => 'draft'])]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('careers.create'), 403);
        $data = $this->validated($request);

        $data['slug'] = JobOpening::uniqueSlug($data['title']);
        $data['created_by'] = $request->user()->id;
        $this->applyStatus($request, $data);

        JobOpening::create($data);

        return redirect()->route('admin.jobs.index')->with('status', 'Opening created.');
    }

    public function edit(Request $request, JobOpening $job)
    {
        abort_unless($request->user()->hasPermission('careers.edit'), 403);

        return view('admin.jobs.form', compact('job'));
    }

    public function update(Request $request, JobOpening $job)
    {
        abort_unless($request->user()->hasPermission('careers.edit'), 403);
        $data = $this->validated($request);

        $data['slug'] = JobOpening::uniqueSlug($data['title'], $job->id);
        $this->applyStatus($request, $data, $job);

        $job->update($data);

        return redirect()->route('admin.jobs.index')->with('status', 'Opening updated.');
    }

    /** Quick publish/unpublish toggle from the list (needs the `publish` permission). */
    public function togglePublish(Request $request, JobOpening $job)
    {
        abort_unless($request->user()->hasPermission('careers.publish'), 403);

        if ($job->isPublished()) {
            $job->update(['status' => 'draft']);
            $msg = 'Opening moved back to draft.';
        } else {
            $job->update(['status' => 'published', 'published_at' => $job->published_at ?? now()]);
            $msg = 'Opening published — it is now live on the website.';
        }

        return back()->with('status', $msg);
    }

    public function destroy(Request $request, JobOpening $job)
    {
        abort_unless($request->user()->hasPermission('careers.delete'), 403);
        $job->delete();

        return back()->with('status', 'Opening removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'department' => ['nullable', 'string', 'max:80'],
            'type' => ['required', Rule::in(JobOpening::TYPES)],
            'location' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:20000'],
            'apply_url' => ['nullable', 'url', 'max:255'],
        ]);
    }

    /**
     * Resolve the requested status. Publishing requires the `publish` permission —
     * a user without it can only ever save a draft, which is the verify-before-live gate.
     */
    private function applyStatus(Request $request, array &$data, ?JobOpening $job = null): void
    {
        $wantPublish = $request->input('status') === 'published'
            && $request->user()->hasPermission('careers.publish');

        $data['status'] = $wantPublish ? 'published' : 'draft';
        if ($data['status'] === 'published') {
            $data['published_at'] = $job?->published_at ?? now();
        }
    }
}
