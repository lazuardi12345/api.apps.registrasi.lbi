<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseSchedule extends Model
{
    protected $table = 'course_schedules';

    protected $fillable = [
        'service_id',
        'course_catalog_id',
        'course_level_id',
        'schedule_day_id',
        'schedule_time_id',
        'learning_format_id',
        'class_location_id',
        'term_id',
        'program_name',
        'course_slug',
        'course_summary',
        'division',
        'batch',
        'level',
        'format',
        'start_date',
        'end_date',
        'schedule_text',
        'capacity',
        'registered',
        'fee',
        'fee_display',
        'registration_open',
        'registration_close',
        'location',
        'notes',
        'is_active',
        'is_open',
        'sort_order',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'is_open'           => 'boolean',
        'start_date'        => 'date',
        'end_date'          => 'date',
        'registration_open' => 'date',
        'registration_close'=> 'date',
        'fee'               => 'decimal:2',
        'capacity'          => 'integer',
        'registered'        => 'integer',
        'sort_order'        => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function courseCatalog(): BelongsTo
    {
        return $this->belongsTo(CourseCatalog::class, 'course_catalog_id');
    }

    public function courseLevel(): BelongsTo
    {
        return $this->belongsTo(CourseLevel::class, 'course_level_id');
    }

    public function scheduleDay(): BelongsTo
    {
        return $this->belongsTo(ScheduleDay::class, 'schedule_day_id');
    }

    public function scheduleTime(): BelongsTo
    {
        return $this->belongsTo(ScheduleTime::class, 'schedule_time_id');
    }

    public function learningFormat(): BelongsTo
    {
        return $this->belongsTo(LearningFormat::class, 'learning_format_id');
    }

    public function classLocation(): BelongsTo
    {
        return $this->belongsTo(ClassLocation::class, 'class_location_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    // ── Scopes ──────────────────────────────────────────────────
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_open', true)
            ->whereColumn('registered', '<', 'capacity')
            ->where(function (Builder $query) {
                $query->whereNull('registration_open')->orWhereDate('registration_open', '<=', now());
            })
            ->where(function (Builder $query) {
                $query->whereNull('registration_close')->orWhereDate('registration_close', '>=', now());
            });
    }

    public function scopeByDivision(Builder $query, string $division): Builder
    {
        return $query->where('division', $division);
    }

    // ── Accessors ────────────────────────────────────────────────
    public function getSlotsAvailableAttribute(): int
    {
        return max(0, $this->capacity - $this->registered);
    }

    public function getIsFullAttribute(): bool
    {
        return $this->registered >= $this->capacity;
    }

    public function getFormattedFeeAttribute(): string
    {
        if ($this->fee_display) return $this->fee_display;
        if ($this->fee == 0) return 'Gratis';
        return 'Rp ' . number_format($this->fee, 0, ',', '.');
    }

    public function setFormatAttribute($value): void
    {
        $val = strtolower((string)$value);
        if ($val === 'online') {
            $this->attributes['format'] = 'daring';
        } elseif ($val === 'offline') {
            $this->attributes['format'] = 'luring';
        } else {
            $this->attributes['format'] = $val ?: 'daring';
        }
    }
}
