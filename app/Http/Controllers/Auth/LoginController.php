<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        // Validate
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Attempt login
        if (!Auth::attempt($credentials)) {
            return back()->withErrors([
                'message' => 'Invalid email or password.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user->account_state === 'deactivated') {
            Auth::logout();
            return redirect('/deactivated');
        }

        if ($user->requiresStudentApproval() && $user->approval_status !== 'approved') {
            Auth::logout();
            return redirect($user->approval_status === 'rejected' ? '/rejected' : '/pending-approval');
        }

        if ($user->must_change_password) {
            return redirect('/account/account-settings?force=1')
                ->with('error', 'Please update your password before accessing the rest of the system.');
        }

        $intendedUrl = $request->session()->pull('url.intended');
        $intendedPath = is_string($intendedUrl) ? (parse_url($intendedUrl, PHP_URL_PATH) ?: '') : '';
        if ($intendedUrl && Str::startsWith($intendedPath, '/join-team')) {
            return redirect()->to($intendedUrl)->with('login_success', 'Sign-in successful.');
        }

        // Redirect by role
        if ($user->role === 'admin') {
            return redirect('/AdminDashboard')->with('login_success', 'Sign-in successful.');
        } elseif ($user->role === 'coach') {
            return redirect('/coach/dashboard')->with('login_success', 'Sign-in successful.');
        } elseif (in_array($user->role, ['student-athlete', 'student'], true)) {
            return redirect('/StudentAthleteDashboard')->with('login_success', 'Sign-in successful.');
        } else {
            Auth::logout();
            return redirect('/Login')->withErrors([
                'message' => 'Your account role is not supported.',
            ]);
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
