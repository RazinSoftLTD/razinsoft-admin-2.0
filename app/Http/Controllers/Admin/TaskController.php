<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskComment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Workspace › Tasks — the global task list, task detail, and every task mutation (board included). */
class TaskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Keys that count as done/excluded across all columns (for "hide completed").
        $closedKeys = \App\Models\ProjectColumn::query()
            ->where(fn ($w) => $w->where('is_done', true)->orWhere('is_excluded', true))
            ->pluck('key')->unique()->values()->all() ?: ['completed', 'cancelled'];

        $q = ProjectTask::query()->whereNull('parent_id')
            ->with(['project:id,code,name', 'project.columns', 'assignee:id,name,photo', 'milestone:id,title'])
            ->withCount('subtasks')
            ->whereHas('project', fn ($p) => $p->visibleTo($user));

        // Hide completed by default — exactly like desk.
        $status = $request->query('status', 'hide_completed');
        if ($status === 'hide_completed') {
            $q->whereNotIn('status', $closedKeys);
        } elseif ($status === 'overdue') {
            // Still open and past its due date — matches the Overdue stat card.
            $q->whereNotIn('status', $closedKeys)->whereDate('due_date', '<', now());
        } elseif ($status !== '' && $status !== 'all') {
            $q->where('status', $status);
        }

        // Fresh visits land on "My Tasks"; the other cards pass mine=0 explicitly.
        $mine = $request->has('mine') ? $request->boolean('mine') : true;
        if ($mine) {
            $q->where('assigned_to', $user->id);
        }
        if ($projectId = $request->query('project')) {
            $q->where('project_id', $projectId);
        }
        if ($assignee = $request->query('assignee')) {
            $q->where('assigned_to', $assignee);
        }
        if ($priority = $request->query('priority')) {
            $q->where('priority', $priority);
        }
        if ($from = $request->query('from')) {
            $q->whereDate('due_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('due_date', '<=', $to);
        }
        if ($search = $request->query('search')) {
            $q->where(fn ($x) => $x->where('title', 'like', "%{$search}%")
                ->orWhereHas('project', fn ($p) => $p->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")));
        }

        $base = ProjectTask::query()->whereNull('parent_id')->whereHas('project', fn ($p) => $p->visibleTo($user));
        $stats = [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->whereNotIn('status', $closedKeys)->count(),
            'overdue' => (clone $base)->whereNotIn('status', $closedKeys)->whereDate('due_date', '<', now())->count(),
            'mine' => (clone $base)->where('assigned_to', $user->id)->whereNotIn('status', $closedKeys)->count(),
        ];

        return view('admin.tasks.index', [
            'tasks' => $q->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderByDesc('id')->paginate(25)->withQueryString(),
            'stats' => $stats,
            'mine' => $mine,
            'projects' => $visibleProjects = Project::query()->visibleTo($user)->orderBy('name')->get(['id', 'code', 'name']),
            // Board columns / milestones / parent tasks per project — the Add Task modal
            // swaps these when a different project is picked.
            'projectMeta' => Project::query()->visibleTo($user)
                ->with(['columns:id,project_id,key,name,color', 'milestones:id,project_id,title'])
                ->get(['id', 'code', 'name'])
                ->mapWithKeys(fn ($p) => [$p->id => [
                    'code' => $p->code,
                    'columns' => $p->columns->map(fn ($c) => ['key' => $c->key, 'name' => $c->name, 'color' => $c->color])->values(),
                    'milestones' => $p->milestones->map(fn ($m) => ['id' => $m->id, 'title' => $m->title])->values(),
                    'tasks' => ProjectTask::where('project_id', $p->id)->whereNull('parent_id')
                        ->orderByDesc('id')->limit(100)->get(['id', 'title'])
                        ->map(fn ($t) => ['id' => $t->id, 'title' => $t->title])->values(),
                ]]),
            'nextTaskId' => (int) ProjectTask::withTrashed()->max('id') + 1,
            'assignees' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'statusFilter' => \App\Models\ProjectColumn::defaults()->pluck('name', 'key')->all(),
        ]);
    }

    /** Task detail — description, subtasks, comments, meta sidebar. */
    public function show(Request $request, ProjectTask $task)
    {
        $this->authorizeTask($request, $task);
        $task->load(['project:id,code,name,client_id,time_tracking,hours_allocated', 'project.client:id,name', 'project.columns', 'assignee:id,name,photo,job_title',
            'milestone:id,title', 'parent:id,title', 'subtasks.assignee:id,name,photo', 'comments.user:id,name,photo',
            'files.uploader:id,name', 'activities.user:id,name,photo']);

        return view('admin.tasks.show', [
            'task' => $task,
            'timer' => $task->runningTimer($request->user()->id),
            'loggedMinutes' => $task->loggedMinutes(),
            'todayMinutes' => (int) $task->timeLogs()->whereDate('spent_on', today())->sum('minutes'),
            'staff' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'milestones' => $task->project?->milestones()->get(['id', 'title']) ?? collect(),
            'statusOptions' => $task->project?->statusOptions() ?? ProjectTask::STATUSES,
        ]);
    }

    // ---------------------------------------------------------------- timer

    /** Start a timer, or resume a paused one. */
    public function timerStart(Request $request, ProjectTask $task)
    {
        abort_unless($task->project?->time_tracking, 404);

        $timer = $task->timers()->firstOrNew(['user_id' => $request->user()->id]);
        if ($timer->isRunning()) {
            return back();
        }

        // Only one timer may run at a time — refuse and point at the one already running.
        $other = \App\Models\ProjectTaskTimer::with('task:id,title')
            ->where('user_id', $request->user()->id)
            ->whereNotNull('started_at')
            ->where('task_id', '!=', $task->id)
            ->first();

        if ($other) {
            return back()->withErrors([
                'timer' => 'A timer is already running on “'.($other->task?->title ?? 'another task').'”. Stop it first, then start this one.',
            ]);
        }

        $resumed = $timer->exists;
        $timer->started_at = now();
        $timer->save();
        $task->log('task', $resumed ? 'resumed the timer' : 'started a timer');

        return back();
    }

    /** Pause without logging — the elapsed time is banked until stop. */
    public function timerPause(Request $request, ProjectTask $task)
    {
        $timer = $task->runningTimer($request->user()->id);
        if (! $timer || ! $timer->isRunning()) {
            return back();
        }
        $timer->update(['banked_seconds' => $timer->elapsedSeconds(), 'started_at' => null]);
        $task->log('task', 'paused the timer');

        return back();
    }

    /** Stop the timer and turn everything banked into a time log. */
    public function timerStop(Request $request, ProjectTask $task)
    {
        abort_unless($task->project?->time_tracking, 404);
        $timer = $task->runningTimer($request->user()->id);
        if (! $timer) {
            return back();
        }

        $note = trim((string) $request->input('note'));
        $minutes = max(1, $timer->elapsedMinutes());
        $task->project->timeLogs()->create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'minutes' => $minutes,
            'spent_on' => today(),
            'note' => $note !== '' ? mb_substr($note, 0, 255) : 'Timer',
        ]);
        $timer->delete();
        $task->log('task', \App\Models\ProjectTimeLog::humanMinutes($minutes).' logged from the timer');

        return back()->with('status', \App\Models\ProjectTimeLog::humanMinutes($minutes).' logged.');
    }

    /** Discard a timer without logging anything. */
    public function timerCancel(Request $request, ProjectTask $task)
    {
        $task->runningTimer($request->user()->id)?->delete();

        return back()->with('status', 'Timer discarded.');
    }

    // ---------------------------------------------------------------- attachments

    public function fileStore(Request $request, ProjectTask $task)
    {
        $request->validate(['files' => ['required', 'array'], 'files.*' => ['file', 'max:20480']]);
        foreach ($request->file('files', []) as $upload) {
            $task->files()->create([
                'name' => $upload->getClientOriginalName(),
                'path' => $upload->store('projects/'.$task->project_id.'/tasks/'.$task->id, 'public'),
                'mime' => $upload->getClientMimeType(),
                'size' => $upload->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
        $task->log('file', count($request->file('files', [])).' file(s) attached');

        return back()->with('status', 'File attached.');
    }

    public function fileDownload(ProjectTask $task, \App\Models\ProjectTaskFile $file)
    {
        abort_if($file->task_id !== $task->id, 404);

        return \Illuminate\Support\Facades\Storage::disk('public')->download($file->path, $file->name);
    }

    public function fileDestroy(ProjectTask $task, \App\Models\ProjectTaskFile $file)
    {
        abort_if($file->task_id !== $task->id, 404);
        \Illuminate\Support\Facades\Storage::disk('public')->delete($file->path);
        $name = $file->name;
        $file->delete();
        $task->log('file', 'removed “'.$name.'”');

        return back()->with('status', 'Attachment removed.');
    }

    public function store(Request $request)
    {
        $project = Project::findOrFail($request->input('project_id'));
        $data = $this->validated($request, null, $project);

        // The modal sends a free-text estimate; older forms still post hours + minutes.
        $minutes = ProjectTask::parseEstimate($data['estimate'] ?? null)
            ?: (((int) ($data['estimated_hours'] ?? 0)) * 60 + (int) ($data['estimated_extra_minutes'] ?? 0));

        $data['description'] = \App\Support\Html::clean($data['description'] ?? null);
        $task = new ProjectTask(collect($data)->except(['estimated_hours', 'estimated_extra_minutes', 'estimate', 'attachments'])->all());
        $task->labels = array_values(array_filter(array_map('trim', $data['labels'] ?? [])));
        $task->estimated_minutes = $minutes ?: null;
        $task->created_by = $request->user()->id;
        $task->applyStatus($data['status'], $project->doneKeys());
        $task->save();

        foreach ($request->file('attachments', []) as $upload) {
            $task->files()->create([
                'name' => $upload->getClientOriginalName(),
                'path' => $upload->store('projects/'.$project->id.'/tasks/'.$task->id, 'public'),
                'mime' => $upload->getClientMimeType(),
                'size' => $upload->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        $project->log('task', 'Task “'.$task->title.'” created.', null, $task->id);

        // "Create another" keeps the modal open on the page we came from.
        return back()->with('status', 'Task added.')->with('task_created_again', $request->boolean('create_another'));
    }

    public function update(Request $request, ProjectTask $task)
    {
        $this->authorizeTask($request, $task);

        $project = $task->project;

        // Board drag sends only {status} as JSON.
        if ($request->wantsJson() && $request->has('status') && ! $request->has('title')) {
            $data = $request->validate(['status' => ['required', Rule::in(array_keys($project->statusOptions()))]]);
            $task->applyStatus($data['status'], $project->doneKeys());
            $task->save();

            return response()->json(['ok' => true, 'status' => $task->status]);
        }

        $data = $this->validated($request, $task, $project);
        $minutes = ProjectTask::parseEstimate($data['estimate'] ?? null)
            ?: (((int) ($data['estimated_hours'] ?? 0)) * 60 + (int) ($data['estimated_extra_minutes'] ?? 0));

        $before = ['status' => $task->status, 'priority' => $task->priority, 'assigned_to' => $task->assigned_to];

        $data['description'] = \App\Support\Html::clean($data['description'] ?? null);
        $task->fill(collect($data)->except(['estimated_hours', 'estimated_extra_minutes', 'estimate', 'labels_csv', 'attachments', 'project_id'])->all());
        // The edit modal sends labels as a comma-separated string.
        if ($request->has('labels_csv')) {
            $task->labels = array_values(array_filter(array_map('trim', explode(',', (string) $request->input('labels_csv')))));
        }
        $task->estimated_minutes = $minutes ?: null;
        $task->applyStatus($data['status'], $project->doneKeys());
        $task->save();

        // Record the meaningful changes on the task's own timeline.
        $opts = $project->statusOptions();
        if ($before['status'] !== $task->status) {
            $task->log('task', 'moved this task from '.($opts[$before['status']] ?? $before['status']).' to '.($opts[$task->status] ?? $task->status));
        }
        if ($before['priority'] !== $task->priority) {
            $task->log('task', 'changed priority from '.ucfirst($before['priority']).' to '.ucfirst($task->priority));
        }
        if ($before['assigned_to'] !== $task->assigned_to) {
            $task->log('task', 'changed the assignee');
        }
        $task->project?->log('task', 'Task “'.$task->title.'” updated.');

        return back()->with('status', 'Task updated.');
    }

    /** Inline status change (list dropdown / subtask checkbox). */
    public function status(Request $request, ProjectTask $task)
    {
        $this->authorizeTask($request, $task);
        $project = $task->project;
        $data = $request->validate(['status' => ['required', Rule::in(array_keys($project->statusOptions()))]]);

        if ($data['status'] !== $task->status) {
            $task->applyStatus($data['status'], $project->doneKeys());
            $task->save();
            $project?->log('task', 'Task “'.$task->title.'” moved to '.($project->statusOptions()[$data['status']] ?? $data['status']).'.');
        }

        return back()->with('status', 'Task updated.');
    }

    public function destroy(Request $request, ProjectTask $task)
    {
        $this->authorizeTask($request, $task);
        $title = $task->title;
        $project = $task->project;
        $task->subtasks()->delete();
        $task->delete();
        $project?->log('task', 'Task “'.$title.'” deleted.');

        // From the task page, go back to the project; elsewhere stay put.
        return $request->query('redirect') === 'project' && $project
            ? redirect()->route('admin.projects.show', ['project' => $project, 'tab' => 'tasks'])->with('status', 'Task deleted.')
            : back()->with('status', 'Task deleted.');
    }

    // ---------------------------------------------------------------- comments

    public function commentStore(Request $request, ProjectTask $task)
    {
        $this->authorizeTask($request, $task);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $task->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        $task->project?->log('comment', 'Comment added on “'.$task->title.'”.');

        return back()->with('status', 'Comment added.');
    }

    public function commentDestroy(Request $request, ProjectTask $task, ProjectTaskComment $comment)
    {
        abort_if($comment->task_id !== $task->id, 404);
        abort_unless($comment->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        $comment->delete();

        return back()->with('status', 'Comment deleted.');
    }

    // ---------------------------------------------------------------- internals

    private function authorizeTask(Request $request, ProjectTask $task): void
    {
        abort_unless(Project::query()->visibleTo($request->user())->whereKey($task->project_id)->exists(), 403);
    }

    private function validated(Request $request, ?ProjectTask $task = null, ?Project $project = null): array
    {
        $project ??= $task?->project;
        $statusKeys = $project ? array_keys($project->statusOptions()) : array_keys(ProjectTask::STATUSES);

        return $request->validate([
            'project_id' => [$task ? 'sometimes' : 'required', 'exists:projects,id'],
            'milestone_id' => ['nullable', Rule::exists('project_milestones', 'id')->where('project_id', $task->project_id ?? $request->input('project_id'))],
            'parent_id' => ['nullable', Rule::exists('project_tasks', 'id')->where('project_id', $task->project_id ?? $request->input('project_id'))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::in($statusKeys)],
            'priority' => ['required', Rule::in(array_keys(ProjectTask::PRIORITIES))],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'estimated_extra_minutes' => ['nullable', 'integer', 'min:0', 'max:59'],
            // Free-text estimate ("4h 2d 30m") from the new task modal.
            'estimate' => ['nullable', 'string', 'max:40'],
            'labels_csv' => ['nullable', 'string', 'max:400'],
            'labels' => ['nullable', 'array', 'max:12'],
            'labels.*' => ['string', 'max:30'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:20480'],
        ]);
    }
}
