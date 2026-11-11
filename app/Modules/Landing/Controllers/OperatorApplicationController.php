<?php

namespace App\Modules\Landing\Controllers;

use App\Modules\Shared\Models\AdmOperatorInvitation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class OperatorApplicationController extends Controller
{
    /**
     * Simpan aplikasi calon operator dari form landing page.
     * Status awal: PENDING_REVIEW (menunggu persetujuan admin).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'hub_name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
        ]);

        AdmOperatorInvitation::create([
            'id' => Str::uuid()->toString(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'hub_name' => $validated['hub_name'],
            'city' => $validated['city'],
            'status' => 'PENDING_REVIEW',
            'assigned_role_tier' => 'OPERATOR_DOMESTIC',
        ]);

        return redirect()->to(url('/#operator-apply'))
            ->with('success', 'Application submitted successfully. Our Compliance team will review your credentials and deliver your registration token via email.');
    }
}
