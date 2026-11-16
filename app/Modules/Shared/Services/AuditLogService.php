<?php

namespace App\Modules\Shared\Services;

use App\Modules\Shared\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuditLogService
{
    /**
     * Record an immutable governance audit trail entry.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function record(
        string $action,
        string $targetType,
        ?string $targetId = null,
        ?array $before = null,
        ?array $after = null,
        ?Request $request = null,
        ?User $actor = null,
        string $actorType = 'ADMIN'
    ): void {
        $resolvedActor = $actor ?? Auth::guard('admin')->user() ?? Auth::user();
        $resolvedRequest = $request ?? request();

        DB::table('adm_immutable_audit_trails')->insert([
            'actor_id' => $resolvedActor?->id,
            'actor_type' => $actorType,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId ? substr($targetId, 0, 36) : null,
            'before' => $before !== null ? json_encode($before) : null,
            'after' => $after !== null ? json_encode($after) : null,
            'ip_address' => $resolvedRequest?->ip() ?? '127.0.0.1',
            'created_at' => now(),
        ]);
    }
}
