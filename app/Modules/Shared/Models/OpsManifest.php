<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpsManifest extends Model
{
    use HasUuids;

    protected $table = 'ops_manifests';

    protected $fillable = [
        'manifest_number',
        'origin_port_id',
        'destination_port_id',
        'vessel_or_vehicle_name',
        'voyage_number',
        'departure_eta',
        'arrival_eta',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'departure_eta' => 'datetime',
            'arrival_eta' => 'datetime',
        ];
    }

    // Status manifes — hindari magic string di seluruh codebase
    const STATUS_PREPARING = 'PREPARING';

    const STATUS_LOCKED_SEALED = 'LOCKED_SEALED';

    const STATUS_SAILING = 'SAILING';

    const STATUS_ARRIVED = 'ARRIVED';

    const STATUS_UNLOADED = 'UNLOADED';

    public function originPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'origin_port_id');
    }

    public function destinationPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'destination_port_id');
    }

    public function manifestItems(): HasMany
    {
        return $this->hasMany(OpsManifestItem::class, 'manifest_id');
    }

    public function items(): HasMany
    {
        return $this->manifestItems();
    }

    public function fleetTelemetries(): HasMany
    {
        return $this->hasMany(OpsFleetTelemetry::class, 'manifest_id');
    }
}
