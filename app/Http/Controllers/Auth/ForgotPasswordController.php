<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetLinkMail;
use App\Models\User;
use App\Services\SystemNotificationService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;

class ForgotPasswordController extends Controller
{
    public function __construct(private SystemNotificationService $notifications) {}

    public function showLinkRequestForm()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->first();

        if (! $user) {
            return back()->withErrors([
                'email' => __(Password::INVALID_USER),
            ]);
        }

        try {
            $token = Password::broker()->createToken($user);
            $resetUrl = route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]);
        } catch (\Throwable $e) {
            Log::error('Password reset token generation failed.', [
                'email' => $validated['email'],
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'email' => 'Unable to generate a password reset link right now. Please try again later.',
            ]);
        }

        $sent = $this->notifications->sendUserEmail(
            $user,
            new PasswordResetLinkMail($user, $resetUrl),
            [
                'defer' => false,
                'respect_preferences' => false,
                'context' => [
                    'communication' => 'password_reset_link',
                    'user_id' => $user->id,
                ],
            ]
        );

        if (! $sent) {
            return back()->withErrors([
                'email' => 'Unable to send the password reset link right now. Please try again later.',
            ]);
        }

        return back()->with('success', __(Password::RESET_LINK_SENT));
    }

    public function showResetForm(Request $request, string $token)
    {
        $email = (string) $request->query('email', '');

        if ($token === '' || $email === '') {
            return redirect('/Login')->withErrors([
                'message' => 'The password reset link is invalid.',
            ]);
        }

        return Inertia::render('Auth/ResetPassword', [
            'email' => $email,
            'token' => $token,
        ]);
    }

    public function reset(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::broker()->reset(
            [
                'token' => $validated['token'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'must_change_password' => false,
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors([
                'password' => __($status),
            ]);
        }

        return redirect('/Login')->with('success', 'Your password has been reset successfully. You may now sign in.');
    }
}
