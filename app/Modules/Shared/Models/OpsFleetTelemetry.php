<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpsFleetTelemetry extends Model
{
    protected $table = 'ops_fleet_telemetry';

    // Tabel hanya menyimpan created_at, tidak ada updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'manifest_id',
        'device_id',
        'latitude',
        'longitude',
        'speed_knots',
        'heading_degrees',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'speed_knots' => 'decimal:2',
            'heading_degrees' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(OpsManifest::class, 'manifest_id');
    }
}
