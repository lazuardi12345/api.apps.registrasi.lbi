<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use App\Models\CourseEnrollment;

class PlacementTestToken extends Model
{
    use HasFactory;

    protected $table = 'placement_test_tokens';

    protected $fillable = [
        'token_code',
        'test_type',
        'activation_start_date',
        'expiry_date',
        'licence_length_days',
        'is_used',
        'enrollment_id',
        'registration_number',
        'student_name',
        'student_email',
        'student_phone',
        'assigned_at',
        'email_sent_at',
        'notes',
    ];

    protected $casts = [
        'activation_start_date' => 'date',
        'expiry_date' => 'date',
        'licence_length_days' => 'integer',
        'is_used' => 'boolean',
        'assigned_at' => 'datetime',
        'email_sent_at' => 'datetime',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class, 'enrollment_id');
    }

    /**
     * Scope for available / unused tokens.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_used', false);
    }

    /**
     * Scope for used tokens.
     */
    public function scopeUsed($query)
    {
        return $query->where('is_used', true);
    }

    /**
     * Claim / assign the next available token to an enrollment in a thread-safe transaction.
     */
    public static function claimForEnrollment(CourseEnrollment $enrollment, string $testType = 'DPT'): ?self
    {
        return DB::transaction(function () use ($enrollment, $testType) {
            // Find the oldest available token and lock the row
            $token = self::where('test_type', $testType)
                ->where('is_used', false)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->first();

            if (! $token) {
                return null;
            }

            $token->update([
                'is_used' => true,
                'enrollment_id' => $enrollment->id,
                'registration_number' => $enrollment->registration_number ?? '#' . $enrollment->id,
                'student_name' => $enrollment->student_name,
                'student_email' => $enrollment->student_email,
                'student_phone' => $enrollment->student_phone,
                'assigned_at' => now(),
            ]);

            return $token;
        });
    }

    /**
     * Format response data based on whether token is used or not.
     */
    public function toResponseArray(): array
    {
        if ($this->is_used) {
            return [
                'id' => $this->id,
                'token_code' => $this->token_code,
                'activation_start_date' => $this->activation_start_date?->format('Y-m-d'),
                'expiry_date' => $this->expiry_date?->format('Y-m-d'),
                'licence_length_days' => $this->licence_length_days,
                'is_used' => true,
                'status' => 'digunakan',
                'id_registrasi' => $this->registration_number ?? ($this->enrollment_id ? '#' . $this->enrollment_id : null),
                'enrollment_id' => $this->enrollment_id,
                'nama_lengkap' => $this->student_name,
                'email' => $this->student_email,
                'nomor_hp' => $this->student_phone,
                'assigned_at' => $this->assigned_at?->toIso8601String(),
                'email_sent_at' => $this->email_sent_at?->toIso8601String(),
            ];
        }

        return [
            'id' => $this->id,
            'token_code' => $this->token_code,
            'activation_start_date' => $this->activation_start_date?->format('Y-m-d'),
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'licence_length_days' => $this->licence_length_days,
            'is_used' => false,
            'status' => 'belum_digunakan',
        ];
    }
}
