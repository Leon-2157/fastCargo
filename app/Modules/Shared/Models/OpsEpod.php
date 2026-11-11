<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpsEpod extends Model
{
    protected $table = 'ops_epods';

    protected $fillable = [
        'cargo_booking_id',
        'recipient_name',
        'recipient_phone',
        'signature_image_path',
        'photo_proof_path',
        'latitude',
        'longitude',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'confirmed_at' => 'datetime',
        ];
    }

    public function cargoBooking(): BelongsTo
    {
        return $this->belongsTo(OpsCargoBooking::class, 'cargo_booking_id');
    }
}
