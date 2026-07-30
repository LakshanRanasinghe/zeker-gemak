<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DhlSetting extends Model
{
    protected $fillable = [
        'configuration',
    ];

    protected $hidden = [
        'configuration',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'encrypted:array',
        ];
    }

    public static function resolved(): array
    {
        return array_replace_recursive(
            (array) config('services.zeker_gemak_dhl', []),
            (array) static::query()->first()?->configuration,
        );
    }
}
