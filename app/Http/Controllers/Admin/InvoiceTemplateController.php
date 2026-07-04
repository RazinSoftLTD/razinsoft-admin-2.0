<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientInvoice;
use App\Models\InvoiceTemplate;
use Illuminate\Http\Request;

class InvoiceTemplateController extends Controller
{
    public function index()
    {
        return view('admin.invoice-templates.index', [
            'templates' => InvoiceTemplate::latest('id')->paginate(15),
        ]);
    }

    public function create()
    {
        return view('admin.invoice-templates.form', [
            'template' => new InvoiceTemplate(['currency' => 'USD']),
            'items' => [['description' => '', 'qty' => 1, 'unit_price' => 0, 'discount_percent' => 0, 'tax_percent' => 0]],
        ]);
    }

    public function store(Request $request)
    {
        InvoiceTemplate::create($this->validated($request));

        return redirect()->route('admin.invoice-templates.index')->with('status', 'Template saved.');
    }

    public function edit(InvoiceTemplate $invoiceTemplate)
    {
        return view('admin.invoice-templates.form', [
            'template' => $invoiceTemplate,
            'items' => $invoiceTemplate->items,
        ]);
    }

    public function update(Request $request, InvoiceTemplate $invoiceTemplate)
    {
        $invoiceTemplate->update($this->validated($request));

        return redirect()->route('admin.invoice-templates.index')->with('status', 'Template updated.');
    }

    public function destroy(InvoiceTemplate $invoiceTemplate)
    {
        $invoiceTemplate->delete();

        return back()->with('status', 'Template deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:8'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}
