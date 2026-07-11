<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Self-service profile for the signed-in panel user (their own info only). */
class MyProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('admin.my-profile', ['me' => $request->user()->loadMissing('designation', 'department', 'reportsTo')]);
    }

    public function update(Request $request)
    {
        $me = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($me)],
            'phone' => ['nullable', 'string', 'max:40'],
            'dial_code' => ['nullable', 'string', 'max:8'],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'about' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if ($request->hasFile('photo')) {
            if ($me->photo) {
                Storage::disk('public')->delete($me->photo);
            }
            $file = $request->file('photo');
            $data['photo'] = $file->storeAs('staff', $file->getClientOriginalName(), 'public');
        } else {
            unset($data['photo']);
        }

        if (! empty($data['password'])) {
            $me->password = $data['password']; // hashed by cast
        }
        unset($data['password']);

        $me->fill($data)->save();

        return back()->with('status', 'Profile updated.');
    }
}
