<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\AdmCorporateCredit;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CorporateCreditController extends Controller
{
    /**
     * Display corporate credit underwriting lines.
     */
    public function index(Request $request)
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
    public function show(string $id)
    {
        $credit = AdmCorporateCredit::with(['company.user'])->findOrFail($id);

        return view('admin.finance.credit-show', [
            'role' => 'admin',
            'credit' => $credit,
        ]);
    }

    /**
     * Update corporate credit parameters.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'credit_limit_amount' => 'required|numeric|min:0',
            'top_days' => 'required|in:30,45,60',
            'kyc_status' => 'required|in:PENDING_VERIFICATION,VERIFIED_APPROVED,REJECTED',
        ]);

        $credit = AdmCorporateCredit::findOrFail($id);
        $credit->update($validated);

        return back()->with('success', "Credit line for {$credit->company?->company_name} updated successfully.");
    }
}
