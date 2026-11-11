<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmCorporateCredit extends Model
{
    use HasUuids;

    protected $table = 'adm_corporate_credits';

    protected $fillable = [
        'company_id',
        'credit_limit_amount',
        'used_credit_amount',
        'top_days',
        'kyc_status',
        'bank_guarantee_ref',
        'debt_enforcement_stage',
        'verified_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit_amount' => 'decimal:2',
            'used_credit_amount' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_admin_id');
    }

    // Sisa kredit yang dapat digunakan perusahaan
    public function availableCredit(): string
    {
        return bcsub((string) $this->credit_limit_amount, (string) $this->used_credit_amount, 2);
    }
}
