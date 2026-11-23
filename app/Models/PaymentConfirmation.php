<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentConfirmation extends Model
{
    protected $table = 'payment_confirmations';

    protected $fillable = [
        'legacy_sid',
        'enrollment_id',
        'user_id',
        'student_name',
        'student_email',
        'course_name',
        'payment_for',
        'bank_name',
        'account_number',
        'account_holder',
        'amount',
        'payment_date',
        'proof_file',
        'status',
        'verified_at',
        'verified_note',
        'verified_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'verified_at' => 'datetime',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class, 'enrollment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
