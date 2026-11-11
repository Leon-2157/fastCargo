<?php

namespace App\Modules\Operator\Controllers\Auth;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function showSetup(Request $request)
    {
        $user = Auth::guard('operator')->user();
        $google2fa = new Google2FA;
        $secretKey = $google2fa->generateSecretKey();

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'FastCargo-Operator',
            $user->email,
            $secretKey
        );

        $renderer = new ImageRenderer(new RendererStyle(180), new SvgImageBackEnd);
        $writer = new Writer($renderer);
        $qrCodeSvg = $writer->writeString($qrCodeUrl);

        return view('customer.auth.two-factor-setup', [
            'secretKey' => $secretKey,
            'qrCodeSvg' => $qrCodeSvg,
            'enableRoute' => route('operator.2fa.enable'),
        ]);
    }

    public function enable(Request $request)
    {
        $request->validate([
            'secret' => 'required|string',
            'code' => 'required|digits:6',
        ]);

        $google2fa = new Google2FA;
        $valid = $google2fa->verifyKey($request->secret, $request->code);

        if (! $valid) {
            return back()->withErrors(['code' => 'Invalid operator OTP token. Check your clock.']);
        }

        $user = Auth::guard('operator')->user();
        $user->update([
            'two_factor_secret' => encrypt($request->secret),
            'two_factor_enabled_at' => now(),
        ]);

        $request->session()->put('two_factor_verified', true);

        return redirect()->route('operator.dashboard')
            ->with('success', 'Operator 2-Factor Authentication activated.');
    }

    public function showChallenge()
    {
        return view('operator.auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6',
        ]);

        $user = Auth::guard('operator')->user();

        if (! $user->two_factor_secret) {
            return redirect()->route('operator.2fa.setup');
        }

        $google2fa = new Google2FA;
        $decryptedSecret = decrypt($user->two_factor_secret);
        $valid = $google2fa->verifyKey($decryptedSecret, $request->code);

        if (! $valid) {
            return back()->withErrors(['code' => 'Invalid OTP verification code.']);
        }

        $request->session()->put('two_factor_verified', true);

        return redirect()->route('operator.dashboard');
    }
}
