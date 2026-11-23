<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\LegacyDrupalPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginUserAction
{
    /**
     * Authenticate user credentials and return JWT token.
     */
    public function execute(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !$this->checkPassword($user, $password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        if (str_starts_with($user->password, '$S$')) {
            DB::table('users')->where('id', $user->id)->update([
                'password' => Hash::make($password),
                'updated_at' => now(),
            ]);
            $user->refresh();
        }

        // Auto-link any unlinked enrollments or payments submitted with this email
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
    }

    private function checkPassword(User $user, string $password): bool
    {
        if (str_starts_with($user->password, '$S$')) {
            return LegacyDrupalPassword::check($password, $user->password);
        }

        return Hash::check($password, $user->password);
    }
}
