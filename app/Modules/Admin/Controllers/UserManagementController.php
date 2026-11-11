<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserManagementController extends Controller
{
    /**
     * Display a listing of system network users.
     */
    public function index(Request $request)
    {
        $query = User::with(['roles'])->latest();

        if ($request->filled('role') && $request->role !== 'ALL') {
            $query->where('user_type', $request->role);
        }

        if ($request->filled('search')) {
            $term = trim($request->search);
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
    public function show(string $id)
    {
        $user = User::with(['roles', 'operatorProfile', 'companies'])->findOrFail($id);

        return view('admin.users.show', [
            'role' => 'admin',
            'user' => $user,
        ]);
    }

    /**
     * Suspend a user account.
     */
    public function suspend(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'SUSPENDED']);

        return back()->with('warning', "User {$user->email} has been SUSPENDED.");
    }

    /**
     * Restore a suspended user account.
     */
    public function restore(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'ACTIVE']);

        return back()->with('success', "User {$user->email} has been restored to ACTIVE status.");
    }

    /**
     * Blacklist a user account permanently.
     */
    public function block(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'BLACKLISTED']);

        return back()->with('error', "User {$user->email} has been BLACKLISTED.");
    }
}
