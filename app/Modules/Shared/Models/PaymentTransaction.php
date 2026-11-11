<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    use HasUuids;

    protected $table = 'payment_transactions';

    protected $fillable = [
        'invoice_id',
        'payer_user_id',
        'payment_gateway',
        'payment_channel',
        'transaction_ref',
        'amount',
        'payment_status',
        'raw_gateway_payload',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'raw_gateway_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    // Status transaksi — hindari magic string di seluruh codebase
    const STATUS_PENDING = 'PENDING';

    const STATUS_SETTLEMENT = 'SETTLEMENT';

    const STATUS_EXPIRED = 'EXPIRED';

    const STATUS_FAILED = 'FAILED';

    const STATUS_REFUNDED = 'REFUNDED';

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AdmInvoice::class, 'invoice_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }
}
