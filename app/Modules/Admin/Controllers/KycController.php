<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class KycController extends Controller
{
    /**
     * Display a listing of corporate KYC applications.
     */
    public function index(Request $request)
    {
        $query = User::whereNotNull('user_type')
            ->with(['companies'])
            ->latest();

        if ($request->filled('status') && $request->status !== 'ALL') {
            $query->where('kyc_aml_status', $request->status);
        }

        $users = $query->paginate(15)->withQueryString();

        $stats = [
            'pending' => User::where('kyc_aml_status', 'PENDING')->count(),
            'verified' => User::where('kyc_aml_status', 'VERIFIED')->count(),
            'rejected' => User::where('kyc_aml_status', 'REJECTED')->count(),
        ];

        return view('admin.kyc.index', [
            'role' => 'admin',
            'users' => $users,
            'stats' => $stats,
        ]);
    }

    /**
     * Display detailed corporate KYC documentation.
     */
    public function show(string $id)
    {
        $user = User::with(['companies'])->findOrFail($id);

        return view('admin.kyc.show', [
            'role' => 'admin',
            'user' => $user,
        ]);
    }

    /**
     * Approve customer corporate KYC verification.
     */
    public function approve(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $user->update([
            'kyc_aml_status' => 'VERIFIED',
            'user_type' => 'CORPORATE_CUSTOMER',
        ]);

        return back()->with('success', "Corporate KYC for {$user->name} approved and verified.");
    }

    /**
     * Reject customer corporate KYC verification.
     */
    public function reject(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $user->update([
            'kyc_aml_status' => 'REJECTED',
        ]);

        return back()->with('warning', "Corporate KYC for {$user->name} rejected.");
    }
}
