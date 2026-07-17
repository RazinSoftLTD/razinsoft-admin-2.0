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
        } elseif ($status !== '' && $status !== 'all') {
            $q->where('status', $status);
        }

        if ($request->boolean('mine')) {
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
            'projects' => Project::query()->visibleTo($user)->orderBy('name')->get(['id', 'code', 'name']),
            'assignees' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'statusFilter' => \App\Models\ProjectColumn::defaults()->pluck('name', 'key')->all(),
        ]);
    }

    /** Task detail — description, subtasks, comments, meta sidebar. */
    public function show(Request $request, ProjectTask $task)
    {
        $this->authorizeTask($request, $task);
        $task->load(['project:id,code,name,client_id', 'project.client:id,name', 'project.columns', 'assignee:id,name,photo,job_title',
            'milestone:id,title', 'parent:id,title', 'subtasks.assignee:id,name,photo', 'comments.user:id,name,photo']);

        return view('admin.tasks.show', [
            'task' => $task,
            'staff' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'milestones' => $task->project?->milestones()->get(['id', 'title']) ?? collect(),
            'statusOptions' => $task->project?->statusOptions() ?? ProjectTask::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $project = Project::findOrFail($request->input('project_id'));
        $data = $this->validated($request, null, $project);

        $minutes = ((int) ($data['estimated_hours'] ?? 0)) * 60 + (int) ($data['estimated_extra_minutes'] ?? 0);
        $task = new ProjectTask(collect($data)->except(['estimated_hours', 'estimated_extra_minutes'])->all());
        $task->estimated_minutes = $minutes ?: null;
        $task->created_by = $request->user()->id;
        $task->applyStatus($data['status'], $project->doneKeys());
        $task->save();

        $project->log('task', 'Task “'.$task->title.'” created.');

        return back()->with('status', 'Task added.');
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
        $minutes = ((int) ($data['estimated_hours'] ?? 0)) * 60 + (int) ($data['estimated_extra_minutes'] ?? 0);
        $task->fill(collect($data)->except(['estimated_hours', 'estimated_extra_minutes', 'project_id'])->all());
        $task->estimated_minutes = $minutes ?: null;
        $task->applyStatus($data['status'], $project->doneKeys());
        $task->save();
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
        ]);
    }
}
