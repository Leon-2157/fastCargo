<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmEscrowLedger extends Model
{
    use HasUuids;

    protected $table = 'adm_escrow_ledgers';

    protected $fillable = [
        'cargo_booking_id',
        'total_paid_amount',
        'milestone_1_pickup_amount',
        'milestone_1_released_at',
        'milestone_2_sailing_amount',
        'milestone_2_released_at',
        'milestone_3_delivered_amount',
        'milestone_3_released_at',
        'escrow_status',
    ];

    protected function casts(): array
    {
        return [
            'total_paid_amount' => 'decimal:2',
            'milestone_1_pickup_amount' => 'decimal:2',
            'milestone_1_released_at' => 'datetime',
            'milestone_2_sailing_amount' => 'decimal:2',
            'milestone_2_released_at' => 'datetime',
            'milestone_3_delivered_amount' => 'decimal:2',
            'milestone_3_released_at' => 'datetime',
        ];
    }

    // Status escrow — hindari magic string di seluruh codebase
    const STATUS_HELD_IN_ESCROW = 'HELD_IN_ESCROW';

    const STATUS_PARTIALLY_RELEASED = 'PARTIALLY_RELEASED';

    const STATUS_FULLY_SETTLED = 'FULLY_SETTLED';

    const STATUS_DISPUTE_FROZEN = 'DISPUTE_FROZEN';

    public function cargoBooking(): BelongsTo
    {
        return $this->belongsTo(OpsCargoBooking::class, 'cargo_booking_id');
    }
}
