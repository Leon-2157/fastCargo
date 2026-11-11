<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpsOperatorProfile extends Model
{
    use HasUuids;

    protected $table = 'ops_operator_profiles';

    protected $fillable = [
        'user_id',
        'employee_id',
        'assigned_hub_id',
        'role_tier',
        'wireguard_pubkey',
        'device_fingerprint',
        'kpi_rating',
        'is_on_duty',
    ];

    protected function casts(): array
    {
        return [
            'kpi_rating' => 'decimal:2',
            'is_on_duty' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'assigned_hub_id');
    }
}
