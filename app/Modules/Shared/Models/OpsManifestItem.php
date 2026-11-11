<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpsManifestItem extends Model
{
    protected $table = 'ops_manifest_items';

    protected $fillable = [
        'manifest_id',
        'cargo_booking_id',
        'container_serial_number',
        'container_type',
        'seal_number',
    ];

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(OpsManifest::class, 'manifest_id');
    }

    public function cargoBooking(): BelongsTo
    {
        return $this->belongsTo(OpsCargoBooking::class, 'cargo_booking_id');
    }
}
