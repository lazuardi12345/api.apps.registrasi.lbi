<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    protected $table = 'student_profiles';

    protected $fillable = [
        'user_id',
        'legacy_profile_id',
        'full_name',
        'identity_number',
        'gender',
        'birth_place',
        'birth_date',
        'phone',
        'education',
        'occupation',
        'address',
        'photo_url',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
