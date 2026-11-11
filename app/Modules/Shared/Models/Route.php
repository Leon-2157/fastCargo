<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    use HasUuids;

    protected $table = 'routes';

    protected $fillable = [
        'origin_port_id',
        'destination_port_id',
        'transport_mode',
        'transit_days',
        'base_rate_idr',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_rate_idr' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function originPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'origin_port_id');
    }

    public function destinationPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'destination_port_id');
    }

    public function cargoBookings(): HasMany
    {
        return $this->hasMany(OpsCargoBooking::class);
    }
}
