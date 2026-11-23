<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Term extends Model
{
    protected $fillable = [
        'name',
        'program',
        'description',
        'academic_year',
        'start_date',
        'end_date',
        'registration_open',
        'registration_close',
        'is_active',
        'is_current',
        'sort_order',
    ];

    protected $casts = [
        'start_date'         => 'date',
        'end_date'           => 'date',
        'registration_open'  => 'date',
        'registration_close' => 'date',
        'is_active'          => 'boolean',
        'is_current'         => 'boolean',
        'sort_order'         => 'integer',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────

    public function schedules(): HasMany
    {
        return $this->hasMany(CourseSchedule::class, 'term_id');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForProgram($query, string $program)
    {
        return $query->where(function ($q) use ($program) {
            $q->where('program', $program)->orWhere('program', 'all');
        });
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    /**
     * True jika pendaftaran sedang dibuka berdasarkan tanggal.
     */
    public function isRegistrationOpen(): bool
    {
        $today = now()->toDateString();
        if ($this->registration_open && $today < $this->registration_open) return false;
        if ($this->registration_close && $today > $this->registration_close) return false;
        return true;
    }

    /**
     * Label display: "Term 1 2026 (PPB)"
     */
    public function getLabelAttribute(): string
    {
        $prog = strtoupper($this->program);
        $prog = $prog === 'ALL' ? 'Semua Program' : $prog;
        return "{$this->name} [{$prog}]";
    }
}
