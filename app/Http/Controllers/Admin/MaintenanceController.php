<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceProject;
use App\Models\MaintenanceTask;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Maintenance contracts: what we look after, what gets done on a schedule, and when it runs out.
 *
 * Everything due is worked out from the dates on read rather than generated ahead of time by a
 * scheduled command. That is deliberate — this server runs no Laravel scheduler, so a design that
 * needed one would have quietly shown nothing.
 */
class MaintenanceController extends Controller
{
    public const VIEWS = ['attention', 'all', 'active', 'expiring', 'expired', 'ended'];

    public function index(Request $request)
    {
        $view = in_array($request->query('view'), self::VIEWS, true) ? $request->query('view') : 'attention';

        $q = MaintenanceProject::with(['client:id,name', 'project:id,name', 'assignee:id,name', 'tasks.runs']);
        $request->user()->applyScope($q, 'maintenance', 'view');

        if ($search = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$search}%")));
        }

        $all = $q->orderBy('ends_on')->get();

        // Worked out in PHP, not SQL: "due" is per-task arithmetic over a recurrence rule, which a
        // where clause cannot express. The set is contracts, not tasks, so it stays small.
        $counts = [
            'attention' => $all->filter(fn ($m) => $m->needsRenewal() || $m->dueTasks()->isNotEmpty())->count(),
            'all' => $all->count(),
            'active' => $all->where('status', 'active')->count(),
            'expiring' => $all->filter(fn ($m) => $m->status !== 'ended' && ! $m->isExpired() && $m->needsRenewal())->count(),
            'expired' => $all->filter(fn ($m) => $m->status !== 'ended' && $m->isExpired())->count(),
            'ended' => $all->where('status', 'ended')->count(),
        ];

        $rows = match ($view) {
            'all' => $all,
            'active' => $all->where('status', 'active'),
            'expiring' => $all->filter(fn ($m) => $m->status !== 'ended' && ! $m->isExpired() && $m->needsRenewal()),
            'expired' => $all->filter(fn ($m) => $m->status !== 'ended' && $m->isExpired()),
            'ended' => $all->where('status', 'ended'),
            default => $all->filter(fn ($m) => $m->needsRenewal() || $m->dueTasks()->isNotEmpty()),
        };

