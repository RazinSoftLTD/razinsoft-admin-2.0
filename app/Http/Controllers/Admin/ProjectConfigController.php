<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectCategory;
use App\Models\ProjectColumn;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Settings › Project Config — manage project categories and the default board columns. */
class ProjectConfigController extends Controller
{
    public function index()
    {
        return view('admin.settings.project-config', [
            'categories' => ProjectCategory::orderBy('position')->orderBy('name')->get(),
            'columns' => ProjectColumn::whereNull('project_id')->orderBy('position')->get(),
        ]);
    }

    // ---- categories ----

    public function categoryStore(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120', 'unique:project_categories,name']]);
        ProjectCategory::create(['name' => $data['name'], 'position' => (int) ProjectCategory::max('position') + 1]);

        return back()->with('status', 'Category added.');
    }

    public function categoryUpdate(Request $request, ProjectCategory $category)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120', 'unique:project_categories,name,'.$category->id]]);
        $category->update($data);

        return back()->with('status', 'Category updated.');
    }

    public function categoryDestroy(ProjectCategory $category)
    {
        $category->delete();

        return back()->with('status', 'Category removed.');
    }

    // ---- default board columns ----

    public function columnStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_done' => ['nullable', 'boolean'],
        ]);
        $key = Str::slug($data['name'], '_') ?: 'col_'.now()->timestamp;
        $base = $key;
        $i = 1;
        while (ProjectColumn::whereNull('project_id')->where('key', $key)->exists()) {
            $key = $base.'_'.$i++;
        }
        ProjectColumn::create([
            'project_id' => null, 'key' => $key, 'name' => $data['name'], 'color' => $data['color'] ?: '#94a3b8',
            'position' => (int) ProjectColumn::whereNull('project_id')->max('position') + 1,
            'is_done' => $request->boolean('is_done'), 'is_excluded' => false,
        ]);

        return back()->with('status', 'Default column added.');
    }

    public function columnUpdate(Request $request, ProjectColumn $column)
    {
        abort_unless(is_null($column->project_id), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_done' => ['nullable', 'boolean'],
        ]);
        $column->update(['name' => $data['name'], 'color' => $data['color'] ?: $column->color, 'is_done' => $request->boolean('is_done')]);

        return back()->with('status', 'Default column updated.');
    }

    public function columnDestroy(ProjectColumn $column)
    {
        abort_unless(is_null($column->project_id), 404);
        if (ProjectColumn::whereNull('project_id')->count() <= 1) {
            return back()->with('error', 'Keep at least one default column.');
        }
        $column->delete();

        return back()->with('status', 'Default column removed.');
    }
}
