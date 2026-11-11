<?php

namespace App\Modules\Operator\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Display operator profile & hub duty status.
     */
    public function show()
    {
        $user = Auth::guard('operator')->user();
        $profile = $user->operatorProfile;

        return view('operator.profile.show', [
            'role' => 'operator',
            'user' => $user,
            'profile' => $profile,
        ]);
    }

    /**
     * Update operator contact details.
     */
    public function update(Request $request)
    {
        $user = Auth::guard('operator')->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $user->update($validated);

        return back()->with('success', 'Profile credentials updated.');
    }

    /**
     * Toggle terminal duty status.
     */
    public function toggleDuty(Request $request)
    {
        $user = Auth::guard('operator')->user();
        $profile = $user->operatorProfile;

        if ($profile) {
            $profile->update([
                'is_on_duty' => ! $profile->is_on_duty,
            ]);
            $msg = $profile->is_on_duty ? 'You are now marked ON DUTY.' : 'You are now marked OFF DUTY.';

            return back()->with('success', $msg);
        }

        return back()->with('error', 'Operator profile not initialized.');
    }
}
