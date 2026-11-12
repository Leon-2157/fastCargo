<?php

namespace App\Modules\Shared\Controllers;

use App\Modules\Shared\Models\User;
use App\Modules\Shared\Services\TwoFactorResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class TwoFactorResetController extends Controller
{
    /**
     * Resolve the active authenticated user and guard.
     *
     * @return array{0: User, 1: string}
     */
    protected function resolveUserAndGuard(): array
    {
        foreach (['admin', 'operator', 'customer'] as $guard) {
            /** @var User|null $user */
            $user = Auth::guard($guard)->user();
            if ($user !== null) {
                return [$user, $guard];
            }
        }

        abort(403, 'Unauthorized.');
    }

    /**
     * Show channel selection form (Email / WhatsApp).
     */
    public function showResetForm(Request $request): View
    {
        [$user, $guard] = $this->resolveUserAndGuard();

        return view('shared.auth.two-factor-reset', [
            'user' => $user,
            'guard' => $guard,
            'sendRoute' => route($guard.'.2fa.reset.send', [], false),
            'challengeRoute' => route($guard.'.2fa.challenge', [], false),
        ]);
    }

    /**
     * Dispatch OTP code via selected channel.
     */
    public function sendOtp(Request $request): RedirectResponse
    {
        [$user, $guard] = $this->resolveUserAndGuard();

        $validated = $request->validate([
            'channel' => ['required', 'in:EMAIL,WHATSAPP'],
        ]);

        $result = TwoFactorResetService::sendResetToken(
            $user,
            $validated['channel'],
            $request->ip(),
            $request->userAgent()
        );

        if (! $result['success']) {
            return back()->withErrors(['channel' => $result['message']])->withInput();
        }

        $request->session()->put('2fa_reset_channel', $result['channel']);

        return redirect()->route($guard.'.2fa.reset.verify.form')
            ->with('success', $result['message']);
    }

    /**
     * Show OTP verification form.
     */
    public function showVerifyForm(Request $request): View
    {
        [$user, $guard] = $this->resolveUserAndGuard();
        $channel = $request->session()->get('2fa_reset_channel', 'EMAIL');

        return view('shared.auth.two-factor-reset-verify', [
            'user' => $user,
            'guard' => $guard,
            'channel' => $channel,
            'verifyRoute' => route($guard.'.2fa.reset.verify', [], false),
            'resendRoute' => route($guard.'.2fa.reset', [], false),
        ]);
    }

    /**
     * Verify OTP and reset 2FA, enforcing immediate reconfiguration.
     */
    public function verifyOtp(Request $request): RedirectResponse
    {
        [$user, $guard] = $this->resolveUserAndGuard();

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $result = TwoFactorResetService::verifyAndReset($user, $validated['code']);

        if (! $result['success']) {
            return back()->withErrors(['code' => $result['message']]);
        }

        $request->session()->forget('2fa_reset_channel');

        // Redirect directly to 2FA setup to enforce immediate new authenticator binding
        return redirect()->route($guard.'.2fa.setup')
            ->with('success', $result['message']);
    }
}
