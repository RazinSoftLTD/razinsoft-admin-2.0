<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return auth()->check() && auth()->user()->isPanelUser()
            ? redirect()->route('admin.dashboard')
            : view('admin.auth.login');
    }

    public function attempt(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Staff can sign in with either their email address or their user ID
        // (employee code, e.g. RS-045) — pick the matching column automatically.
        $login = trim($data['email']);
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'employee_code';
        $credentials = [$field => $login, 'password' => $data['password']];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        if (! Auth::user()->isPanelUser()) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'This account is not authorised for the admin panel.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
