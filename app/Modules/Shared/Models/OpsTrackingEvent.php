<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpsTrackingEvent extends Model
{
    protected $table = 'ops_tracking_events';

    // Tabel hanya menyimpan created_at, tidak ada updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'cargo_booking_id',
        'event_name',
        'current_status',
        'latitude',
        'longitude',
        'operator_id',
        'event_timestamp',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'event_timestamp' => 'datetime',
        ];
    }

    public function cargoBooking(): BelongsTo
    {
        return $this->belongsTo(OpsCargoBooking::class, 'cargo_booking_id');
    }

    // operator_id nullable — relasi opsional
    public function operator(): BelongsTo
    {
        return $this->belongsTo(OpsOperatorProfile::class, 'operator_id');
    }
}
