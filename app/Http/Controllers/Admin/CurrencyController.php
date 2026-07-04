<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientInvoice;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrencyController extends Controller
{
    public function index()
    {
        return view('admin.currencies.index', [
            'currencies' => Currency::orderBy('sort_order')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:8', 'alpha', Rule::unique('currencies', 'code')],
            'symbol' => ['required', 'string', 'max:12'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['sort_order'] = (int) Currency::max('sort_order') + 1;
        $data['is_active'] = true;

        Currency::create($data);

        return back()->with('status', "Currency {$data['code']} added.");
    }

    public function update(Request $request, Currency $currency)
    {
        $data = $request->validate([
            'symbol' => ['required', 'string', 'max:12'],
            'name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $currency->update($data);

        return back()->with('status', "Currency {$currency->code} updated.");
    }

    public function destroy(Currency $currency)
    {
        // Don't remove a currency that invoices already use — it would break their display.
        if (ClientInvoice::where('currency', $currency->code)->exists()) {
            return back()->with('error', "{$currency->code} is used by existing invoices; deactivate it instead.");
        }

        $currency->delete();

        return back()->with('status', "Currency {$currency->code} deleted.");
    }
}
