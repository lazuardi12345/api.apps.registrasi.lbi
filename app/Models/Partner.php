<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Partner extends Model
{
    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('landing:overview');
        });

        static::deleted(function () {
            Cache::forget('landing:overview');
        });
    }

    protected $table = 'partners';

    protected $fillable = [
        'legacy_nid',
        'name',
        'logo_url',
        'website_url',
    ];
}
