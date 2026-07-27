<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Email\EmailDispatcher;
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

        try {
            [$first, $last] = array_pad(explode(' ', trim($user->name), 2), 2, null);

            \App\Services\Meta\ConversionsApi::make()->send('CompleteRegistration', 'signup-'.$user->id, [
                'content_name' => 'Customer account',
                'source_url' => rtrim((string) config('brand.website'), '/').'/register',
            ], [
                'email' => $user->email,
                'phone' => $user->phone,
                'first_name' => $first,
                'last_name' => $last,
                'id' => $user->id,
            ], $request);
        } catch (\Throwable $e) {
            // Signing up must not depend on a tracking call.
            report($e);
        }

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

        $this->welcomeOnce($user);

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ], $status);
    }

    /**
     * Send the welcome email the first time a customer gets into their account.
     *
     * Here rather than in register(), because customers also arrive through accounts an admin or
     * an import created for them — those people have no registration to hang this off, and their
     * first sign-in is the first time they see the portal.
     *
     * Sent once and only once: welcomed_at is stamped when the mail is accepted. It is not stamped
     * when the dispatcher refuses, so turning the notification back on later still reaches
     * everyone who has not had it. Whether it sends at all is the admin's call — the
     * account.welcome rule and the template's own Active switch both gate it.
     */
    private function welcomeOnce(User $user): void
    {
        if ($user->role !== User::ROLE_CUSTOMER || $user->welcomed_at || ! $user->email) {
            return;
        }

        try {
            $log = app(EmailDispatcher::class)->sendTemplate('welcome_client', $user->email, [
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'registration_date' => $user->created_at?->format('j M Y, g:i A'),
            ], [
                'event' => 'account.welcome',
                'module' => 'account',
                'related' => $user,
                'user_id' => $user->id,
            ]);

            if ($log) {
                $user->forceFill(['welcomed_at' => now()])->saveQuietly();
            }
        } catch (\Throwable $e) {
            // Signing in must not depend on the mail system being reachable.
            report($e);
        }
    }
}
