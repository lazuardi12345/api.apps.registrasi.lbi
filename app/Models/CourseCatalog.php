<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class CourseCatalog extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('landing:overview'));
        static::deleted(fn () => Cache::forget('landing:overview'));
    }

    protected $table = 'course_catalogs';

    protected $fillable = [
        'legacy_nid',
        'service_id',
        'title',
        'slug',
        'summary',
        'description',
        'division',
        'category',
        'level',
        'learning_outcomes',
        'target_audience',
        'instructor_info',
        'duration',
        'registration_url',
        'payment_steps',
        'image_url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'payment_steps' => 'array',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(CourseSchedule::class, 'course_catalog_id');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(CourseLevel::class, 'course_catalog_id')->orderBy('sort_order');
    }
}
