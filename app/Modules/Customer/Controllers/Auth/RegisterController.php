<?php

namespace App\Modules\Customer\Controllers\Auth;

use App\Modules\Shared\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function showForm()
    {
        return view('customer.auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'required|string|max:50',
            'password' => 'required|string|min:8|confirmed',
            'browser_timezone' => 'nullable|string|max:50',
        ]);

        $user = User::create([
            'id' => Str::uuid()->toString(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'user_type' => 'RETAIL_CUSTOMER',
            'status' => 'ACTIVE',
            'kyc_aml_status' => 'UNVERIFIED',
            'timezone' => $validated['browser_timezone'] ?? 'Asia/Jakarta',
        ]);

        $user->sendEmailVerificationNotification();
        Auth::guard('customer')->login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice')
            ->with('status', 'Account created. Check your email to verify it before signing in.');
    }

    public function verifyEmail(Request $request, string $id, string $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $hash)) {
            abort(403, 'Invalid verification link.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->route('customer.login')->with('status', 'Email verified. Please sign in.');
    }
}
