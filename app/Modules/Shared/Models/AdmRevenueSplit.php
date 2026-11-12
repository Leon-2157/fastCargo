<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmRevenueSplit extends Model
{
    use HasUuids;

    protected $table = 'adm_revenue_splits';

    protected $fillable = [
        'cargo_booking_id',
        'platform_fee_amount',
        'operator_share_amount',
        'tax_and_port_dues',
        'net_platform_profit',
    ];

    protected function casts(): array
    {
        return [
            'platform_fee_amount' => 'decimal:2',
            'operator_share_amount' => 'decimal:2',
            'tax_and_port_dues' => 'decimal:2',
            'net_platform_profit' => 'decimal:2',
        ];
    }

    public function cargoBooking(): BelongsTo
    {
        return $this->belongsTo(OpsCargoBooking::class, 'cargo_booking_id');
    }
}
