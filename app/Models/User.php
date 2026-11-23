<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    public const ROLES = [
        'administrator',
        'admin',
        'admin_ppb',
        'admin_bipa',
        'admin_ppp',
        'finance',
        'marketing',
        'content_writer',
        'reviewer',
        'teacher',
        'student',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'legacy_uid',
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(StudentProfile::class, 'user_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'user_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class, 'student_email', 'email');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentConfirmation::class, 'user_id');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    /**
     * Check if the user possesses one of the specified roles.
     */
    public function hasRole(string|array ...$roles): bool
    {
        $flattened = collect($roles)->flatten()->all();

        // Administrator always has access
        if ($this->role === 'administrator') {
            return true;
        }

        // Check primary role
        if (in_array($this->role, $flattened, true)) {
            return true;
        }

        // Check relation roles
        return $this->roles()->whereIn('role_name', $flattened)->exists();
    }
}
