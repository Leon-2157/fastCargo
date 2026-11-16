<?php

namespace App\Modules\Admin\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class AuditTrailController extends Controller
{
    /**
     * Display immutable system audit logs with search & action filtering.
     */
    public function index(Request $request): View
    {
        $query = DB::table('adm_immutable_audit_trails')
            ->leftJoin('users', 'adm_immutable_audit_trails.actor_id', '=', 'users.id')
            ->select('adm_immutable_audit_trails.*', 'users.name as actor_name', 'users.email as actor_email')
            ->latest('adm_immutable_audit_trails.created_at');

        if ($request->filled('action') && $request->action !== 'ALL') {
            $query->where('adm_immutable_audit_trails.action', $request->action);
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->search);
            $query->where(function ($q) use ($term) {
                $q->where('adm_immutable_audit_trails.action', 'LIKE', "%{$term}%")
                    ->orWhere('adm_immutable_audit_trails.target_type', 'LIKE', "%{$term}%")
                    ->orWhere('adm_immutable_audit_trails.target_id', 'LIKE', "%{$term}%")
                    ->orWhere('adm_immutable_audit_trails.ip_address', 'LIKE', "%{$term}%")
                    ->orWhere('users.name', 'LIKE', "%{$term}%")
                    ->orWhere('users.email', 'LIKE', "%{$term}%");
            });
        }

        $trails = $query->paginate(20)->withQueryString();

        $availableActions = DB::table('adm_immutable_audit_trails')
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('admin.audit.index', [
            'role' => 'admin',
            'trails' => $trails,
            'availableActions' => $availableActions,
        ]);
    }
}
