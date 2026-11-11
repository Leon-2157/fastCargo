<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OpsCargoBooking extends Model
{
    use HasUuids;

    protected $table = 'ops_cargo_bookings';

    protected $fillable = [
        'tracking_number',
        'shipper_id',
        'company_id',
        'route_id',
        'hs_code',
        'booking_type',
        'cargo_type',
        'total_weight_kg',
        'total_volume_cbm',
        'declared_goods_value',
        'currency',
        'cargo_status',
        'customs_channel',
        'assigned_operator_id',
    ];

    protected function casts(): array
    {
        return [
            'total_weight_kg' => 'decimal:2',
            'total_volume_cbm' => 'decimal:2',
            'declared_goods_value' => 'decimal:2',
        ];
    }

    // Status constants — hindari magic string di seluruh codebase
    const STATUS_DRAFT = 'DRAFT';

    const STATUS_BOOKED = 'BOOKED';

    const STATUS_GATE_IN = 'GATE_IN';

    const STATUS_SAILING = 'SAILING';

    const STATUS_DELIVERED = 'DELIVERED';

    const STATUS_CANCELLED = 'CANCELLED';

    const STATUS_WEATHER_HOLD = 'WEATHER_HOLD';

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipper_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function assignedOperator(): BelongsTo
    {
        return $this->belongsTo(OpsOperatorProfile::class, 'assigned_operator_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OpsCargoItem::class, 'cargo_booking_id');
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(OpsTrackingEvent::class, 'cargo_booking_id')->orderBy('event_timestamp');
    }

    public function epod(): HasOne
    {
        return $this->hasOne(OpsEpod::class, 'cargo_booking_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(AdmInvoice::class, 'cargo_booking_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(AdmInvoice::class, 'cargo_booking_id');
    }

    public function escrowLedger(): HasOne
    {
        return $this->hasOne(AdmEscrowLedger::class, 'cargo_booking_id');
    }

    public function manifestItems(): HasMany
    {
        return $this->hasMany(OpsManifestItem::class, 'cargo_booking_id');
    }

    public function manifests(): BelongsToMany
    {
        return $this->belongsToMany(
            OpsManifest::class,
            'ops_manifest_items',
            'cargo_booking_id',
            'manifest_id'
        )->withPivot(['container_serial_number', 'container_type', 'seal_number'])
            ->withTimestamps();
    }

    public function isRetailLcl(): bool
    {
        return $this->booking_type === 'RETAIL_LCL';
    }

    public function isCorporateFcl(): bool
    {
        return $this->booking_type === 'CORPORATE_FCL';
    }
}
