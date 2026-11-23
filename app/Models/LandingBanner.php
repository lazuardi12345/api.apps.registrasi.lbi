<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class LandingBanner extends Model
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

    protected $table = 'landing_banners';

    protected $fillable = [
        'legacy_id',
        'title',
        'category',
        'description',
        'image_url',
        'link_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1)->latest();
    }
}
