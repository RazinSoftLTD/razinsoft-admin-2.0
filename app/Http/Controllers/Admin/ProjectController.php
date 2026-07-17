<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Workspace › Projects — desk-style project management. */
class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $q = Project::query()->visibleTo($user)
            ->with(['client:id,name', 'members.user:id,name,photo', 'parent:id,name'])
            ->withCount(['tasks as tasks_total', 'children']);

        if ($search = $request->query('search')) {
            $q->where(fn ($x) => $x->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
        }
        if (($status = $request->query('status')) && $status !== 'all') {
            $status === 'overdue'
                ? $q->whereDate('deadline', '<', now())->whereNotIn('status', Project::CLOSED_STATUSES)
                : $q->where('status', $status);
        }
        if ($category = $request->query('category')) {
            $q->where('category', $category);
        }
        if ($client = $request->query('client')) {
            $q->where('client_id', $client);
        }
        if ($from = $request->query('from')) {
            $q->whereDate('start_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('start_date', '<=', $to);
        }

        $base = Project::query()->visibleTo($user);
        $stats = [
            'total' => (clone $base)->count(),
            'in_progress' => (clone $base)->where('status', 'in_progress')->count(),
            'overdue' => (clone $base)->whereDate('deadline', '<', now())->whereNotIn('status', Project::CLOSED_STATUSES)->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
        ];

        return view('admin.projects.index', [
            'projects' => $q->latest('id')->paginate(15)->withQueryString(),
            'stats' => $stats,
            'clients' => User::clients()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('admin.projects.form', array_merge(['project' => new Project()], $this->formOptions()));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $memberIds = $data['member_ids'] ?? [];
        unset($data['member_ids']);
        $data['created_by'] = $request->user()->id;

        $project = Project::create($data);
        foreach (array_unique($memberIds) as $id) {
            $project->members()->create(['user_id' => $id]);
        }
        $this->storeUploads($request, $project);
        $project->log('created', 'Project created.');

        return redirect()->route('admin.projects.show', $project)->with('status', 'Project created.');
    }

    public function show(Request $request, Project $project)
    {
        $this->authorizeView($request, $project);
        $project->load(['client', 'projectManager:id,name,photo', 'parent:id,name,code', 'children' => fn ($c) => $c->withCount('tasks as tasks_total'), 'members.user:id,name,photo,job_title', 'milestones', 'files.uploader:id,name']);

        $project->load('columns');
        $tab = $request->query('tab', 'overview');
        $tasks = $project->tasks()->with(['assignee:id,name,photo', 'milestone:id,title', 'subtasks.assignee:id,name,photo'])->get();

        return view('admin.projects.show', [
            'project' => $project,
            'tab' => in_array($tab, ['overview', 'tasks', 'board', 'milestones', 'files', 'members', 'activity'], true) ? $tab : 'overview',
            'tasks' => $tasks,
            'staff' => User::assignable()->orderBy('name')->get(['id', 'name', 'photo']),
            'activities' => $tab === 'activity' ? $project->activities()->with('user:id,name,photo')->limit(100)->get() : collect(),
        ]);
    }

    public function edit(Request $request, Project $project)
    {
        return view('admin.projects.form', array_merge(['project' => $project->load('members')], $this->formOptions()));
    }

    public function update(Request $request, Project $project)
    {
        $data = $this->validated($request, $project);
        $memberIds = $data['member_ids'] ?? [];
        unset($data['member_ids']);

        $project->update($data);
        $project->members()->whereNotIn('user_id', $memberIds)->delete();
        foreach (array_unique($memberIds) as $id) {
            $project->members()->firstOrCreate(['user_id' => $id]);
        }
        $this->storeUploads($request, $project);
        $project->log('updated', 'Project details updated.');

        return redirect()->route('admin.projects.show', $project)->with('status', 'Project updated.');
    }

    public function status(Request $request, Project $project)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Project::STATUSES))]]);
        if ($data['status'] !== $project->status) {
            $project->update($data);
            $project->log('status', 'Status changed to '.(Project::STATUSES[$data['status']] ?? $data['status']).'.');
        }

        return back()->with('status', 'Project status updated.');
    }

    public function destroy(Request $request, Project $project)
    {
        $project->delete();

        return redirect()->route('admin.projects.index')->with('status', "Project {$project->code} deleted.");
    }

    // ---------------------------------------------------------------- members

    public function memberStore(Request $request, Project $project)
    {
        $data = $request->validate(['user_id' => ['required', 'exists:users,id']]);
        $member = $project->members()->firstOrCreate(['user_id' => $data['user_id']]);
        if ($member->wasRecentlyCreated) {
            $project->log('member', ($member->user?->name ?? 'A member').' added to the project.');
        }

        return back()->with('status', 'Member added.');
    }

    public function memberDestroy(Request $request, Project $project, ProjectMember $member)
    {
        abort_if($member->project_id !== $project->id, 404);
        $name = $member->user?->name ?? 'A member';
        $member->delete();
        $project->log('member', $name.' removed from the project.');

        return back()->with('status', 'Member removed.');
    }

    // ---------------------------------------------------------------- milestones

    public function milestoneStore(Request $request, Project $project)
    {
        $data = $this->milestoneValidated($request);
        $project->milestones()->create($data);
        $project->log('milestone', 'Milestone “'.$data['title'].'” added.');

        return back()->with('status', 'Milestone added.');
    }

    public function milestoneUpdate(Request $request, Project $project, ProjectMilestone $milestone)
    {
        abort_if($milestone->project_id !== $project->id, 404);
        // A bare status flip comes from the list's toggle; a full payload from the edit modal.
        if ($request->has('title')) {
            $milestone->update($this->milestoneValidated($request));
        } else {
            $milestone->update($request->validate(['status' => ['required', Rule::in(array_keys(ProjectMilestone::STATUSES))]]));
        }
        $project->log('milestone', 'Milestone “'.$milestone->title.'” updated.');

        return back()->with('status', 'Milestone updated.');
    }

    public function milestoneDestroy(Request $request, Project $project, ProjectMilestone $milestone)
    {
        abort_if($milestone->project_id !== $project->id, 404);
        $milestone->tasks()->update(['milestone_id' => null]);
        $milestone->delete();
        $project->log('milestone', 'Milestone “'.$milestone->title.'” deleted.');

        return back()->with('status', 'Milestone deleted.');
    }

    // ---------------------------------------------------------------- files

    public function fileStore(Request $request, Project $project)
    {
        $request->validate(['files' => ['required', 'array'], 'files.*' => ['file', 'max:20480']]);
        foreach ($request->file('files', []) as $upload) {
            $project->files()->create([
                'name' => $upload->getClientOriginalName(),
                'path' => $upload->store('projects/'.$project->id, 'public'),
                'size' => $upload->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
        $project->log('file', count($request->file('files', [])).' file(s) uploaded.');

        return back()->with('status', 'File uploaded.');
    }

    public function fileDownload(Request $request, Project $project, ProjectFile $file)
    {
        abort_if($file->project_id !== $project->id, 404);
        $this->authorizeView($request, $project);

        return Storage::disk('public')->download($file->path, $file->name);
    }

    public function fileDestroy(Request $request, Project $project, ProjectFile $file)
    {
        abort_if($file->project_id !== $project->id, 404);
        Storage::disk('public')->delete($file->path);
        $name = $file->name;
        $file->delete();
        $project->log('file', 'File “'.$name.'” deleted.');

        return back()->with('status', 'File deleted.');
    }

    // ---------------------------------------------------------------- internals

    private function authorizeView(Request $request, Project $project): void
    {
        abort_unless(Project::query()->visibleTo($request->user())->whereKey($project->id)->exists(), 403);
    }

    private function formOptions(): array
    {
        return [
            'clients' => User::clients()->orderBy('name')->get(['id', 'name', 'company']),
            'staff' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'parents' => Project::whereNull('parent_id')->orderBy('name')->get(['id', 'code', 'name']),
            'categories' => \App\Models\ProjectCategory::names(),
            'currencies' => \App\Models\Currency::query()->orderBy('code')->pluck('code')->all() ?: ['USD', 'BDT', 'EUR', 'GBP'],
        ];
    }

    // ---------------------------------------------------------------- board columns

    public function columnStore(Request $request, Project $project)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_done' => ['nullable', 'boolean'],
        ]);
        $key = \Illuminate\Support\Str::slug($data['name'], '_') ?: 'col_'.now()->timestamp;
        // Ensure the key is unique within this project.
        $base = $key;
        $i = 1;
        while ($project->columns()->where('key', $key)->exists()) {
            $key = $base.'_'.$i++;
        }
        $project->columns()->create([
            'key' => $key, 'name' => $data['name'], 'color' => $data['color'] ?: '#94a3b8',
            'position' => (int) $project->columns()->max('position') + 1,
            'is_done' => $request->boolean('is_done'), 'is_excluded' => false,
        ]);
        $project->log('column', 'Board column “'.$data['name'].'” added.');

        return back()->with('status', 'Column added.');
    }

    public function columnUpdate(Request $request, Project $project, \App\Models\ProjectColumn $column)
    {
        abort_if($column->project_id !== $project->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_done' => ['nullable', 'boolean'],
        ]);
        $column->update(['name' => $data['name'], 'color' => $data['color'] ?: $column->color, 'is_done' => $request->boolean('is_done')]);
        $project->log('column', 'Board column “'.$column->name.'” updated.');

        return back()->with('status', 'Column updated.');
    }

    public function columnDestroy(Request $request, Project $project, \App\Models\ProjectColumn $column)
    {
        abort_if($column->project_id !== $project->id, 404);
        if ($project->columns()->count() <= 1) {
            return back()->with('error', 'A project must keep at least one column.');
        }
        // Move any tasks in this column to the first remaining column.
        $fallback = $project->columns()->where('id', '!=', $column->id)->orderBy('position')->first();
        \App\Models\ProjectTask::where('project_id', $project->id)->where('status', $column->key)->update(['status' => $fallback->key]);
        $name = $column->name;
        $column->delete();
        $project->log('column', 'Board column “'.$name.'” removed.');

        return back()->with('status', 'Column removed. Its tasks moved to “'.$fallback->name.'”.');
    }

    /** Save any files posted alongside the project form (create + edit). */
    private function storeUploads(Request $request, Project $project): void
    {
        foreach ($request->file('files', []) as $upload) {
            $project->files()->create([
                'name' => $upload->getClientOriginalName(),
                'path' => $upload->store('projects/'.$project->id, 'public'),
                'size' => $upload->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    private function validated(Request $request, ?Project $project = null): array
    {
        // "No deadline" checkbox clears the date before validation runs.
        if ($request->boolean('no_deadline')) {
            $request->merge(['deadline' => null]);
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('projects', 'code')->ignore($project?->id)],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:projects,id', Rule::notIn([$project?->id])],
            'client_id' => ['nullable', 'exists:users,id'],
            'category' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(Project::PRIORITIES))],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'hours_allocated' => ['nullable', 'integer', 'min:0'],
            'auto_progress' => ['nullable', 'boolean'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'summary' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'project_manager_id' => ['nullable', 'exists:users,id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:users,id'],
        ]);
        $data['auto_progress'] = $request->boolean('auto_progress');
        $data['currency'] = $data['currency'] ?? 'USD';
        // Blank code → let the model auto-generate.
        if (blank($data['code'] ?? null)) {
            unset($data['code']);
        }

        return $data;
    }

    private function milestoneValidated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(array_keys(ProjectMilestone::STATUSES))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
