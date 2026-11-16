<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\AdmCorporateCredit;
use App\Modules\Shared\Models\Company;
use App\Modules\Shared\Models\User;
use App\Modules\Shared\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class KycController extends Controller
{
    private const CUSTOMER_TYPES = [
        'RETAIL_CUSTOMER',
        'CORPORATE_USER',
        'CORPORATE_CUSTOMER',
    ];

    /**
     * Display a listing of corporate KYC applications.
     */
    public function index(Request $request): View
    {
        $query = User::whereIn('user_type', self::CUSTOMER_TYPES)
            ->with(['companies.corporateCredit'])
            ->latest();

        if ($request->filled('status') && $request->status !== 'ALL') {
            $query->where('kyc_aml_status', $request->status);
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->search);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('email', 'LIKE', "%{$term}%")
                    ->orWhereHas('companies', function ($cq) use ($term) {
                        $cq->where('company_name', 'LIKE', "%{$term}%")
                            ->orWhere('nib', 'LIKE', "%{$term}%")
                            ->orWhere('npwp', 'LIKE', "%{$term}%");
                    });
            });
        }

        $users = $query->paginate(15)->withQueryString();

        $baseCohort = User::whereIn('user_type', self::CUSTOMER_TYPES);
        $stats = [
            'total' => (clone $baseCohort)->count(),
            'pending' => (clone $baseCohort)->where('kyc_aml_status', 'PENDING')->count(),
            'verified' => (clone $baseCohort)->where('kyc_aml_status', 'VERIFIED')->count(),
            'rejected' => (clone $baseCohort)->where('kyc_aml_status', 'REJECTED')->count(),
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
    public function show(string $id): View
    {
        $user = User::with(['companies.corporateCredit', 'companies.invoices'])->findOrFail($id);

        return view('admin.kyc.show', [
            'role' => 'admin',
            'user' => $user,
        ]);
    }

    /**
     * Approve customer corporate KYC verification and auto-provision Corporate Credit facility.
     */
    public function approve(Request $request, string $id): RedirectResponse
    {
        $user = User::with('companies')->findOrFail($id);

        $before = [
            'kyc_aml_status' => $user->kyc_aml_status,
            'user_type' => $user->user_type,
        ];

        $user->update([
            'kyc_aml_status' => 'VERIFIED',
            'user_type' => 'CORPORATE_USER',
        ]);

        $company = $user->companies->first();
        if (! $company) {
            $company = Company::create([
                'user_id' => $user->id,
                'company_name' => $user->name.' Logistics Enterprise',
                'pic_name' => $user->name,
                'pic_phone' => $user->phone ?? '+62811000000',
                'email' => $user->email,
            ]);
        }

        $credit = AdmCorporateCredit::firstOrNew(['company_id' => $company->id]);
        if (! $credit->exists || (float) $credit->credit_limit_amount <= 0) {
            $credit->credit_limit_amount = 50000000;
            $credit->used_credit_amount = $credit->used_credit_amount ?? 0;
            $credit->top_days = $credit->top_days ?? '30';
            $credit->debt_enforcement_stage = $credit->debt_enforcement_stage ?? 'NORMAL';
        }
        $credit->kyc_status = 'VERIFIED_APPROVED';
        $credit->verified_by_admin_id = Auth::guard('admin')->id();
        $credit->save();

        AuditLogService::record(
            'KYC_APPROVED',
            'users',
            (string) $user->id,
            $before,
            [
                'kyc_aml_status' => 'VERIFIED',
                'user_type' => 'CORPORATE_USER',
                'company_id' => $company->id,
                'credit_facility_id' => $credit->id,
                'credit_limit_amount' => (float) $credit->credit_limit_amount,
            ],
            $request
        );

        return back()->with('success', "Corporate KYC for {$user->name} approved, account upgraded to Corporate, and B2B Credit Facility provisioned.");
    }

    /**
     * Reject customer corporate KYC verification.
     */
    public function reject(Request $request, string $id): RedirectResponse
    {
        $user = User::with('companies')->findOrFail($id);

        $before = [
            'kyc_aml_status' => $user->kyc_aml_status,
            'user_type' => $user->user_type,
        ];

        $user->update([
            'kyc_aml_status' => 'REJECTED',
        ]);

        $company = $user->companies->first();
        if ($company) {
            AdmCorporateCredit::where('company_id', $company->id)->update([
                'kyc_status' => 'REJECTED',
                'verified_by_admin_id' => Auth::guard('admin')->id(),
            ]);
        }

        AuditLogService::record(
            'KYC_REJECTED',
            'users',
            (string) $user->id,
            $before,
            ['kyc_aml_status' => 'REJECTED'],
            $request
        );

        return back()->with('warning', "Corporate KYC for {$user->name} rejected.");
    }
}
