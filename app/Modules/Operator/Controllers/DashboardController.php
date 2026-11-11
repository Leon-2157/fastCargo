<?php

namespace App\Modules\Operator\Controllers;

use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\OpsIncidentEscalation;
use App\Modules\Shared\Models\OpsManifest;
use App\Modules\Shared\Services\CockpitMapService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(public CockpitMapService $cockpitMapService) {}

    public function index(Request $request): View
    {
        $user = Auth::guard('operator')->user();
        $profile = $user->operatorProfile()->with('hub')->first();

        $activeManifestsCount = OpsManifest::whereIn('status', ['PREPARING', 'LOCKED_SEALED', 'SAILING'])->count();
        $pendingApprovalsCount = OpsCargoBooking::where('cargo_status', 'DRAFT')->count();
        $openIncidentsCount = OpsIncidentEscalation::where('approval_status', 'PENDING_REVIEW')->count();
        $assignedHub = $profile?->hub;

        $recentManifests = OpsManifest::with(['originPort', 'destinationPort'])
            ->latest()
            ->take(5)
            ->get();

        $mapPayload = $this->cockpitMapService->buildOperatorPayload($user);

        $pageContext = [
            'operator' => $user->name,
            'hub_id' => $profile->assigned_hub_id ?? 'IDJKT',
            'hub_name' => $assignedHub->name ?? 'Tanjung Priok',
            'active_manifests' => $activeManifestsCount,
            'pending_approvals' => $pendingApprovalsCount,
            'open_incidents' => $openIncidentsCount,
            'on_duty' => $profile ? $profile->is_on_duty : true,
            'recent_manifests' => $recentManifests->map(fn (OpsManifest $m): array => [
                'manifest_number' => $m->manifest_number,
                'status' => $m->status,
                'vessel' => $m->vessel_or_vehicle_name,
                'corridor' => ($m->originPort->id ?? '').'->'.($m->destinationPort->id ?? ''),
            ])->all(),
        ];

        return view('operator.dashboard.index', compact(
            'user',
            'profile',
            'assignedHub',
            'activeManifestsCount',
            'pendingApprovalsCount',
            'openIncidentsCount',
            'recentManifests',
            'mapPayload',
            'pageContext'
        ))->with('role', 'operator');
    }

    public function mapData(): JsonResponse
    {
        $user = Auth::guard('operator')->user();

        return response()->json($this->cockpitMapService->buildOperatorPayload($user));
    }
}
