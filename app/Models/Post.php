<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Post extends Model
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

    protected $table = 'posts';

    protected $fillable = [
        'legacy_nid',
        'title',
        'slug',
        'category',
        'body',
        'summary',
        'featured_image',
        'author_id',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', 1)->latest();
    }
}
