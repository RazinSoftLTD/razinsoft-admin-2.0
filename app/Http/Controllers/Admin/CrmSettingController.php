<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadOption;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Settings → CRM Settings: manage configurable lead taxonomies (sources, departments). */
class CrmSettingController extends Controller
{
    public function index()
    {
        return view('admin.crm-settings.index', [
            'sources' => LeadOption::ofType('source')->get(),
            'departments' => LeadOption::ofType('department')->get(),
            'products' => LeadOption::ofType('product')->get(),
        ]);
    }

    public function storeOption(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(LeadOption::TYPES))],
            'label' => ['required', 'string', 'max:60'],
        ]);

        // No duplicates within the same list (case-insensitive).
        $exists = LeadOption::where('type', $data['type'])
            ->whereRaw('LOWER(label) = ?', [mb_strtolower($data['label'])])->exists();
        if ($exists) {
            return back()->with('error', 'That option already exists.');
        }

        LeadOption::create([
            'type' => $data['type'],
            'label' => trim($data['label']),
            'sort_order' => (int) LeadOption::where('type', $data['type'])->max('sort_order') + 1,
        ]);

        return back()->with('status', LeadOption::TYPES[$data['type']].' added.');
    }

    public function destroyOption(LeadOption $option)
    {
        $option->delete();

        return back()->with('status', 'Option removed.');
    }
}
