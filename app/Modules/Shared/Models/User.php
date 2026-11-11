<?php

namespace App\Modules\Shared\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasRoles, HasUuids, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'user_type',
        'admin_role',
        'status',
        'two_factor_secret',
        'two_factor_enabled_at',
        'email_verified_at',
        'nik',
        'kyc_aml_status',
        'ktp_file_url',
        'business_type',
        'timezone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_enabled_at' => 'datetime',
            'ktp_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Helper checks untuk user type
    public function isAdmin(): bool
    {
        return $this->user_type === 'ADMIN_USER';
    }

    public function isOperator(): bool
    {
        return $this->user_type === 'OPERATOR_USER';
    }

    public function isCorporate(): bool
    {
        return $this->user_type === 'CORPORATE_USER';
    }

    public function isRetail(): bool
    {
        return $this->user_type === 'RETAIL_CUSTOMER';
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_enabled_at);
    }

    // Relations
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function operatorProfile(): HasOne
    {
        return $this->hasOne(OpsOperatorProfile::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(OpsCargoBooking::class, 'shipper_id');
    }
}
