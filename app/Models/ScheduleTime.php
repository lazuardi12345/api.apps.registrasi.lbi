<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleTime extends Model
{
    use HasFactory;

    protected $table = 'schedule_times';

    protected $fillable = [
        'time_range',
        'start_time',
        'end_time',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(CourseSchedule::class, 'schedule_time_id');
    }
}
