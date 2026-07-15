<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvoiceSetting;
use App\Models\InvoiceTax;
use App\Models\InvoiceUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class InvoiceConfigController extends Controller
{
    public function index()
    {
        return view('admin.invoice-config.index', [
            'settings' => InvoiceSetting::current(),
            'units' => InvoiceUnit::orderBy('sort_order')->orderBy('name')->get(),
            'taxes' => InvoiceTax::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    // ---- Branding (logo + name) ----
    public function updateBranding(Request $request)
    {
        $data = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:120'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $settings = InvoiceSetting::current();
        $settings->brand_name = $data['brand_name'] ?? $settings->brand_name;

        if ($request->hasFile('logo')) {
            if ($settings->logo) {
                Storage::disk('public')->delete($settings->logo);
            }
            $settings->logo = $request->file('logo')->store('invoices/branding', 'public');
        }
        $settings->save();

        return back()->with('status', 'Invoice branding updated.');
    }

    // ---- Units ----
    public function storeUnit(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60', Rule::unique('invoice_units', 'name')]]);
        InvoiceUnit::create([
            'name' => $data['name'],
            'sort_order' => (int) InvoiceUnit::max('sort_order') + 1,
            'is_default' => ! InvoiceUnit::exists(),
        ]);

        return back()->with('status', "Unit “{$data['name']}” added.");
    }

    public function updateUnit(Request $request, InvoiceUnit $unit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('invoice_units', 'name')->ignore($unit)],
            'is_default' => ['nullable', 'boolean'],
        ]);
        if ($request->boolean('is_default')) {
            InvoiceUnit::where('id', '!=', $unit->id)->update(['is_default' => false]);
        }
        $unit->update(['name' => $data['name'], 'is_default' => $request->boolean('is_default')]);

        return back()->with('status', 'Unit updated.');
    }

    public function destroyUnit(InvoiceUnit $unit)
    {
        $unit->delete();

        return back()->with('status', 'Unit deleted.');
    }

    // ---- Taxes / charges ----
    public function storeTax(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        InvoiceTax::create([
            'name' => $data['name'],
            'rate' => $data['rate'],
            'sort_order' => (int) InvoiceTax::max('sort_order') + 1,
        ]);

        return back()->with('status', "Tax “{$data['name']}” added.");
    }

    public function updateTax(Request $request, InvoiceTax $tax)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        $tax->update($data);

        return back()->with('status', 'Tax updated.');
    }

    public function destroyTax(InvoiceTax $tax)
    {
        $tax->delete();

        return back()->with('status', 'Tax deleted.');
    }
}
