<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CourseEnrollment extends Model
{
    protected $table = 'course_enrollments';

    protected $fillable = [
        'registration_number',
        'legacy_sid',
        'webform_id',
        'legacy_uid',
        'user_id',
        'course_catalog_id',
        'course_level_id',
        'course_schedule_id',
        'term_id',
        'registration_source',
        'division',
        'student_type',
        'registration_track',
        'term_period',
        'student_name',
        'student_email',
        'student_phone',
        'course_name',
        'level_name',
        'campus_location',
        'learning_mode',
        'schedule_days',
        'schedule_time',
        'discount_percent',
        'registration_fee',
        'tuition_fee',
        'course_fee_rule_id',
        'postpone_reason',
        'status',
        'completion_status',
        'payment_status',
        'submitted_at',
        'metadata',
        'sar_document_path',
        'certificate_path',
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'registration_fee' => 'decimal:2',
        'submitted_at'     => 'datetime',
        'metadata'         => 'array',
    ];

    /**
     * Auto-generate nomor pendaftaran unik saat enrollment baru dibuat.
     * Format: {DIVISI}-{TAHUN}-{5-digit-id}
     * Contoh: PPB-2026-00001, PPP-2026-00001, BIPA-2026-00001
     */
    protected static function booted(): void
    {
        static::created(function (CourseEnrollment $enrollment) {
            if (! $enrollment->registration_number) {
                $prefix = match (strtolower($enrollment->division ?? '')) {
                    'ppb'         => 'PPB',
                    'ppb_foreign' => 'PPBF',
                    'testing'     => 'TEST',
                    'ppp'         => 'PPP',
                    'bipa'        => 'BIPA',
                    default       => 'REG',
                };
                $year   = now()->year;
                $number = str_pad($enrollment->id, 5, '0', STR_PAD_LEFT);
                $enrollment->updateQuietly([
                    'registration_number' => "{$prefix}-{$year}-{$number}",
                ]);
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Relations                                                           */
    /* ------------------------------------------------------------------ */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_email', 'email');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentConfirmation::class, 'enrollment_id');
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(PaymentConfirmation::class, 'enrollment_id')
                    ->latestOfMany('created_at');
    }

    public function courseCatalog(): BelongsTo
    {
        return $this->belongsTo(CourseCatalog::class, 'course_catalog_id');
    }

    public function courseLevel(): BelongsTo
    {
        return $this->belongsTo(CourseLevel::class, 'course_level_id');
    }

    public function courseFeeRule(): BelongsTo
    {
        return $this->belongsTo(CourseFeeRule::class, 'course_fee_rule_id');
    }

    public function courseSchedule(): BelongsTo
    {
        return $this->belongsTo(CourseSchedule::class, 'course_schedule_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }
}
