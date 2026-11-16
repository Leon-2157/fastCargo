<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\OpsIncidentEscalation;
use App\Modules\Shared\Services\AuditLogService;
use App\Modules\Shared\WhatsApp\WaNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class IncidentReviewController extends Controller
{
    /**
     * Display a listing of field incident tickets.
     */
    public function index(Request $request): View
    {
        $query = OpsIncidentEscalation::with(['cargoBooking.route', 'reporter.user'])->latest();

        if ($request->filled('status') && $request->status !== 'ALL') {
            if (in_array($request->status, ['PENDING', 'PENDING_REVIEW'], true)) {
                $query->whereIn('approval_status', ['PENDING', 'PENDING_REVIEW']);
            } else {
                $query->where('approval_status', $request->status);
            }
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->search);
            $query->where(function ($q) use ($term) {
                $q->where('incident_ticket_no', 'LIKE', "%{$term}%")
                    ->orWhere('incident_type', 'LIKE', "%{$term}%")
                    ->orWhereHas('cargoBooking', function ($bq) use ($term) {
                        $bq->where('tracking_number', 'LIKE', "%{$term}%");
                    });
            });
        }

        $incidents = $query->paginate(15)->withQueryString();

        $stats = [
            'pending' => OpsIncidentEscalation::whereIn('approval_status', ['PENDING', 'PENDING_REVIEW'])->count(),
            'approved' => OpsIncidentEscalation::where('approval_status', 'APPROVED_BY_ADMIN')->count(),
            'rejected' => OpsIncidentEscalation::where('approval_status', 'REJECTED')->count(),
        ];

        return view('admin.incident.index', [
            'role' => 'admin',
            'incidents' => $incidents,
            'stats' => $stats,
        ]);
    }

    /**
     * Inspect a specific incident escalation ticket.
     */
    public function show(string $id): View
    {
        $incident = OpsIncidentEscalation::with([
            'cargoBooking.route.originPort',
            'cargoBooking.route.destinationPort',
            'cargoBooking.shipper',
            'reporter.user',
        ])->findOrFail($id);

        return view('admin.incident.show', [
            'role' => 'admin',
            'incident' => $incident,
        ]);
    }

    /**
     * Approve emergency incident disbursement or waiver.
     */
    public function approve(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $incident = OpsIncidentEscalation::findOrFail($id);
        $before = [
            'approval_status' => $incident->approval_status,
            'admin_notes' => $incident->admin_notes,
        ];

        $adminName = Auth::guard('admin')->user()?->name ?? 'Administrator';
        $customNote = trim((string) ($validated['admin_notes'] ?? ''));
        $noteEntry = "[Approved by {$adminName} on ".now()->toDateTimeString().']'
            .($customNote !== '' ? " {$customNote}" : '');

        $incident->update([
            'approval_status' => 'APPROVED_BY_ADMIN',
            'admin_notes' => ($incident->admin_notes ? $incident->admin_notes."\n\n" : '').$noteEntry,
        ]);

        WaNotificationService::notifyIncidentUpdate($incident, 'APPROVED_BY_ADMIN');

        AuditLogService::record(
            'INCIDENT_APPROVED',
            'ops_incident_escalations',
            (string) $incident->id,
            $before,
            [
                'incident_ticket_no' => $incident->incident_ticket_no,
                'approval_status' => 'APPROVED_BY_ADMIN',
                'admin_notes' => $incident->admin_notes,
            ],
            $request
        );

        return back()->with('success', "Incident {$incident->incident_ticket_no} approved. Funds released / waiver granted.");
    }

    /**
     * Reject incident escalation.
     */
    public function reject(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $incident = OpsIncidentEscalation::findOrFail($id);
        $before = [
            'approval_status' => $incident->approval_status,
            'admin_notes' => $incident->admin_notes,
        ];

        $adminName = Auth::guard('admin')->user()?->name ?? 'Administrator';
        $customNote = trim((string) ($validated['admin_notes'] ?? ''));
        $noteEntry = "[Rejected by {$adminName} on ".now()->toDateTimeString().']'
            .($customNote !== '' ? " {$customNote}" : '');

        $incident->update([
            'approval_status' => 'REJECTED',
            'admin_notes' => ($incident->admin_notes ? $incident->admin_notes."\n\n" : '').$noteEntry,
        ]);

        WaNotificationService::notifyIncidentUpdate($incident, 'REJECTED');

        AuditLogService::record(
            'INCIDENT_REJECTED',
            'ops_incident_escalations',
            (string) $incident->id,
            $before,
            [
                'incident_ticket_no' => $incident->incident_ticket_no,
                'approval_status' => 'REJECTED',
                'admin_notes' => $incident->admin_notes,
            ],
            $request
        );

        return back()->with('warning', "Incident {$incident->incident_ticket_no} marked as REJECTED.");
    }
}
