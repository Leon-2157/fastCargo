<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\User;
use App\Modules\Shared\Services\AuditLogService;
use App\Modules\Shared\Services\TwoFactorResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserManagementController extends Controller
{
    /**
     * Display a listing of system network users.
     */
    public function index(Request $request): View
    {
        $query = User::with(['roles'])->latest();

        if ($request->filled('role') && $request->role !== 'ALL') {
            if ($request->role === 'CORPORATE_USER') {
                $query->whereIn('user_type', ['CORPORATE_USER', 'CORPORATE_CUSTOMER']);
            } else {
                $query->where('user_type', $request->role);
            }
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->search);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('email', 'LIKE', "%{$term}%")
                    ->orWhere('phone', 'LIKE', "%{$term}%");
            });
        }

        $users = $query->paginate(15)->withQueryString();

        return view('admin.users.index', [
            'role' => 'admin',
            'users' => $users,
        ]);
    }

    /**
     * Display a specific user identity.
     */
    public function show(string $id): View
    {
        $user = User::with(['roles', 'operatorProfile.assignedPort', 'companies.corporateCredit'])->findOrFail($id);

        $auditTrails = DB::table('adm_immutable_audit_trails')
            ->where(function ($q) use ($user) {
                $q->where(function ($sub) use ($user) {
                    $sub->where('target_type', 'users')->where('target_id', (string) $user->id);
                })->orWhere('actor_id', $user->id);
            })
            ->latest('created_at')
            ->take(10)
            ->get();

        return view('admin.users.show', [
            'role' => 'admin',
            'user' => $user,
            'auditTrails' => $auditTrails,
        ]);
    }

    /**
     * Suspend a user account.
     */
    public function suspend(Request $request, string $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if ((string) $user->id === (string) Auth::guard('admin')->id()) {
            return back()->with('error', 'Security Policy: You cannot suspend your own active administrator account.');
        }

        $previousStatus = $user->status;
        $user->update(['status' => 'SUSPENDED']);

        AuditLogService::record(
            'USER_SUSPENDED',
            'users',
            (string) $user->id,
            ['status' => $previousStatus, 'email' => $user->email],
            ['status' => 'SUSPENDED', 'email' => $user->email],
            $request
        );

        return back()->with('warning', "User {$user->email} has been SUSPENDED.");
    }

    /**
     * Restore a suspended user account.
     */
    public function restore(Request $request, string $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $previousStatus = $user->status;

        $user->update(['status' => 'ACTIVE']);

        AuditLogService::record(
            'USER_RESTORED',
            'users',
            (string) $user->id,
            ['status' => $previousStatus, 'email' => $user->email],
            ['status' => 'ACTIVE', 'email' => $user->email],
            $request
        );

        return back()->with('success', "User {$user->email} has been restored to ACTIVE status.");
    }

    /**
     * Blacklist a user account permanently.
     */
    public function block(Request $request, string $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if ((string) $user->id === (string) Auth::guard('admin')->id()) {
            return back()->with('error', 'Security Policy: You cannot blacklist your own active administrator account.');
        }

        $previousStatus = $user->status;
        $user->update(['status' => 'BLACKLISTED']);

        AuditLogService::record(
            'USER_BLACKLISTED',
            'users',
            (string) $user->id,
            ['status' => $previousStatus, 'email' => $user->email],
            ['status' => 'BLACKLISTED', 'email' => $user->email],
            $request
        );

        return back()->with('error', "User {$user->email} has been BLACKLISTED.");
    }

    /**
     * Reset 2FA for a user account (Admin intervention).
     */
    public function resetTwoFactor(Request $request, string $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        /** @var User|null $admin */
        $admin = Auth::guard('admin')->user();

        TwoFactorResetService::adminReset($user, $admin, $request->ip());

        return back()->with('success', "Two-Factor Authentication for {$user->email} has been reset. The user must configure a new authenticator upon next login.");
    }
}
