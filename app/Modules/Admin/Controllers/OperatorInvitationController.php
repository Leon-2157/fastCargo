<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\AdmOperatorInvitation;
use App\Modules\Shared\Models\Port;
use App\Modules\Shared\WhatsApp\WaNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OperatorInvitationController extends Controller
{
    /**
     * Display a listing of operator applications.
     */
    public function index(Request $request): View
    {
        $query = AdmOperatorInvitation::with('assignedHub')->latest();

        if ($request->filled('status') && $request->status !== 'ALL') {
            $query->where('status', $request->status);
        }

        $invitations = $query->paginate(15)->withQueryString();

        $stats = [
            'pending' => AdmOperatorInvitation::where('status', AdmOperatorInvitation::STATUS_PENDING_REVIEW)->count(),
            'invited' => AdmOperatorInvitation::where('status', AdmOperatorInvitation::STATUS_INVITATION_SENT)->count(),
            'registered' => AdmOperatorInvitation::where('status', AdmOperatorInvitation::STATUS_REGISTERED)->count(),
            'rejected' => AdmOperatorInvitation::where('status', AdmOperatorInvitation::STATUS_REJECTED)->count(),
        ];

        return view('admin.invitation.index', [
            'role' => 'admin',
            'invitations' => $invitations,
            'stats' => $stats,
        ]);
    }

    /**
     * Display a specific operator application.
     */
    public function show(string $id): View
    {
        $invitation = AdmOperatorInvitation::with(['assignedHub', 'invitedByAdmin'])->findOrFail($id);
        $hubs = Port::orderBy('name')->get(['id', 'name', 'city', 'country_code']);

        return view('admin.invitation.show', [
            'role' => 'admin',
            'invitation' => $invitation,
            'hubs' => $hubs,
        ]);
    }

    /**
     * Approve operator application, generate a 7-day onboarding token, and queue WhatsApp notification.
     */
    public function approve(Request $request, string $id): RedirectResponse
    {
        $invitation = AdmOperatorInvitation::findOrFail($id);

        if ($invitation->status === AdmOperatorInvitation::STATUS_REGISTERED) {
            return back()->with('warning', 'This applicant has already completed operator registration.');
        }

        $validated = $request->validate([
            'assigned_hub_id' => ['nullable', 'string', 'exists:ports,id'],
            'assigned_role_tier' => ['nullable', 'string', 'in:OPERATOR_DOMESTIC,OPERATOR_INTERNATIONAL'],
        ]);

        $hubId = $validated['assigned_hub_id'] ?? $invitation->assigned_hub_id;
        $roleTier = $validated['assigned_role_tier'] ?? 'OPERATOR_INTERNATIONAL';

        $registrationUrl = $this->issueInvitation($invitation, $hubId, $roleTier);

        return back()->with('success', "Application approved. Onboarding registration link generated: {$registrationUrl}");
    }

    /**
     * Reject operator application and invalidate any existing onboarding token.
     */
    public function reject(Request $request, string $id): RedirectResponse
    {
        $invitation = AdmOperatorInvitation::findOrFail($id);

        if ($invitation->status === AdmOperatorInvitation::STATUS_REGISTERED) {
            return back()->with('warning', 'Cannot reject an application that has already completed registration.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $invitation->update([
            'status' => AdmOperatorInvitation::STATUS_REJECTED,
            'invitation_token' => null,
            'expires_at' => null,
            'invited_by_admin_id' => auth('admin')->id(),
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        return back()->with('warning', "Application for {$invitation->name} ({$invitation->email}) marked as REJECTED.");
    }

    /**
     * Regenerate the onboarding invitation token for another 7 days and resend WhatsApp notification.
     */
    public function resend(Request $request, string $id): RedirectResponse
    {
        $invitation = AdmOperatorInvitation::findOrFail($id);

        if ($invitation->status === AdmOperatorInvitation::STATUS_REGISTERED) {
            return back()->with('warning', 'Cannot renew token: operator has already completed registration.');
        }

        if ($invitation->status === AdmOperatorInvitation::STATUS_REJECTED) {
            return back()->with('warning', 'Cannot renew token for a rejected application. Approve the application first.');
        }

        $hubId = $invitation->assigned_hub_id;
        $roleTier = $invitation->assigned_role_tier ?: 'OPERATOR_INTERNATIONAL';

        $registrationUrl = $this->issueInvitation($invitation, $hubId, $roleTier);

        return back()->with('success', "New onboarding token generated: {$registrationUrl}");
    }

    /**
     * Issue or renew a 7-day onboarding invitation token and queue the WhatsApp alert.
     */
    private function issueInvitation(AdmOperatorInvitation $invitation, ?string $hubId, string $roleTier): string
    {
        $token = Str::random(40);

        $invitation->update([
            'status' => AdmOperatorInvitation::STATUS_INVITATION_SENT,
            'invitation_token' => $token,
            'expires_at' => now()->addDays(7),
            'assigned_hub_id' => $hubId,
            'assigned_role_tier' => $roleTier,
            'invited_by_admin_id' => auth('admin')->id(),
            'rejection_reason' => null,
        ]);

        $registrationUrl = $this->buildRegistrationUrl($token);

        WaNotificationService::notifyOperatorApplicationApproved($invitation, $registrationUrl);

        return $registrationUrl;
    }

    private function buildRegistrationUrl(string $token): string
    {
        return rtrim((string) config('app.portal_urls.operator', 'http://operator.fastcargo.local:8080'), '/')."/register/{$token}";
    }
}
