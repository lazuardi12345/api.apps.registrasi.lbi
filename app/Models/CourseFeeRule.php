<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseFeeRule extends Model
{
    protected $fillable = [
        'name',
        'fee_type',
        'division',
        'course_catalog_id',
        'course_level_id',
        'term_id',
        'course_schedule_id',
        'amount',
        'effective_from',
        'effective_until',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
    ];

    public function scopeEffective(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', now()));
    }

    public function courseCatalog(): BelongsTo
    {
        return $this->belongsTo(CourseCatalog::class);
    }

    public function courseLevel(): BelongsTo
    {
        return $this->belongsTo(CourseLevel::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function courseSchedule(): BelongsTo
    {
        return $this->belongsTo(CourseSchedule::class);
    }
}