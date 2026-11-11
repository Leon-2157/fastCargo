<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpsCargoItem extends Model
{
    protected $table = 'ops_cargo_items';

    protected $fillable = [
        'cargo_booking_id',
        'item_name',
        'hs_code',
        'package_type',
        'quantity',
        'weight_kg',
        'length_cm',
        'width_cm',
        'height_cm',
        'volume_cbm',
        'is_dangerous_good',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'volume_cbm' => 'decimal:2',
            'is_dangerous_good' => 'boolean',
        ];
    }

    public function cargoBooking(): BelongsTo
    {
        return $this->belongsTo(OpsCargoBooking::class, 'cargo_booking_id');
    }
}
