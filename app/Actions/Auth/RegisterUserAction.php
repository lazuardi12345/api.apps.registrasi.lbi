<?php

namespace App\Actions\Auth;

use App\Models\StudentProfile;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class RegisterUserAction
{
    /**
     * Register a new user and create their student profile.
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'student',
                'status' => true,
            ]);

            UserRole::create([
                'user_id' => $user->id,
                'role_name' => 'student',
            ]);

            StudentProfile::create([
                'user_id' => $user->id,
                'full_name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'identity_number' => $data['identity_number'] ?? null,
                'gender' => $data['gender'] ?? null,
                'birth_place' => $data['birth_place'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'address' => $data['address'] ?? null,
                'education' => $data['education'] ?? null,
                'occupation' => $data['occupation'] ?? null,
            ]);

            // Auto-link any previous enrollments or payments submitted with this email
            \App\Models\CourseEnrollment::where('student_email', $user->email)
                ->whereNull('user_id')
                ->update(['user_id' => $user->id]);

            \App\Models\PaymentConfirmation::where('student_email', $user->email)
                ->whereNull('user_id')
                ->update(['user_id' => $user->id]);

            $token = JWTAuth::fromUser($user);

            return [
                'user' => $user->load('profile', 'roles'),
                'token' => $token,
                'token_type' => 'Bearer',
            ];
        });
    }
}
