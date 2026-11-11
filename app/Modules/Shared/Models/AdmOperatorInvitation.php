<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmOperatorInvitation extends Model
{
    use HasUuids;

    protected $table = 'adm_operator_invitations';

    protected $fillable = [
        'invitation_token',
        'email',
        'phone',
        'name',
        'hub_name',
        'city',
        'status',
        'assigned_hub_id',
        'assigned_role_tier',
        'invited_by_admin_id',
        'rejection_reason',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    // Status undangan operator — hindari magic string di seluruh codebase
    const STATUS_PENDING_REVIEW = 'PENDING_REVIEW';

    const STATUS_INVITATION_SENT = 'INVITATION_SENT';

    const STATUS_REGISTERED = 'REGISTERED';

    const STATUS_REJECTED = 'REJECTED';

    const STATUS_EXPIRED = 'EXPIRED';

    public function assignedHub(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'assigned_hub_id');
    }

    public function invitedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_admin_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
