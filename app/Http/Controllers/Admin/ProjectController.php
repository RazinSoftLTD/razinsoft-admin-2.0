<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChecklistTemplate;
use App\Models\Project;
use App\Models\ProjectChangeRequest;
use App\Models\ProjectChecklistItem;
use App\Models\ProjectDocument;
use App\Models\ProjectTask;
use App\Models\ProjectWorkstream;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    /* ---------------------------------------------------------------- Projects */

    public function index(Request $request)
    {
        $q = Project::query()->with('client:id,name', 'projectManager:id,name')
            ->withCount(['allTasks as tasks_total', 'workstreams']);

        // Constrain to the projects this user may see. A non-"all" scope means
        // "assigned to me": project manager, creator, or a project member.
        $user = $request->user();
        $scope = $user->permissionScope('projects', 'view');
        if ($scope === 'none') {
            $q->whereRaw('1 = 0');
        } elseif ($scope !== 'all') {
            $q->where(function ($w) use ($user, $scope) {
                if (in_array($scope, ['owned', 'both'], true)) {
                    $w->orWhere('project_manager_id', $user->id);
                }
                if (in_array($scope, ['added', 'both'], true)) {
                    $w->orWhere('created_by', $user->id);
                }
                $w->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id));
            });
        }

        if ($search = $request->query('search')) {
            $q->where(fn ($x) => $x->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('company', 'like', "%{$search}%"));
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($type = $request->query('type')) {
            $q->where('project_type', $type);
        }
        if ($priority = $request->query('priority')) {
            $q->where('priority', $priority);
        }

        $all = Project::query();
        $stats = [
            'total' => (clone $all)->count(),
            'active' => (clone $all)->whereNotIn('status', Project::CLOSED_STATUSES)->count(),
            'on_hold' => (clone $all)->where('status', 'on_hold')->count(),
            'completed' => (clone $all)->where('status', 'completed')->count(),
        ];

        return view('admin.projects.index', [
            'projects' => $q->latest('id')->paginate(12)->withQueryString(),
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        return view('admin.projects.form', ['project' => new Project(['status' => 'draft', 'priority' => 'medium', 'currency' => 'BDT'])] + $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $project = Project::create($data);
        $project->log('created', "Project “{$project->name}” created.");

        return redirect()->route('admin.projects.show', $project)->with('status', 'Project created.');
    }

    /** A non-"all" project scope only reaches projects the user is assigned to (PM / creator / member). */
    private function authorizeProject(Request $request, Project $project): void
    {
        $user = $request->user();
        if ($user->isAdmin() || $user->permissionScope('projects', 'view') === 'all') {
            return;
        }
        $reachable = $project->project_manager_id === $user->id
            || $project->created_by === $user->id
            || $project->members()->where('user_id', $user->id)->exists();
        abort_unless($reachable, 403);
    }

    public function show(Request $request, Project $project)
    {
        $this->authorizeProject($request, $project);
        $project->load([
            'client', 'projectManager', 'salesPerson', 'accountManager',
            'workstreams', 'members.user:id,name',
            'tasks.subtasks', 'tasks.assignee:id,name', 'tasks.workstream:id,name',
            'checklistItems', 'documents.uploader:id,name', 'changeRequests.requester:id,name',
            'activityLogs.user:id,name',
        ]);

        $tab = $request->query('tab', 'overview');

        return view('admin.projects.show', [
            'project' => $project,
            'tab' => $tab,
            'staff' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'availableTemplates' => ChecklistTemplate::where('project_type', $project->project_type)->orderBy('category')->orderBy('sort_order')->get(),
        ]);
    }

    public function edit(Request $request, Project $project)
    {
        $this->authorizeProject($request, $project);

        return view('admin.projects.form', ['project' => $project] + $this->formData());
    }

    public function update(Request $request, Project $project)
    {
        $this->authorizeProject($request, $project);
        $project->update($this->validated($request));
        $project->log('updated', "Project details updated.");

        return redirect()->route('admin.projects.show', $project)->with('status', 'Project updated.');
    }

    public function destroy(Request $request, Project $project)
    {
        $this->authorizeProject($request, $project);
        $project->delete();

        return redirect()->route('admin.projects.index')->with('status', 'Project deleted.');
    }

    /** Quick status change from the detail header. */
    public function status(Request $request, Project $project)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Project::STATUSES))]]);
        if ($data['status'] !== $project->status) {
            $project->update($data);
            if ($data['status'] === 'delivered' || $data['status'] === 'completed') {
                $project->forceFill(['actual_delivery' => $project->actual_delivery ?? now()->toDateString()])->save();
            }
            $project->log('status', 'Status changed to '.(Project::STATUSES[$data['status']] ?? $data['status']).'.');
        }

        return back()->with('status', 'Status updated.');
    }

    /* --------------------------------------------------------------- Workstreams */

    public function workstreamStore(Request $request, Project $project)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(ProjectWorkstream::TYPES)],
            'status' => ['required', Rule::in(array_keys(ProjectWorkstream::STATUSES))],
        ]);
        $data['sort_order'] = (int) $project->workstreams()->max('sort_order') + 1;
        $project->workstreams()->create($data);
        $project->log('workstream', "Workstream “{$data['name']}” added.");

        return back()->with('status', 'Workstream added.');
    }

    public function workstreamUpdate(Request $request, Project $project, ProjectWorkstream $workstream)
    {
        abort_if($workstream->project_id !== $project->id, 404);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(ProjectWorkstream::TYPES)],
            'status' => ['sometimes', Rule::in(array_keys(ProjectWorkstream::STATUSES))],
            'progress' => ['sometimes', 'integer', 'between:0,100'],
        ]);
        $workstream->update($data);

        return back()->with('status', 'Workstream updated.');
    }

    public function workstreamDestroy(Project $project, ProjectWorkstream $workstream)
    {
        abort_if($workstream->project_id !== $project->id, 404);
        $workstream->delete();

        return back()->with('status', 'Workstream removed.');
    }

    /* -------------------------------------------------------------------- Tasks */

    public function taskStore(Request $request, Project $project)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'workstream_id' => ['nullable', Rule::exists('project_workstreams', 'id')->where('project_id', $project->id)],
            'parent_id' => ['nullable', Rule::exists('project_tasks', 'id')->where('project_id', $project->id)],
            'status' => ['required', Rule::in(array_keys(ProjectTask::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(ProjectTask::PRIORITIES))],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ]);
        $data['created_by'] = $request->user()->id;
        $task = $project->allTasks()->create($data);
        $project->log('task', 'Task “'.$task->title.'” created.');

        return back()->with('status', 'Task added.');
    }

    public function taskUpdate(Request $request, Project $project, ProjectTask $task)
    {
        abort_if($task->project_id !== $project->id, 404);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'workstream_id' => ['nullable', Rule::exists('project_workstreams', 'id')->where('project_id', $project->id)],
            'status' => ['sometimes', Rule::in(array_keys(ProjectTask::STATUSES))],
            'priority' => ['sometimes', Rule::in(array_keys(ProjectTask::PRIORITIES))],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ]);
        $task->update($data);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'status' => $task->status]);
        }

        return back()->with('status', 'Task updated.');
    }

    public function taskDestroy(Project $project, ProjectTask $task)
    {
        abort_if($task->project_id !== $project->id, 404);
        $task->delete();

        return back()->with('status', 'Task removed.');
    }

    /* ---------------------------------------------------------------- Checklist */

    /** Generate checklist items for this project from its type's templates (skips ones already present). */
    public function checklistGenerate(Request $request, Project $project)
    {
        $templates = ChecklistTemplate::where('project_type', $project->project_type)->orderBy('sort_order')->get();
        $existing = $project->checklistItems()->pluck('title')->all();
        $added = 0;
        foreach ($templates as $t) {
            if (in_array($t->title, $existing, true)) {
                continue;
            }
            $project->checklistItems()->create([
                'category' => $t->category, 'title' => $t->title, 'required' => $t->required,
                'status' => 'waiting', 'requested_at' => now(), 'sort_order' => $t->sort_order,
            ]);
            $added++;
        }
        $project->log('checklist', "Generated {$added} checklist item(s) from template.");

        return back()->with('status', $added ? "{$added} checklist item(s) added." : 'Checklist already up to date.');
    }

    public function checklistStore(Request $request, Project $project)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'required' => ['nullable', 'boolean'],
            'deadline' => ['nullable', 'date'],
        ]);
        $data['required'] = $request->boolean('required');
        $data['status'] = 'waiting';
        $data['requested_at'] = now();
        $project->checklistItems()->create($data);

        return back()->with('status', 'Checklist item added.');
    }

    public function checklistUpdate(Request $request, Project $project, ProjectChecklistItem $item)
    {
        abort_if($item->project_id !== $project->id, 404);
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(array_keys(ProjectChecklistItem::STATUSES))],
            'comment' => ['nullable', 'string', 'max:2000'],
            'deadline' => ['nullable', 'date'],
        ]);
        if (($data['status'] ?? null) === 'received' && ! $item->received_at) {
            $data['received_at'] = now();
        }
        $item->update($data);
        if (isset($data['status'])) {
            $project->log('checklist', "Checklist “{$item->title}” → ".(ProjectChecklistItem::STATUSES[$item->status] ?? $item->status).'.');
        }

        return back()->with('status', 'Checklist updated.');
    }

    public function checklistDestroy(Project $project, ProjectChecklistItem $item)
    {
        abort_if($item->project_id !== $project->id, 404);
        $item->delete();

        return back()->with('status', 'Checklist item removed.');
    }

    /* ---------------------------------------------------------------- Documents */

    public function documentStore(Request $request, Project $project)
    {
        $request->validate([
            'type' => ['required', Rule::in(Project::DOCUMENT_TYPES)],
            'file' => ['required', 'file', 'max:20480'],
        ]);
        $file = $request->file('file');
        $project->documents()->create([
            'type' => $request->type,
            'name' => $file->getClientOriginalName(),
            'path' => $file->store('projects/documents', 'public'),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'uploaded_by' => $request->user()->id,
        ]);
        $project->log('document', "Document “{$file->getClientOriginalName()}” uploaded.");

        return back()->with('status', 'Document uploaded.');
    }

    public function documentDestroy(Project $project, ProjectDocument $document)
    {
        abort_if($document->project_id !== $project->id, 404);
        Storage::disk('public')->delete($document->path);
        $document->delete();

        return back()->with('status', 'Document removed.');
    }

    /* ------------------------------------------------------------------ Members */

    public function memberStore(Request $request, Project $project)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['nullable', 'string', 'max:100'],
        ]);
        $project->members()->firstOrCreate(['user_id' => $data['user_id']], ['role' => $data['role'] ?? null]);

        return back()->with('status', 'Member added.');
    }

    public function memberDestroy(Project $project, \App\Models\ProjectMember $member)
    {
        abort_if($member->project_id !== $project->id, 404);
        $member->delete();

        return back()->with('status', 'Member removed.');
    }

    /* ----------------------------------------------------------- Change requests */

    public function changeRequestStore(Request $request, Project $project)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(array_keys(ProjectChangeRequest::PRIORITIES))],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'estimated_time' => ['nullable', 'string', 'max:100'],
        ]);
        $data['requested_by'] = $request->user()->id;
        $project->changeRequests()->create($data);
        $project->log('change_request', "Change request “{$data['title']}” raised.");

        return back()->with('status', 'Change request added.');
    }

    public function changeRequestUpdate(Request $request, Project $project, ProjectChangeRequest $changeRequest)
    {
        abort_if($changeRequest->project_id !== $project->id, 404);
        $data = $request->validate([
            'approval_status' => ['sometimes', Rule::in(array_keys(ProjectChangeRequest::APPROVAL_STATUSES))],
            'development_status' => ['sometimes', Rule::in(array_keys(ProjectChangeRequest::DEVELOPMENT_STATUSES))],
        ]);
        $changeRequest->update($data);

        return back()->with('status', 'Change request updated.');
    }

    public function changeRequestDestroy(Project $project, ProjectChangeRequest $changeRequest)
    {
        abort_if($changeRequest->project_id !== $project->id, 404);
        $changeRequest->delete();

        return back()->with('status', 'Change request removed.');
    }

    /* ------------------------------------------------------------------- Helpers */

    private function formData(): array
    {
        return [
            'clients' => User::clients()->orderBy('name')->get(['id', 'name']),
            'staff' => User::assignable()->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'exists:users,id'],
            'company' => ['nullable', 'string', 'max:255'],
            'sales_person_id' => ['nullable', 'exists:users,id'],
            'project_manager_id' => ['nullable', 'exists:users,id'],
            'account_manager_id' => ['nullable', 'exists:users,id'],
            'project_type' => ['nullable', Rule::in(Project::TYPES)],
            'priority' => ['required', Rule::in(array_keys(Project::PRIORITIES))],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'currency' => ['required', 'string', 'max:8'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'expected_delivery' => ['nullable', 'date'],
            'actual_delivery' => ['nullable', 'date'],
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'description' => ['nullable', 'string', 'max:10000'],
        ]);
    }
}
