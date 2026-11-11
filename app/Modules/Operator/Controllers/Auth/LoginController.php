<?php

namespace App\Modules\Operator\Controllers\Auth;

use App\Modules\Shared\Models\AdmOperatorInvitation;
use App\Modules\Shared\Models\OpsOperatorProfile;
use App\Modules\Shared\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class LoginController extends Controller
{
    public function showForm()
    {
        return view('operator.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::guard('operator')->attempt(['email' => $credentials['email'], 'password' => $credentials['password']], $remember)) {
            return back()->withErrors(['email' => 'Invalid operator credentials.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = Auth::guard('operator')->user();

        // Enforce operator role
        if ($user->user_type !== 'OPERATOR_USER') {
            Auth::guard('operator')->logout();

            return back()->withErrors(['email' => 'Access denied: not an authorized operator account.']);
        }

        if ($user->status !== 'ACTIVE') {
            Auth::guard('operator')->logout();

            return back()->withErrors(['email' => 'Operator terminal suspended. Contact Central Dispatch.']);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('operator.2fa.setup');
        }

        $request->session()->put('two_factor_verified', false);

        return redirect()->route('operator.2fa.challenge');
    }

    /**
     * Resolve an active, non-expired operator invitation by token or abort with 403.
     */
    private function findValidInvitation(string $token): AdmOperatorInvitation
    {
        $invitation = AdmOperatorInvitation::where('invitation_token', $token)
            ->where('status', AdmOperatorInvitation::STATUS_INVITATION_SENT)
            ->first();

        if (! $invitation || $invitation->isExpired()) {
            abort(403, 'Invalid or expired operator invitation token.');
        }

        return $invitation;
    }

    public function showRegister(string $token)
    {
        $invitation = $this->findValidInvitation($token);

        return view('operator.auth.register', compact('invitation'));
    }

    public function register(Request $request, string $token)
    {
        $invitation = $this->findValidInvitation($token);

        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
            'nik' => 'required|string|max:30',
            'ktp_file' => 'required|file|mimes:jpeg,png,pdf|max:5120',
            'bank_name' => 'required|string|max:100',
            'bank_account_number' => 'required|string|max:50',
            'bank_account_name' => 'required|string|max:100',
        ]);

        if (User::where('email', $invitation->email)->exists()) {
            return back()->withErrors([
                'password' => 'An account with this invited email already exists. Contact Central Dispatch.',
            ]);
        }

        // Upload KTP file
        $ktpPath = $request->file('ktp_file')->store('ktp_documents', 'local');

        $hubId = $invitation->assigned_hub_id ?? 'IDJKT';
        $roleTier = $invitation->assigned_role_tier ?: 'OPERATOR_DOMESTIC';
        $employeeId = 'OPS-'.substr($hubId, 0, 3).'-'.strtoupper(Str::random(4));

        $user = DB::transaction(function () use ($invitation, $validated, $ktpPath, $hubId, $roleTier, $employeeId): User {
            $user = User::create([
                'name' => $invitation->name,
                'email' => $invitation->email,
                'phone' => $invitation->phone,
                'password' => Hash::make($validated['password']),
                'user_type' => 'OPERATOR_USER',
                'status' => 'ACTIVE',
                'nik' => $validated['nik'],
                'ktp_file_url' => $ktpPath,
                'kyc_aml_status' => 'VERIFIED',
                'email_verified_at' => now(),
                'timezone' => 'Asia/Jakarta',
            ]);

            $role = Role::where('name', strtolower($roleTier))
                ->where('guard_name', 'operator')
                ->first();

            if ($role) {
                $user->assignRole($role);
            }

            OpsOperatorProfile::create([
                'user_id' => $user->id,
                'employee_id' => $employeeId,
                'assigned_hub_id' => $hubId,
                'role_tier' => $roleTier,
                'is_on_duty' => true,
                'kpi_rating' => 5.00,
            ]);

            $invitation->update([
                'status' => AdmOperatorInvitation::STATUS_REGISTERED,
            ]);

            return $user;
        });

        Auth::guard('operator')->login($user);

        return redirect()->route('operator.2fa.setup')
            ->with('status', 'Operator account registered. Mandatory 2-Factor setup required.');
    }

    public function logout(Request $request)
    {
        Auth::guard('operator')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('operator.login');
    }
}
