<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningFormat extends Model
{
    use HasFactory;

    protected $table = 'learning_formats';

    protected $fillable = [
        'code',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(CourseSchedule::class, 'learning_format_id');
    }
}
