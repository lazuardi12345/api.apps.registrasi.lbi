<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
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

    protected $table = 'services';

    protected $fillable = [
        'legacy_nid',
        'parent_service_id',
        'title',
        'slug',
        'body',
        'summary',
        'video_description',
        'icon',
        'menu_category',
        'menu_order',
        'image_url',
        'video_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'menu_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    public function courseSchedules(): HasMany
    {
        return $this->hasMany(CourseSchedule::class, 'service_id');
    }

    public function courseCatalogs(): HasMany
    {
        return $this->hasMany(CourseCatalog::class, 'service_id');
    }

    public function parentService(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_service_id');
    }

    public function childServices(): HasMany
    {
        return $this->hasMany(self::class, 'parent_service_id');
    }
}
