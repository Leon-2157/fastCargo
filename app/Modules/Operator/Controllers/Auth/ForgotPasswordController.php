<?php

namespace App\Modules\Operator\Controllers\Auth;

use App\Modules\Shared\Models\User;
use App\Modules\Shared\Services\PasswordResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ForgotPasswordController extends Controller
{
    /**
     * Display the form to request an operator password reset link.
     */
    public function showLinkRequestForm(): View
    {
        return view('operator.auth.forgot-password');
    }

    /**
     * Send a reset link to the given terminal operator user.
     */
    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        /** @var User|null $user */
        $user = User::where('email', $validated['email'])->first();

        // Enforce operator portal role isolation
        if (! $user || $user->user_type !== 'OPERATOR_USER') {
            return back()->with('status', 'If your account is registered as a FastCargo Terminal Operator, a password reset link has been dispatched to your email.');
        }

        $result = PasswordResetService::sendResetLink(
            $user,
            'operator',
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
