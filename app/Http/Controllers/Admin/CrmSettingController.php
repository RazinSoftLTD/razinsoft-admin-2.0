<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientLabel;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadOption;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Settings → CRM Settings: manage configurable lead & deal taxonomies. */
class CrmSettingController extends Controller
{
    public function index()
    {
        return view('admin.crm-settings.index', [
            'sources' => LeadOption::ofType('source')->get(),
            'departments' => LeadOption::ofType('department')->get(),
            'products' => LeadOption::ofType('product')->get(),
            'stages' => LeadOption::ofType('deal_stage')->get(),
            'clientLabels' => ClientLabel::ordered(),
            'labelColors' => array_keys(ClientLabel::COLORS),
            'productCategories' => ProductCategory::tree(),
        ]);
    }

    // ===== Product categories / sub-categories (shared by Leads, Deals and Clients) =====

    /**
     * Answer the way the caller asked.
     *
     * The catalogue tab talks over fetch so adding ten sub-categories is ten keystrokes and no
     * page reloads — a plain redirect there threw the whole page away, landing back on the first
     * tab with the cursor gone. A normal form post still gets its redirect.
     */
    private function categoryReply(Request $request, ?string $error, string $ok)
    {
        if ($request->expectsJson()) {
            return $error
                ? response()->json(['message' => $error], 422)
                : response()->json(['message' => $ok, 'categories' => ProductCategory::tree()
                    ->map(fn ($c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'children' => $c->children->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
                    ])->values()]);
        }

        return $error ? back()->with('error', $error) : back()->with('status', $ok);
    }

    public function storeProductCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'parent_id' => ['nullable', 'exists:product_categories,id'],
        ]);

        // A sub-category may only hang off a top-level category — no deeper nesting.
        $parent = ! empty($data['parent_id']) ? ProductCategory::find($data['parent_id']) : null;
        if ($parent && $parent->parent_id) {
            return $this->categoryReply($request, 'Sub-categories cannot be nested further.', '');
        }

        if ($this->duplicateCategory($data['name'], $parent?->id)) {
            return $this->categoryReply($request, 'That name already exists here.', '');
        }

        ProductCategory::create([
            'parent_id' => $parent?->id,
            'name' => trim($data['name']),
            'sort_order' => (int) ProductCategory::where('parent_id', $parent?->id)->max('sort_order') + 1,
        ]);

        return $this->categoryReply($request, null, $parent ? 'Sub-category added.' : 'Product category added.');
    }

    public function updateProductCategory(Request $request, ProductCategory $productCategory)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $name = trim($data['name']);

        if ($this->duplicateCategory($name, $productCategory->parent_id, $productCategory->id)) {
            return $this->categoryReply($request, 'Another entry here already has that name.', '');
        }

        // Records store the name, so a rename has to follow through everywhere it is used.
        if ($productCategory->name !== $name) {
            $column = $productCategory->parent_id ? 'sub' : 'cat';
            $this->renameEverywhere($productCategory->name, $name, $column);
        }

        $productCategory->update(['name' => $name]);

        return $this->categoryReply($request, null, 'Updated.');
    }

    public function destroyProductCategory(Request $request, ProductCategory $productCategory)
    {
        if ($productCategory->children()->exists()) {
            return $this->categoryReply($request, 'Remove its sub-categories first.', '');
        }

        $productCategory->delete();

        return $this->categoryReply($request, null, 'Removed. Records already using it keep the old value.');
    }

    /** Keep leads / deals / clients pointing at a renamed category or sub-category. */
    private function renameEverywhere(string $old, string $new, string $which): void
    {
        $map = $which === 'cat'
            ? [Lead::class => 'product_category', Deal::class => 'product_category', User::class => 'client_category']
            : [Lead::class => 'product_sub_category', Deal::class => 'product_sub_category', User::class => 'client_sub_category'];

        foreach ($map as $model => $column) {
            $model::where($column, $old)->update([$column => $new]);
        }
    }

    private function duplicateCategory(string $name, ?int $parentId, ?int $ignoreId = null): bool
    {
        return ProductCategory::where('parent_id', $parentId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    // ===== Client loyalty/priority labels =====
    public function storeClientLabel(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique('client_labels', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', Rule::in(array_keys(ClientLabel::COLORS))],
        ]);
        ClientLabel::create([
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? 'gray',
            'sort_order' => (int) ClientLabel::max('sort_order') + 1,
        ]);

        return back()->with('status', "Client label “{$data['name']}” added.");
    }

    public function updateClientLabel(Request $request, ClientLabel $clientLabel)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique('client_labels', 'name')->ignore($clientLabel)],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', Rule::in(array_keys(ClientLabel::COLORS))],
        ]);
        // Keep clients tagged with the old name in sync when it's renamed.
        if ($clientLabel->name !== $data['name']) {
            User::where('client_label', $clientLabel->name)->update(['client_label' => $data['name']]);
        }
        $clientLabel->update(['name' => trim($data['name']), 'description' => $data['description'] ?? null, 'color' => $data['color'] ?? $clientLabel->color]);

        return back()->with('status', 'Client label updated.');
    }

    public function destroyClientLabel(ClientLabel $clientLabel)
    {
        $clientLabel->delete();

        return back()->with('status', 'Client label removed.');
    }

    public function storeOption(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(LeadOption::TYPES))],
            'label' => ['required', 'string', 'max:60'],
        ]);

        if ($this->duplicate($data['type'], $data['label'])) {
            return back()->with('error', 'That option already exists.');
        }

        LeadOption::create([
            'type' => $data['type'],
            'label' => trim($data['label']),
            'sort_order' => (int) LeadOption::where('type', $data['type'])->max('sort_order') + 1,
        ]);

        return back()->with('status', LeadOption::TYPES[$data['type']].' added.');
    }

    public function updateOption(Request $request, LeadOption $option)
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:60']]);
        $label = trim($data['label']);

        if ($this->duplicate($option->type, $label, $option->id)) {
            return back()->with('error', 'Another option with that name already exists.');
        }

        // Renaming a deal stage changes its slug — move existing deals to the new key.
        if ($option->type === 'deal_stage') {
            $oldSlug = Str::slug($option->label);
            $newSlug = Str::slug($label);
            if ($oldSlug !== $newSlug) {
                Deal::where('stage', $oldSlug)->update(['stage' => $newSlug]);
            }
        }

        $option->update(['label' => $label]);

        return back()->with('status', 'Updated.');
    }

    public function destroyOption(LeadOption $option)
    {
        if ($option->type === 'deal_stage') {
            if (Deal::where('stage', Str::slug($option->label))->exists()) {
                return back()->with('error', 'This stage still has deals — move them to another stage first.');
            }
            if (LeadOption::ofType('deal_stage')->count() <= 1) {
                return back()->with('error', 'At least one deal stage is required.');
            }
        }

        $option->delete();

        return back()->with('status', 'Option removed.');
    }

    /** True when another option of the same type already has this label (case-insensitive). */
    private function duplicate(string $type, string $label, ?int $ignoreId = null): bool
    {
        return LeadOption::where('type', $type)
            ->whereRaw('LOWER(label) = ?', [mb_strtolower(trim($label))])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}
