<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Port extends Model
{
    protected $table = 'ports';

    // PK adalah kode string custom (mis. IATA/UN LOCODE), bukan auto-increment
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'city',
        'country_code',
        'port_type',
        'latitude',
        'longitude',
    ];

    public function originRoutes(): HasMany
    {
        return $this->hasMany(Route::class, 'origin_port_id');
    }

    public function destinationRoutes(): HasMany
    {
        return $this->hasMany(Route::class, 'destination_port_id');
    }
}
