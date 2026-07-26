<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingAddress;
use Illuminate\Http\Request;

/** The signed-in customer's saved billing addresses — listed, added, edited and defaulted from
 *  the account dashboard, and offered again at checkout so the address is asked for once. */
class BillingAddressController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['data' => $this->all($request)]);
    }

    public function store(Request $request)
    {
        $address = BillingAddress::create($this->validated($request) + ['user_id' => $request->user()->id]);

        if ($request->boolean('is_default')) {
            $address->makeDefault();
        }

        return response()->json(['data' => $this->all($request), 'id' => $address->id], 201);
    }

    public function update(Request $request, BillingAddress $billingAddress)
    {
        $this->authorizeOwner($request, $billingAddress);
        $billingAddress->update($this->validated($request));

        if ($request->boolean('is_default')) {
            $billingAddress->makeDefault();
        }

        return response()->json(['data' => $this->all($request)]);
    }

    public function destroy(Request $request, BillingAddress $billingAddress)
    {
        $this->authorizeOwner($request, $billingAddress);
        $billingAddress->delete();

        return response()->json(['data' => $this->all($request)]);
    }

    /** Mark one as the default; the rest are cleared. */
    public function setDefault(Request $request, BillingAddress $billingAddress)
    {
        $this->authorizeOwner($request, $billingAddress);
        $billingAddress->makeDefault();

        return response()->json(['data' => $this->all($request)]);
    }

    private function all(Request $request): array
    {
        $addresses = BillingAddress::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')->orderBy('id')
            ->get();

        // "Home 1"/"Home 2" once a type repeats — worked out over the whole set, so the dashboard
        // and the checkout dropdown always show the same name for the same address.
        $names = BillingAddress::displayNames($addresses);

        return $addresses
            ->map(fn (BillingAddress $a) => [
                'id' => $a->id,
                'label' => $a->label,
                'display_label' => $names[$a->id] ?? $a->labelName(),
                'full_name' => $a->full_name,
                'company' => $a->company,
                'phone' => $a->phone,
                'address' => $a->address,
                'city' => $a->city,
                'state' => $a->state,
                'zip' => $a->zip,
                'country' => $a->country,
                'is_default' => $a->is_default,
                'one_line' => $a->oneLine(),
            ])->all();
    }

    /** Address, postal code and country are what a card payment needs; city and state are not. */
    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required', \Illuminate\Validation\Rule::in(array_keys(BillingAddress::LABELS))],
            'full_name' => ['nullable', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'zip' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:120'],
        ]);
    }

    private function authorizeOwner(Request $request, BillingAddress $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 403);
    }
}
