<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrandSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Settings → Branding. The buyer makes the panel theirs without touching a file. */
class BrandController extends Controller
{
    public function index(Request $request)
    {
        $this->can($request);

        return view('admin.settings.branding', ['brand' => BrandSetting::current()]);
    }

    public function update(Request $request)
    {
        $this->can($request);

        $data = $request->validate([
            'product' => ['nullable', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:150'],
            'primary' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,webp,svg', 'max:1024'],
            'icon' => ['nullable', 'image', 'mimes:png,jpg,webp,svg', 'max:512'],
        ], [
            'primary.regex' => 'Pick a colour, or type it as a six-digit hex like #5b6cf7.',
        ]);

        $brand = BrandSetting::first() ?? new BrandSetting;

        $brand->fill([
            'product' => $data['product'] ?? null,
            'tagline' => $data['tagline'] ?? null,
            'primary' => $data['primary'] ?? null,
            // Derived from the primary, so there is only ever one colour to choose.
            'primary_hover' => null,
        ]);

        foreach (['logo', 'icon'] as $field) {
            if ($request->hasFile($field)) {
                // The old file goes only once the new one is stored, so a failed upload never
                // leaves the panel with no mark at all.
                $old = $brand->$field;
                $brand->$field = $request->file($field)->store('branding', 'public');

                if ($old) {
                    Storage::disk('public')->delete($old);
                }
            }
        }

        $brand->save();

        return back()->with('status', 'Branding saved.');
    }

    /** Put a mark back to the one the software shipped with. */
    public function resetAsset(Request $request, string $field)
    {
        $this->can($request);

        abort_unless(in_array($field, ['logo', 'icon'], true), 404);

        $brand = BrandSetting::first();

        if ($brand?->$field) {
            Storage::disk('public')->delete($brand->$field);
            $brand->forceFill([$field => null])->save();
        }

        return back()->with('status', ucfirst($field).' reset to the default.');
    }

    private function can(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403);
    }
}
