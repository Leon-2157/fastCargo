<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\AdmCorporateCredit;
use App\Modules\Shared\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class CorporateCreditController extends Controller
{
    /**
     * Display corporate credit underwriting lines.
     */
    public function index(Request $request): View
    {
        $credits = AdmCorporateCredit::with(['company.user'])
            ->latest()
            ->paginate(15);

        return view('admin.finance.credits', [
            'role' => 'admin',
            'credits' => $credits,
        ]);
    }

    /**
     * Display specific credit line.
     */
    public function show(string $id): View
    {
        $credit = AdmCorporateCredit::with([
            'company.user',
            'company.invoices' => fn ($q) => $q->latest()->take(10),
            'verifiedByAdmin',
        ])->findOrFail($id);

        return view('admin.finance.credit-show', [
            'role' => 'admin',
            'credit' => $credit,
        ]);
    }

    /**
     * Update corporate credit parameters.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'credit_limit_amount' => ['required', 'numeric', 'min:0'],
            'top_days' => ['required', 'in:30,45,60'],
            'kyc_status' => ['required', 'in:PENDING_VERIFICATION,VERIFIED_APPROVED,REJECTED'],
            'debt_enforcement_stage' => ['nullable', 'in:NORMAL,WARNING_LETTER_1,ACCOUNT_FROZEN,LEGAL_DEFAULT'],
            'bank_guarantee_ref' => ['nullable', 'string', 'max:120'],
        ]);

        $credit = AdmCorporateCredit::with('company')->findOrFail($id);

        $before = [
            'credit_limit_amount' => (float) $credit->credit_limit_amount,
            'top_days' => $credit->top_days,
            'kyc_status' => $credit->kyc_status,
            'debt_enforcement_stage' => $credit->debt_enforcement_stage,
            'bank_guarantee_ref' => $credit->bank_guarantee_ref,
        ];

        $validated['verified_by_admin_id'] = Auth::guard('admin')->id();
        if (! isset($validated['debt_enforcement_stage'])) {
            $validated['debt_enforcement_stage'] = $credit->debt_enforcement_stage ?? 'NORMAL';
        }

        $credit->update($validated);

        AuditLogService::record(
            'CORPORATE_CREDIT_UPDATED',
            'adm_corporate_credits',
            (string) $credit->id,
            $before,
            [
                'company_name' => $credit->company?->company_name,
                'credit_limit_amount' => (float) $credit->credit_limit_amount,
                'top_days' => $credit->top_days,
                'kyc_status' => $credit->kyc_status,
                'debt_enforcement_stage' => $credit->debt_enforcement_stage,
                'bank_guarantee_ref' => $credit->bank_guarantee_ref,
            ],
            $request
        );

        return back()->with('success', "Credit line for {$credit->company?->company_name} updated successfully.");
    }
}
