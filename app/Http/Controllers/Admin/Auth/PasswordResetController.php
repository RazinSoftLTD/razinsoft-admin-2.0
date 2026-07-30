<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Email\EmailDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * "Forgot password" for the admin panel.
 *
 * The token comes from Laravel's password broker — expiry, single use and hashing at rest are all
 * its job, and reimplementing them is how reset flows end up insecure. Only the delivery is ours:
 * the panel sends through its own configured SMTP account and the password_reset template, so a
 * reset email is logged, suppression-checked and styled like every other mail this system sends.
 *
 * The link has to be built here rather than left to the framework: the app points reset URLs at the
 * website (ResetPassword::createUrlUsing in AppServiceProvider), which is right for a customer and
 * wrong for staff — it would send an employee to a page that cannot sign them into the panel.
 */
class PasswordResetController extends Controller
{
    public function showRequest()
    {
        return view('admin.auth.forgot-password');
    }

    public function sendLink(Request $request, EmailDispatcher $mailer)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $data['email'])->first();

        // Staff only. A customer account has no panel to be let into, and saying so out loud would
        // turn this form into a way to test which addresses exist — so the reply never varies.
        if ($user && $user->isPanelUser()) {
            $token = Password::broker()->createToken($user);

            $mailer->sendTemplate('password_reset', $user->email, [
                'customer_name' => $user->name,
                'reset_url' => route('admin.password.reset', ['token' => $token]).'?email='.urlencode($user->email),
            ]);
        }

        return back()->with('status', 'If that address belongs to a panel account, a reset link is on its way. The link is good for one hour.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                // Rotating this signs out every "remember me" session, which is the point: if the
                // reset was prompted by someone else having the old password, that access ends here.
                'remember_token' => Str::random(60),
            ])->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        // The login screen shows the confirmation; ?reset=1 is what tells it to.
        return redirect()->route('admin.login', ['reset' => 1]);
    }
}
