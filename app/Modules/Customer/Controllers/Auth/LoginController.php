<?php

namespace App\Modules\Customer\Controllers\Auth;

use App\Modules\Shared\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showForm()
    {
        return view('customer.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'browser_timezone' => 'nullable|string|max:50',
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::guard('customer')->attempt(['email' => $credentials['email'], 'password' => $credentials['password']], $remember)) {
            return back()->withErrors(['email' => 'Invalid email or password credentials.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = Auth::guard('customer')->user();

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        // Enforce customer user type
        if (! in_array($user->user_type, ['RETAIL_CUSTOMER', 'CORPORATE_USER'], true)) {
            Auth::guard('customer')->logout();

            return back()->withErrors(['email' => 'Access denied: not a registered customer account.']);
        }

        // Check account suspension status
        if ($user->status !== 'ACTIVE') {
            Auth::guard('customer')->logout();

            return back()->withErrors(['email' => 'Your account has been suspended or locked. Contact FastCargo support.']);
        }

        // Persist auto-detected timezone
        if (! empty($credentials['browser_timezone']) && $user->timezone !== $credentials['browser_timezone']) {
            $user->update(['timezone' => $credentials['browser_timezone']]);
        }

        // Enforce 2FA check
        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('customer.2fa.setup');
        }

        $request->session()->put('two_factor_verified', false);

        return redirect()->route('customer.2fa.challenge');
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    }
}
