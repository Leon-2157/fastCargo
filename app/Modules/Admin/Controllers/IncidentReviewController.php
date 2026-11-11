<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\OpsIncidentEscalation;
use App\Modules\Shared\WhatsApp\WaNotificationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IncidentReviewController extends Controller
{
    /**
     * Display a listing of field incident tickets.
     */
    public function index(Request $request)
    {
        $incidents = OpsIncidentEscalation::with(['cargoBooking.route', 'reporter.user'])
            ->latest()
            ->paginate(15);

        return view('admin.incident.index', [
            'role' => 'admin',
            'incidents' => $incidents,
        ]);
    }

    /**
     * Inspect a specific incident escalation ticket.
     */
    public function show(string $id)
    {
        $incident = OpsIncidentEscalation::with(['cargoBooking.route', 'reporter.user'])
            ->findOrFail($id);

        return view('admin.incident.show', [
            'role' => 'admin',
            'incident' => $incident,
        ]);
    }

    /**
     * Approve emergency incident disbursement or waiver.
     */
    public function approve(Request $request, string $id)
    {
        $incident = OpsIncidentEscalation::findOrFail($id);

        $incident->update([
            'approval_status' => 'APPROVED_BY_ADMIN',
            'admin_notes' => ($incident->admin_notes ? $incident->admin_notes."\n\n" : '').'Admin Note: Approved by '.auth('admin')->user()->name.' on '.now()->toDateTimeString(),
        ]);

        WaNotificationService::notifyIncidentUpdate($incident, 'APPROVED_BY_ADMIN');

        return back()->with('success', "Incident {$incident->incident_ticket_no} approved. Funds released / waiver granted.");
    }

    /**
     * Reject incident escalation.
     */
    public function reject(Request $request, string $id)
    {
        $incident = OpsIncidentEscalation::findOrFail($id);

        $incident->update([
            'approval_status' => 'REJECTED',
            'admin_notes' => ($incident->admin_notes ? $incident->admin_notes."\n\n" : '').'Admin Note: Rejected by '.auth('admin')->user()->name,
        ]);

        WaNotificationService::notifyIncidentUpdate($incident, 'REJECTED');

        return back()->with('warning', "Incident {$incident->incident_ticket_no} marked as REJECTED.");
    }
}
