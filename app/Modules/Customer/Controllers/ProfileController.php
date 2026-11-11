<?php

namespace App\Modules\Customer\Controllers;

use App\Modules\Shared\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    /**
     * Display customer account settings, KYC status & company profile.
     */
    public function show()
    {
        $user = Auth::guard('customer')->user();
        $company = Company::where('user_id', $user->id)->first();

        return view('customer.profile.show', [
            'role' => 'customer',
            'user' => $user,
            'company' => $company,
        ]);
    }

    /**
     * Update customer profile and preferences.
     */
    public function update(Request $request)
    {
        $user = Auth::guard('customer')->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'timezone' => 'required|string|max:50',
        ]);

        $user->update($validated);

        return back()->with('success', 'Profile and timezone updated successfully.');
    }

    /**
     * Submit Corporate Account KYC Upgrade request.
     */
    public function requestCorporateUpgrade(Request $request)
    {
        $user = Auth::guard('customer')->user();

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'nib' => 'required|string|max:50',
            'npwp' => 'required|string|max:50',
            'company_address' => 'required|string',
            'industry' => 'required|string|max:100',
        ]);

        $company = Company::updateOrCreate(
            ['user_id' => $user->id],
            array_merge($validated, [
                'id' => Str::uuid()->toString(),
                'pic_name' => $user->name,
                'pic_phone' => $user->phone ?? '+62811000000',
                'email' => $user->email,
            ])
        );

        $user->update([
            'user_type' => 'CORPORATE_CUSTOMER',
            'kyc_aml_status' => 'PENDING',
        ]);

        return back()->with('success', 'Corporate KYC credentials submitted for Compliance verification.');
    }
}
