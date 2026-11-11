<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmInvoice extends Model
{
    use HasUuids;

    protected $table = 'adm_invoices';

    protected $fillable = [
        'invoice_number',
        'company_id',
        'cargo_booking_id',
        'total_invoice_amount',
        'currency',
        'exchange_rate_locked',
        'due_date',
        'payment_status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'total_invoice_amount' => 'decimal:2',
            'exchange_rate_locked' => 'decimal:6',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    // Status pembayaran invoice — hindari magic string di seluruh codebase
    const STATUS_UNPAID = 'UNPAID';

    const STATUS_PARTIALLY_PAID = 'PARTIALLY_PAID';

    const STATUS_OVERDUE = 'OVERDUE';

    const STATUS_PAID_SETTLED = 'PAID_SETTLED';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // cargo_booking_id nullable — invoice bisa dibuat tanpa booking (mis. biaya admin)
    public function cargoBooking(): BelongsTo
    {
        return $this->belongsTo(OpsCargoBooking::class, 'cargo_booking_id');
    }

    public function booking(): BelongsTo
    {
        return $this->cargoBooking();
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'invoice_id');
    }
}
