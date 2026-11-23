<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseLevel extends Model
{
    use HasFactory;

    protected $table = 'course_levels';

    protected $fillable = [
        'course_catalog_id',
        'name',
        'code',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function courseCatalog(): BelongsTo
    {
        return $this->belongsTo(CourseCatalog::class, 'course_catalog_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(CourseSchedule::class, 'course_level_id');
    }
}
