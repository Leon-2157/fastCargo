<?php

namespace App\Modules\Admin\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showForm()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $remember = $request->boolean('remember');

        // Allow login by email or username
        $fieldType = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::guard('admin')->attempt([$fieldType => $credentials['login'], 'password' => $credentials['password']], $remember)) {
            return back()->withErrors(['login' => 'Invalid administrative credentials.'])->onlyInput('login');
        }

        $request->session()->regenerate();

        $user = Auth::guard('admin')->user();

        if ($user->user_type !== 'ADMIN_USER' || $user->status !== 'ACTIVE') {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['login' => 'Access denied: administrative account is inactive or unauthorized.']);
        }

        $request->session()->put('admin_two_factor_verified', false);

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('admin.2fa.setup');
        }

        return redirect()->route('admin.2fa.challenge');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
