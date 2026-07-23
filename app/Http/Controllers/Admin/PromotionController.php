<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** The site-wide promo banner shown above the nav on the public website. */
class PromotionController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('promotion.view'), 403);

        $promotions = Promotion::with('creator')->latest()->get();
        $topBanners = $promotions->where('type', Promotion::TYPE_TOP_BANNER)->values();
        $popups = $promotions->where('type', Promotion::TYPE_POPUP)->values();

        return view('admin.promotions.index', compact('topBanners', 'popups'));
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->hasPermission('promotion.create'), 403);

        return view('admin.promotions.form', ['promotion' => new Promotion([
            'status' => 'draft',
            'type' => $request->query('type', Promotion::TYPE_TOP_BANNER),
            'countdown_enabled' => true,
        ])]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('promotion.create'), 403);
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        // Give a brand-new Top Banner a real default title in the DB (not just a
        // display fallback), so clearing it later is a genuine, distinguishable "hide the title".
        if (($data['type'] ?? null) === Promotion::TYPE_TOP_BANNER && empty($data['countdown_label'])) {
            $data['countdown_label'] = Promotion::DEFAULT_COUNTDOWN_LABEL;
        }
        $this->handleImage($request, $data);
        $this->applyStatus($request, $data);

        Promotion::create($data);

        return redirect()->route('admin.promotions.index')->with('status', 'Promotion created.');
    }

    public function edit(Request $request, Promotion $promotion)
    {
        abort_unless($request->user()->hasPermission('promotion.edit'), 403);

        return view('admin.promotions.form', compact('promotion'));
    }

    public function update(Request $request, Promotion $promotion)
    {
        abort_unless($request->user()->hasPermission('promotion.edit'), 403);
        $data = $this->validated($request, $promotion);
        $this->handleImage($request, $data, $promotion);
        $this->applyStatus($request, $data, $promotion);

        $promotion->update($data);

        return redirect()->route('admin.promotions.index')->with('status', 'Promotion updated.');
    }

    /** Quick publish/unpublish toggle from the list (needs the `publish` permission). */
    public function togglePublish(Request $request, Promotion $promotion)
    {
        abort_unless($request->user()->hasPermission('promotion.publish'), 403);

        if ($promotion->isPublished()) {
            $promotion->update(['status' => 'draft']);
            $msg = 'Promotion moved back to draft.';
        } else {
            $promotion->update(['status' => 'published', 'published_at' => $promotion->published_at ?? now()]);
            $msg = 'Promotion published — it is now live on the website (within its schedule).';
        }

        return back()->with('status', $msg);
    }

    public function destroy(Request $request, Promotion $promotion)
    {
        abort_unless($request->user()->hasPermission('promotion.delete'), 403);
        if ($promotion->image) {
            Storage::disk('public')->delete($promotion->image);
        }
        $promotion->delete();

        return back()->with('status', 'Promotion removed.');
    }

    private function validated(Request $request, ?Promotion $promotion = null): array
    {
        $type = $request->input('type', $promotion?->type ?? Promotion::TYPE_TOP_BANNER);
        $specKey = $type === Promotion::TYPE_POPUP ? 'popup_banner' : 'banner';

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Promotion::TYPES))],
            'image' => [$promotion?->exists ? 'nullable' : 'required', 'image', 'max:4096', \App\Support\ImageSpecs::rule($specKey)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'countdown_label' => ['nullable', 'string', 'max:60'],
            'countdown_title_color' => ['nullable', 'string', 'max:7', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'countdown_value_color' => ['nullable', 'string', 'max:7', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
        ], [
            'image.dimensions' => \App\Support\ImageSpecs::message($specKey, 'image'),
        ]);

        // Countdown is a Top Banner–only feature; irrelevant (and unused) for a Popup.
        if ($type === Promotion::TYPE_TOP_BANNER) {
            $data['countdown_enabled'] = $request->boolean('countdown_enabled');
        }

        return $data;
    }

    /** Store the uploaded banner/popup image (if any), deleting the old one on replace. */
    private function handleImage(Request $request, array &$data, ?Promotion $promotion = null): void
    {
        $type = $data['type'] ?? Promotion::TYPE_TOP_BANNER;
        $folder = $type === Promotion::TYPE_POPUP ? 'promotions/popups' : 'promotions/banners';

        if ($request->hasFile('image')) {
            if ($promotion?->image) {
                Storage::disk('public')->delete($promotion->image);
            }
            $data['image'] = $request->file('image')->store($folder, 'public');
        } else {
            unset($data['image']);
        }
    }

    /**
     * Resolve the requested status. Publishing requires the `publish` permission —
     * a user without it can only ever save a draft, which is the verify-before-live gate.
     */
    private function applyStatus(Request $request, array &$data, ?Promotion $promotion = null): void
    {
        $wantPublish = $request->input('status') === 'published'
            && $request->user()->hasPermission('promotion.publish');

        $data['status'] = $wantPublish ? 'published' : 'draft';
        if ($data['status'] === 'published') {
            $data['published_at'] = $promotion?->published_at ?? now();
        }
    }
}
