<?php

namespace App\Modules\Admin\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class AuditTrailController extends Controller
{
    /**
     * Display immutable system audit logs.
     */
    public function index(Request $request)
    {
        $trails = DB::table('adm_immutable_audit_trails')
            ->leftJoin('users', 'adm_immutable_audit_trails.actor_id', '=', 'users.id')
            ->select('adm_immutable_audit_trails.*', 'users.name as actor_name', 'users.email as actor_email')
            ->latest('adm_immutable_audit_trails.created_at')
            ->paginate(20);

        return view('admin.audit.index', [
            'role' => 'admin',
            'trails' => $trails,
        ]);
    }
}
