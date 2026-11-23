<?php

namespace Tests\Feature;

use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentDashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_returns_only_the_authenticated_students_enrollments(): void
    {
        $student = $this->createStudent();
        $ownEnrollment = $this->createEnrollment($student);
        $otherStudent = $this->createStudent();
        $this->createEnrollment($otherStudent);

        $this->actingAs($student, 'api')
            ->getJson('/api/v1/student/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonCount(1, 'data.enrollments')
            ->assertJsonPath('data.enrollments.0.id', $ownEnrollment->id)
            ->assertJsonPath('data.enrollments.0.student_email', $student->email);
    }

    public function test_student_enrollment_list_returns_requested_page_and_only_own_records(): void
    {
        $student = $this->createStudent();
        $otherStudent = $this->createStudent();

        for ($index = 0; $index < 6; $index++) {
            $this->createEnrollment($student);
        }
        $this->createEnrollment($otherStudent);

        $this->actingAs($student, 'api')
            ->getJson('/api/v1/student/my-enrollments?page=2&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 6)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.from', 3)
            ->assertJsonPath('meta.to', 4)
            ->assertJsonPath('data.0.student_email', $student->email)
            ->assertJsonPath('data.1.student_email', $student->email);
    }

    public function test_student_enrollment_list_rejects_invalid_page_size(): void
    {
        $student = $this->createStudent();

        $this->actingAs($student, 'api')
            ->getJson('/api/v1/student/my-enrollments?per_page=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    private function createStudent(): User
    {
        $suffix = Str::uuid();

        return User::create([
            'name' => 'Dashboard Test Student',
            'email' => "dashboard-test-{$suffix}@example.test",
            'password' => 'password',
            'role' => 'student',
        ]);
    }

    private function createEnrollment(User $student): CourseEnrollment
    {
        return CourseEnrollment::create([
            'user_id' => $student->id,
            'division' => 'ppb',
            'student_name' => $student->name,
            'student_email' => $student->email,
            'student_phone' => '081234567890',
            'course_name' => 'Dashboard Test Course',
            'registration_track' => 'no_test',
            'registration_fee' => 100000,
            'status' => 'pending_payment',
        ]);
    }
}