        return view('admin.maintenance.index', [
            'rows' => $rows->values(),
            'counts' => $counts,
            'view' => $view,
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.maintenance.form', [
            'maintenance' => new MaintenanceProject([
                'starts_on' => Carbon::today(),
                'ends_on' => Carbon::today()->addYear(),
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'currency' => 'USD',
            ]),
            'clients' => $this->clients(),
            'projects' => $this->projects(),
            'staff' => $this->staff(),
        ]);
    }

    public function store(Request $request)
    {
        $m = MaintenanceProject::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('admin.maintenance.show', $m)->with('status', "Maintenance {$m->code} created.");
    }

    public function show(MaintenanceProject $maintenance)
    {
        $maintenance->load(['client:id,name,email', 'project:id,name', 'assignee:id,name', 'tasks.assignee:id,name', 'tasks.runs.completedBy:id,name', 'renewals.renewedBy:id,name']);

        return view('admin.maintenance.show', [
            'maintenance' => $maintenance,
            'due' => $maintenance->dueTasks(),
            'staff' => $this->staff(),
        ]);
    }

    public function edit(MaintenanceProject $maintenance)
    {
        return view('admin.maintenance.form', [
            'maintenance' => $maintenance,
            'clients' => $this->clients(),
            'projects' => $this->projects(),
            'staff' => $this->staff(),
        ]);
    }

    public function update(Request $request, MaintenanceProject $maintenance)
    {
        $maintenance->update($this->validated($request, $maintenance));

        return redirect()->route('admin.maintenance.show', $maintenance)->with('status', 'Maintenance updated.');
    }

    public function destroy(MaintenanceProject $maintenance)
    {
        $maintenance->delete();

        return redirect()->route('admin.maintenance.index')->with('status', 'Maintenance removed.');
    }

    // ---- The plan ----------------------------------------------------------------------------

    public function storeTask(Request $request, MaintenanceProject $maintenance)
    {
        $maintenance->tasks()->create($this->validatedTask($request) + [
            'position' => (int) $maintenance->tasks()->max('position') + 1,
        ]);

        return back()->with('status', 'Task added to the plan.');
    }

    public function updateTask(Request $request, MaintenanceProject $maintenance, MaintenanceTask $task)
    {
        abort_unless($task->maintenance_project_id === $maintenance->id, 404);
        $task->update($this->validatedTask($request));

        return back()->with('status', 'Task updated.');
    }

    public function destroyTask(MaintenanceProject $maintenance, MaintenanceTask $task)
    {
        abort_unless($task->maintenance_project_id === $maintenance->id, 404);
        $task->delete();

        return back()->with('status', 'Task removed from the plan.');
    }

    /**
     * Tick off one occurrence.
     *
     * Keyed on the task and the date it fell due, so ticking the same occurrence twice writes one
     * row — the unique index on (task, due_on) is what makes a double submit harmless.
     */
    public function completeTask(Request $request, MaintenanceProject $maintenance, MaintenanceTask $task)
    {
        abort_unless($task->maintenance_project_id === $maintenance->id, 404);

        $data = $request->validate([
            'due_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $dueOn = Carbon::parse($data['due_on'])->toDateString();

        // Looked up with whereDate, not updateOrCreate: the column is cast to a date and SQLite
        // keeps it as "2026-07-31 00:00:00", so matching on the bare "2026-07-31" finds nothing and
        // the second submit tries to insert a duplicate — which the unique index then rejects with
        // a 500 instead of quietly doing nothing.
        $run = $task->runs()->whereDate('due_on', $dueOn)->first();

        $values = ['completed_at' => now(), 'completed_by' => $request->user()->id, 'note' => $data['note'] ?? null];
        $run ? $run->update($values) : $task->runs()->create($values + ['due_on' => $dueOn]);

        return back()->with('status', 'Marked as done.');
    }

    /**
     * Renew for another term.
     *
     * The old end date is kept on the renewal row, so the history reads as a run of terms rather
     * than a contract whose dates were quietly edited.
     */
    public function renew(Request $request, MaintenanceProject $maintenance)
    {
        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $maintenance->renewals()->create($data + [
            'previous_ends_on' => $maintenance->ends_on->toDateString(),
            'renewed_by' => $request->user()->id,
        ]);

        // starts_on stays at the earliest start, and only ends_on moves. Renewing early is normal —
        // you agree the next term before the current one runs out — and writing the new term's start
        // onto the contract would put its start in the future, so nothing would fall due for the
        // rest of the term still being paid for. The per-term dates live on the renewal row.
        $maintenance->update([
            'starts_on' => $maintenance->starts_on->min(Carbon::parse($data['starts_on']))->toDateString(),
            'ends_on' => $data['ends_on'],
            'fee' => $data['fee'] ?? $maintenance->fee,
            'status' => 'active',
        ]);

        return back()->with('status', "Renewed to {$maintenance->ends_on->format('d M Y')}.");
    }

    // ---- Helpers -----------------------------------------------------------------------------

    private function validated(Request $request, ?MaintenanceProject $m = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_id' => ['required', Rule::exists('users', 'id')],
            'project_id' => ['nullable', Rule::exists('projects', 'id')],
            'status' => ['required', Rule::in(array_keys(MaintenanceProject::STATUSES))],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'billing_cycle' => ['required', Rule::in(array_keys(MaintenanceProject::CYCLES))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'scope' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function validatedTask(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'frequency' => ['required', Rule::in(array_keys(MaintenanceTask::FREQUENCIES))],
            'weekday' => ['nullable', 'integer', 'between:0,6'],
            'day_of_month' => ['nullable', 'integer', 'between:1,31'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Clear the field the chosen frequency does not use, so a task switched from weekly to
        // monthly does not keep a stale weekday that the label would then read back.
        $data['weekday'] = $data['frequency'] === 'weekly' ? ($data['weekday'] ?? 1) : null;
        $data['day_of_month'] = $data['frequency'] === 'monthly' ? ($data['day_of_month'] ?? 1) : null;
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function clients()
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    private function projects()
    {
        return Project::query()->orderBy('name')->get(['id', 'name']);
    }

    private function staff()
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }
}
