<?php

namespace App\Modules\Admin\Controllers\Auth;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function showSetup(Request $request): View|RedirectResponse
    {
        $user = Auth::guard('admin')->user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('admin.2fa.challenge');
        }

        $google2fa = new Google2FA;
        $secretKey = $request->session()->get('admin_pending_2fa_secret');

        if (! $secretKey) {
            $secretKey = $google2fa->generateSecretKey();
            $request->session()->put('admin_pending_2fa_secret', $secretKey);
        }

        $qrCodeUrl = $google2fa->getQRCodeUrl('FastCargo-Admin', $user->email, $secretKey);
        $renderer = new ImageRenderer(new RendererStyle(180), new SvgImageBackEnd);
        $qrCodeSvg = (new Writer($renderer))->writeString($qrCodeUrl);

        return view('customer.auth.two-factor-setup', [
            'secretKey' => $secretKey,
            'qrCodeSvg' => $qrCodeSvg,
            'enableRoute' => route('admin.2fa.enable', [], false),
            'pageTitle' => 'Admin Two-Factor Setup — FastCargo',
            'heading' => 'Secure Admin Access',
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $secretKey = $request->session()->pull('admin_pending_2fa_secret');

        if (! $secretKey || ! (new Google2FA)->verifyKey($secretKey, $validated['code'])) {
            return back()->withErrors(['code' => 'Invalid authenticator code. Restart setup and try again.']);
        }

        Auth::guard('admin')->user()->update([
            'two_factor_secret' => encrypt($secretKey),
            'two_factor_enabled_at' => now(),
        ]);
        $request->session()->put('admin_two_factor_verified', true);
        $request->session()->forget('admin_pending_2fa_secret');

        return redirect()->route('admin.dashboard');
    }

    public function showChallenge(): View
    {
        return view('admin.auth.two-factor-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $encryptedSecret = Auth::guard('admin')->user()->two_factor_secret;

        $encryptedSecret = Auth::guard('admin')->user()->two_factor_secret;

        if (! $encryptedSecret || ! (new Google2FA)->verifyKey(decrypt($encryptedSecret), $validated['code'])) {
            return back()->withErrors(['code' => 'Invalid authenticator code.']);
        }

        $request->session()->put('admin_two_factor_verified', true);
        $request->session()->forget('admin_pending_2fa_secret');

        return redirect()->route('admin.dashboard');
    }
}
