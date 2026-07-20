<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectPrdItem;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        if (($priority = $request->query('priority')) && array_key_exists($priority, Project::PRIORITIES)) {
            $q->where('priority', $priority);
        }
        if ($client = $request->query('client')) {
            $q->where('client_id', $client);
        }
        // Start-date range
        if ($from = $request->query('from')) {
            $q->whereDate('start_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('start_date', '<=', $to);
        }
        // End-date (deadline) range
        if ($ef = $request->query('end_from')) {
            $q->whereDate('deadline', '>=', $ef);
        }
        if ($et = $request->query('end_to')) {
            $q->whereDate('deadline', '<=', $et);
        }
        // Sort — falls back to the manual drag order when no sort is chosen.
        $sortMap = ['start' => 'start_date', 'end' => 'deadline', 'name' => 'name', 'created' => 'created_at'];
        if (($sort = $request->query('sort')) && isset($sortMap[$sort])) {
            $q->orderBy($sortMap[$sort], $request->query('order') === 'oldest' ? 'asc' : 'desc');
        } else {
            $q->orderBy('position')->orderByDesc('id');
        }

        $base = Project::query()->visibleTo($user);
        $stats = [
            'total' => (clone $base)->count(),
            'in_progress' => (clone $base)->where('status', 'in_progress')->count(),
            'overdue' => (clone $base)->whereDate('deadline', '<', now())->whereNotIn('status', Project::CLOSED_STATUSES)->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
        ];

        return view('admin.projects.index', [
            'projects' => $q->paginate(15)->withQueryString(),
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
        // The Settings tab (per-project permissions, columns, requirements) is manager-only.
        $user = $request->user();
        $allowed = ['overview', 'tasks', 'board', 'activity'];
        foreach (['milestones', 'files', 'members'] as $section) {
            if ($user->allows('projects', $section)) {
                $allowed[] = $section;
            }
        }
        if ($project->needs_requirements && $user->allows('projects', 'prd')) {
            $allowed[] = 'prd';
        }
        if ($project->time_tracking && $user->allows('tasks', 'time')) {
            $allowed[] = 'time';
        }
        if ($user->allows('projects', 'settings')) {
            $allowed[] = 'settings';
        }
        $tab = in_array($tab, $allowed, true) ? $tab : 'overview';
        // Newest task first — the manual sort_order still wins when it has been set.
        $tasks = $project->tasks()->with(['assignee:id,name,photo', 'milestone:id,title', 'subtasks.assignee:id,name,photo'])
            // "My Tasks" on the Tasks tab narrows the list to the current user.
            ->when($tab === 'tasks' && $request->boolean('mine'), fn ($q) => $q->where('assigned_to', $user->id))
            ->reorder()->orderBy('sort_order')->orderByDesc('id')->get();

        return view('admin.projects.show', [
            'project' => $project,
            'tab' => $tab,
            'tasks' => $tasks,
            'staff' => User::assignable()->orderBy('name')->get(['id', 'name', 'photo']),
            'overview' => $tab === 'overview' ? $this->overviewData($project, (string) $request->query('range', 'month')) : null,
            'activities' => $tab === 'activity'
                ? $project->activities()->with('user:id,name,photo')->limit(100)->get()
                : ($tab === 'overview' ? $project->activities()->with('user:id,name,photo')->limit(5)->get() : collect()),
        ]);
    }

    /** Everything the redesigned Overview tab needs (cards, charts, milestone list). */
    private function overviewData(Project $project, string $range = 'month'): array
    {
        $doneKeys = $project->doneKeys();
        $tasks = $project->allTasks()->whereNull('parent_id')->get(['id', 'status', 'milestone_id', 'updated_at']);
        $total = $tasks->count();

        // Donut: one slice per board column, using the column's own colour.
        $breakdown = $project->columns->map(fn ($col) => [
            'name' => $col->name,
            'color' => $col->color ?: '#94a3b8',
            'count' => $c = $tasks->where('status', $col->key)->count(),
            'pct' => $total ? (int) round($c / $total * 100) : 0,
        ])->values()->all();

        $clientMembers = $project->members->filter(fn ($m) => $m->user && $m->user->role === User::ROLE_CUSTOMER)->count();

        // Upcoming milestones with their outstanding task count.
        $upcoming = $project->milestones->where('status', '!=', 'complete')->sortBy('end_date')->take(4)
            ->map(fn ($ms) => [
                'title' => $ms->title,
                'end_date' => $ms->end_date,
                'remaining' => $tasks->where('milestone_id', $ms->id)->whereNotIn('status', $doneKeys)->count(),
            ])->values()->all();

        return [
            'range' => in_array($range, ['month', 'all'], true) ? $range : 'month',
            'tasksTotal' => $total,
            'tasksTodo' => ($first = $project->columns->first()) ? $tasks->where('status', $first->key)->count() : 0,
            'tasksDone' => $tasks->whereIn('status', $doneKeys)->count(),
            'breakdown' => $breakdown,
            'milestonesTotal' => $project->milestones->count(),
            'milestonesDone' => $project->milestones->where('status', 'complete')->count(),
            'membersTotal' => $project->members->count(),
            'membersClient' => $clientMembers,
            'membersTeam' => $project->members->count() - $clientMembers,
            'upcoming' => $upcoming,
            'chart' => $this->progressSeries($project, $tasks, $doneKeys, $range),
        ];
    }

    /**
     * Actual vs planned progress over time.
     * Actual = share of tasks already in a done column by that date.
     * Planned = share of the start→deadline window elapsed by that date.
     */
    private function progressSeries(Project $project, $tasks, array $doneKeys, string $range): array
    {
        $end = now()->endOfDay();
        $start = $range === 'all'
            ? ($project->start_date?->copy() ?? $end->copy()->subDays(30))
            : now()->startOfMonth();
        if ($start->gt($end)) {
            $start = $end->copy()->subDays(14);
        }

        $step = max(1, (int) ceil(max(1, $start->diffInDays($end)) / 12));   // ≤ ~13 points
        $total = max(1, $tasks->count());
        $pStart = $project->start_date?->copy() ?? $start;
        $pEnd = $project->deadline?->copy() ?? $end;
        $span = max(1, $pStart->diffInDays($pEnd));

        $labels = $actual = $planned = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDays($step)) {
            $cut = $d->copy()->endOfDay();
            $labels[] = $d->format('d M');
            $done = $tasks->filter(fn ($t) => in_array($t->status, $doneKeys, true) && $t->updated_at && $t->updated_at->lte($cut))->count();
            $actual[] = (int) round($done / $total * 100);
            $planned[] = (int) max(0, min(100, round($pStart->diffInDays($d, false) / $span * 100)));
        }

        return ['labels' => $labels, 'actual' => $actual, 'planned' => $planned];
    }

    /** Star / un-star this project for the current user. */
    public function toggleFavorite(Request $request, Project $project)
    {
        $this->authorizeView($request, $project);
        $user = $request->user();
        $ids = collect($user->favorite_projects ?? [])->map('intval');
        $ids = $ids->contains($project->id) ? $ids->reject(fn ($id) => $id === $project->id) : $ids->push($project->id);
        $user->forceFill(['favorite_projects' => $ids->values()->all()])->save();

        return back();
    }

    /** Persist drag-and-drop ordering of the list (positions for the reordered page). */
    public function reorder(Request $request)
    {
        abort_unless($request->user()->allows('projects', 'edit'), 403);
        $data = $request->validate([
            'ids' => ['required', 'array'], 'ids.*' => ['integer'],
            'base' => ['nullable', 'integer', 'min:0'],
        ]);
        $base = (int) ($data['base'] ?? 0);
        // Only reorder projects this user can see; keep the given order.
        $allowed = Project::query()->visibleTo($request->user())->whereIn('id', $data['ids'])->pluck('id')->all();
        foreach ($data['ids'] as $i => $id) {
            if (in_array((int) $id, $allowed, true)) {
                Project::whereKey($id)->update(['position' => $base + $i]);
            }
        }

        return response()->json(['ok' => true]);
    }

    /** Compact project detail for the slide-in drawer on the list (loaded via fetch). */
    public function drawer(Request $request, Project $project)
    {
        $this->authorizeView($request, $project);
        $project->load(['client:id,name,company', 'projectManager:id,name,photo', 'parent:id,name,code', 'members.user:id,name,photo,job_title', 'milestones']);
        $project->loadCount(['tasks as tasks_total', 'children as children_count']);

        return view('admin.projects._drawer', compact('project'));
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

    /** Set a member's access level for this project (Settings tab). */
    public function memberAccess(Request $request, Project $project, ProjectMember $member)
    {
        abort_unless($request->user()->allows('projects', 'edit'), 403);
        abort_if($member->project_id !== $project->id, 404);
        $data = $request->validate(['access_level' => ['required', \Illuminate\Validation\Rule::in(array_keys(ProjectMember::ACCESS_LEVELS))]]);
        $member->update($data);

        return back()->with('status', 'Access updated for '.($member->user?->name ?? 'member').'.');
    }

    /** Per-project settings (Settings tab) — currently the requirements-file switch. */
    public function updateSettings(Request $request, Project $project)
    {
        abort_unless($request->user()->allows('projects', 'edit'), 403);
        $valid = array_keys(\App\Models\Project::PRD_SECTIONS);
        $project->update([
            'needs_requirements' => $request->boolean('needs_requirements'),
            'time_tracking' => $request->boolean('time_tracking'),
            'prd_sections' => array_values(array_intersect($valid, (array) $request->input('prd_sections', []))),
        ]);

        return back()->with('status', 'Project settings saved.');
    }

    // ---------------------------------------------------------------- milestones

    public function milestoneStore(Request $request, Project $project)
    {
        $data = $this->milestoneValidated($request);
        $taskIds = $data['task_ids'] ?? null;
        unset($data['task_ids']);
        $milestone = $project->milestones()->create($data);
        $this->syncMilestoneTasks($project, $milestone, $taskIds);
        $project->log('milestone', 'Milestone “'.$data['title'].'” added.');

        return back()->with('status', 'Milestone added.');
    }

    public function milestoneUpdate(Request $request, Project $project, ProjectMilestone $milestone)
    {
        abort_if($milestone->project_id !== $project->id, 404);
        // A bare status flip comes from the list's toggle; a full payload from the edit modal.
        if ($request->has('title')) {
            $data = $this->milestoneValidated($request);
            $taskIds = $data['task_ids'] ?? null;
            unset($data['task_ids']);
            $milestone->update($data);
            $this->syncMilestoneTasks($project, $milestone, $taskIds);
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

    // ---------------------------------------------------------------- PRD

    /** Upload files and/or leave a note against one enabled PRD section. */
    public function prdStore(Request $request, Project $project)
    {
        $this->authorizeView($request, $project);
        $data = $request->validate([
            'section' => ['required', 'string', 'in:'.implode(',', $project->prdSectionKeys())],
            'note' => ['nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480'],
        ]);

        $uploads = $request->file('files', []);
        if (! $uploads && blank($data['note'] ?? null)) {
            return back()->withErrors(['files' => 'Attach a file or write a note.']);
        }

        foreach ($uploads as $upload) {
            $project->prdItems()->create([
                'section' => $data['section'],
                'name' => $upload->getClientOriginalName(),
                'path' => $upload->store('projects/'.$project->id.'/prd', 'public'),
                'mime' => $upload->getClientMimeType(),
                'size' => $upload->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
        if (filled($data['note'] ?? null)) {
            $project->prdItems()->create([
                'section' => $data['section'],
                'note' => $data['note'],
                'uploaded_by' => $request->user()->id,
            ]);
        }

        $label = Project::PRD_SECTIONS[$data['section']][0];
        $project->log('file', 'PRD updated — '.$label.'.');

        return back()->with('status', 'PRD updated.');
    }

    public function prdDownload(Request $request, Project $project, ProjectPrdItem $item)
    {
        abort_if($item->project_id !== $project->id || ! $item->path, 404);
        $this->authorizeView($request, $project);

        return Storage::disk('public')->download($item->path, $item->name);
    }

    public function prdDestroy(Request $request, Project $project, ProjectPrdItem $item)
    {
        abort_if($item->project_id !== $project->id, 404);
        $this->authorizeView($request, $project);
        if ($item->path) {
            Storage::disk('public')->delete($item->path);
        }
        $item->delete();

        return back()->with('status', 'Removed.');
    }

    /** Name, subtitle and avatar — edited from the Settings tab. */
    public function updateProfile(Request $request, Project $project)
    {
        abort_unless($request->user()->allows('projects', 'edit'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:160'],
            'avatar' => ['nullable', 'image', 'max:4096'],
        ]);

        $update = ['name' => $data['name'], 'subtitle' => $data['subtitle'] ?? null];

        if ($request->boolean('remove_avatar') && $project->avatar) {
            Storage::disk('public')->delete($project->avatar);
            $update['avatar'] = null;
        }
        if ($file = $request->file('avatar')) {
            if ($project->avatar) {
                Storage::disk('public')->delete($project->avatar);
            }
            $update['avatar'] = $file->store('projects/'.$project->id.'/avatar', 'public');
        }

        $project->update($update);
        $project->log('updated', 'Project details updated.');

        return back()->with('status', 'Project details saved.');
    }

    /** Log time against the project (optionally against one task). */
    public function timeStore(Request $request, Project $project)
    {
        $this->authorizeView($request, $project);
        abort_unless($project->time_tracking, 404);

        $data = $request->validate([
            'task_id' => ['nullable', 'integer', Rule::exists('project_tasks', 'id')->where('project_id', $project->id)],
            'hours' => ['nullable', 'integer', 'min:0', 'max:999'],
            'minutes' => ['nullable', 'integer', 'min:0', 'max:59'],
            'spent_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $total = ((int) ($data['hours'] ?? 0)) * 60 + (int) ($data['minutes'] ?? 0);
        if ($total < 1) {
            return back()->withErrors(['hours' => 'Enter how long you worked.']);
        }

        $project->timeLogs()->create([
            'task_id' => $data['task_id'] ?? null,
            'user_id' => $request->user()->id,
            'minutes' => $total,
            'spent_on' => $data['spent_on'],
            'note' => $data['note'] ?? null,
        ]);
        $project->log('updated', \App\Models\ProjectTimeLog::humanMinutes($total).' logged.');

        return back()->with('status', 'Time logged.');
    }

    public function timeDestroy(Request $request, Project $project, \App\Models\ProjectTimeLog $log)
    {
        abort_if($log->project_id !== $project->id, 404);
        // Own entries, or anyone's if you can edit the project.
        abort_unless($log->user_id === $request->user()->id || $request->user()->allows('projects', 'edit'), 403);
        $log->delete();

        return back()->with('status', 'Time entry removed.');
    }

    /** Create (or revoke) the client-facing PRD link. */
    public function prdShare(Request $request, Project $project)
    {
        abort_unless($request->user()->allows('projects', 'edit'), 403);

        if ($request->boolean('revoke')) {
            $project->update(['prd_share_token' => null]);
            $project->log('file', 'PRD client link revoked.');

            return back()->with('status', 'Client link revoked.');
        }

        if (! $project->prd_share_token) {
            $project->update(['prd_share_token' => Str::random(48)]);
            $project->log('file', 'PRD client link created.');
        }

        return back()->with('status', 'Client link ready.');
    }

    /** Approve or send back a client submission. */
    public function prdReview(Request $request, Project $project, ProjectPrdItem $item)
    {
        abort_unless($request->user()->allows('projects', 'edit'), 403);
        abort_if($item->project_id !== $project->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected,pending'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $item->update([
            'status' => $data['status'],
            'review_note' => $data['review_note'] ?? null,
            'approved_by' => $data['status'] === 'pending' ? null : $request->user()->id,
            'approved_at' => $data['status'] === 'pending' ? null : now(),
        ]);

        $label = Project::PRD_SECTIONS[$item->section][0] ?? $item->section;
        $project->log('file', 'PRD '.$data['status'].' — '.$label.'.');

        return back()->with('status', 'Review saved.');
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
            'is_done' => $request->boolean('is_done'), 'is_review' => $request->boolean('is_review'), 'is_excluded' => false,
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
        $column->update(['name' => $data['name'], 'color' => $data['color'] ?: $column->color, 'is_done' => $request->boolean('is_done'), 'is_review' => $request->boolean('is_review')]);
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
            'priority' => ['nullable', Rule::in(array_keys(ProjectMilestone::PRIORITIES))],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', Rule::in(array_keys(ProjectMilestone::ICONS))],
            'task_ids' => ['nullable', 'array'],
            'task_ids.*' => ['integer'],
        ]);
    }

    /** Point the chosen tasks at this milestone (and release the ones unticked). */
    private function syncMilestoneTasks(Project $project, ProjectMilestone $milestone, ?array $ids): void
    {
        if ($ids === null) {
            return;
        }
        $ids = array_filter(array_map('intval', $ids));
        $project->allTasks()->where('milestone_id', $milestone->id)->whereNotIn('id', $ids)->update(['milestone_id' => null]);
        if ($ids) {
            $project->allTasks()->whereIn('id', $ids)->update(['milestone_id' => $milestone->id]);
        }
    }
}
