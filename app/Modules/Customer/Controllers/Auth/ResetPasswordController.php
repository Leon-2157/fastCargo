<?php

namespace App\Modules\Customer\Controllers\Auth;

use App\Modules\Shared\Models\User;
use App\Modules\Shared\Services\PasswordResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ResetPasswordController extends Controller
{
    /**
     * Display the password reset form for customer credentials.
     */
    public function showResetForm(Request $request, string $token): View
    {
        return view('customer.auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /**
     * Reset the given customer user's password.
     */
    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        /** @var User|null $user */
        $user = User::where('email', $validated['email'])->first();

        // Enforce customer portal role isolation
        if (! $user || ! in_array($user->user_type, ['RETAIL_CUSTOMER', 'CORPORATE_USER'], true)) {
            return back()->withErrors(['email' => 'Unable to locate an authorized customer account with this email address.'])->withInput();
        }

        $result = PasswordResetService::resetPassword(
            $validated['email'],
            $validated['token'],
            $validated['password'],
            $request->ip()
        );

        if (! $result['success']) {
            return back()->withErrors(['email' => $result['message']])->withInput();
        }

        return redirect()->route('customer.login')
            ->with('status', $result['message'].' Two-Factor Authentication (2FA) will be verified upon sign in.');
    }
}
