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

            // Basic information
            'company_name' => ['nullable', 'string', 'max:120'],
            'support_email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website_url' => ['nullable', 'url', 'max:190'],
            'address' => ['nullable', 'string', 'max:300'],

            // Website header
            'header_cta_label' => ['nullable', 'string', 'max:40'],
            'header_cta_url' => ['nullable', 'string', 'max:190'],

            // Website footer
            'footer_about' => ['nullable', 'string', 'max:400'],
            'footer_note' => ['nullable', 'string', 'max:200'],

            // Login screens
            'login_heading' => ['nullable', 'string', 'max:120'],
            'login_subheading' => ['nullable', 'string', 'max:250'],

            // Social. Validated per network so one bad paste names itself instead of failing the
            // whole form with "the social field is invalid".
            'social' => ['nullable', 'array'],
            'social.*' => ['nullable', 'url', 'max:190'],
        ], [
            'primary.regex' => 'Pick a colour, or type it as a six-digit hex like #5b6cf7.',
            'social.*.url' => 'That does not look like a full link — include https://',
        ]);

        $brand = BrandSetting::first() ?? new BrandSetting;

        $brand->fill([
            'product' => $data['product'] ?? null,
            'tagline' => $data['tagline'] ?? null,
            'primary' => $data['primary'] ?? null,
            // Derived from the primary, so there is only ever one colour to choose.
            'primary_hover' => null,

            'company_name' => $data['company_name'] ?? null,
            'support_email' => $data['support_email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'address' => $data['address'] ?? null,
            'header_cta_label' => $data['header_cta_label'] ?? null,
            'header_cta_url' => $data['header_cta_url'] ?? null,
            'footer_about' => $data['footer_about'] ?? null,
            'footer_note' => $data['footer_note'] ?? null,
            'login_heading' => $data['login_heading'] ?? null,
            'login_subheading' => $data['login_subheading'] ?? null,
            // Blank rows dropped, so an emptied field means "not on that network" rather than a
            // dead icon in the footer.
            'social' => array_filter($data['social'] ?? []) ?: null,
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
