<?php

namespace App\Modules\Customer\Controllers\Auth;

use App\Modules\Shared\Models\User;
use App\Modules\Shared\Services\PasswordResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ForgotPasswordController extends Controller
{
    /**
     * Display the form to request a password reset link.
     */
    public function showLinkRequestForm(): View
    {
        return view('customer.auth.forgot-password');
    }

    /**
     * Send a reset link to the given customer user.
     */
    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        /** @var User|null $user */
        $user = User::where('email', $validated['email'])->first();

        // Enforce customer portal role isolation
        if (! $user || ! in_array($user->user_type, ['RETAIL_CUSTOMER', 'CORPORATE_USER'], true)) {
            return back()->with('status', 'If your account is registered with FastCargo Customer Portal, a password reset link has been dispatched to your email.');
        }

        $result = PasswordResetService::sendResetLink(
            $user,
            'customer',
            $request->ip(),
            $request->userAgent()
        );

        if (! $result['success']) {
            return back()->withErrors(['email' => $result['message']])->withInput();
        }

        if (app()->environment('local') && ! empty($result['reset_url'])) {
            session()->flash('dev_reset_url', $result['reset_url']);
        }

        return back()->with('status', $result['message']);
    }
}
