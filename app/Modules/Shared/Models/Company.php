<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use HasUuids;

    protected $table = 'companies';

    protected $fillable = [
        'user_id',
        'company_name',
        'nib',
        'npwp',
        'company_address',
        'pic_name',
        'pic_phone',
        'email',
        'industry',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function corporateCredit(): HasOne
    {
        return $this->hasOne(AdmCorporateCredit::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(AdmInvoice::class);
    }
}
