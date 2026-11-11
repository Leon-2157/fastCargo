<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;

class AdmSystemSetting extends Model
{
    protected $table = 'adm_system_settings';

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    // Ambil nilai setting berdasarkan key; kembalikan $default bila tidak ditemukan
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }
}
