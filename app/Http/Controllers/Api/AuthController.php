<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:4'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'customer',
        ]);

        return $this->respondWithToken($user, 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['These credentials do not match our records.']]);
        }

        // Blocked accounts cannot sign in at all. (Inactive accounts may sign in but are limited to support.)
        if (! $user->canLogin()) {
            throw ValidationException::withMessages(['email' => ['This account is blocked. Please contact support.']]);
        }

        return $this->respondWithToken($user);
    }

    /** Send a password-reset link to the given email (used for booked clients who never set a password). */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        // Only customers use this flow; never leak which emails exist.
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'If that email is registered, a password reset link is on its way.']);
    }

    /** Set a new password from a reset token. */
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
            // A client who resets their password is a real, active account.
            if ($user->status === 'inactive' || $user->status === null) {
                $user->forceFill(['status' => 'active'])->save();
            }
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => 'Password updated. You can now sign in.']);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function respondWithToken(User $user, int $status = 200)
    {
        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ], $status);
    }
}
