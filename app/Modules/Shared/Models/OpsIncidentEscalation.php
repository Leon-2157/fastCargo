<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpsIncidentEscalation extends Model
{
    use HasUuids;

    protected $table = 'ops_incident_escalations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'incident_ticket_no',
        'cargo_booking_id',
        'reporter_operator_id',
        'incident_type',
        'requested_amount',
        'bap_document_path',
        'approval_status',
        'admin_notes',
        'sla_response_minutes',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'sla_response_minutes' => 'integer',
    ];

    public function cargoBooking(): BelongsTo
    {
        return $this->belongsTo(OpsCargoBooking::class, 'cargo_booking_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(OpsOperatorProfile::class, 'reporter_operator_id');
    }
}
