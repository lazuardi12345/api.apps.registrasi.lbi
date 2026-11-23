<?php

namespace Tests\Feature;

use App\Models\CourseEnrollment;
use App\Models\PaymentConfirmation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminEnrollmentWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_ppp_is_limited_to_ppp_enrollments(): void
    {
        $admin = $this->createAdmin('admin_ppp');
        $pppEnrollment = $this->createEnrollment('ppp');
        $ppbEnrollment = $this->createEnrollment('ppb');

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/admin/enrollments?search=' . urlencode($pppEnrollment->student_email))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pppEnrollment->id);

        $this->getJson("/api/v1/admin/enrollments/{$ppbEnrollment->id}")
            ->assertNotFound();

        $this->putJson("/api/v1/admin/enrollments/{$ppbEnrollment->id}/status", ['status' => 'confirmed'])
            ->assertNotFound();
    }

    public function test_registration_and_course_stages_use_schedule_and_registration_payment(): void
    {
        $admin = $this->createAdmin('admin_ppp');
        $scheduleId = DB::table('course_schedules')->insertGetId([
            'program_name' => 'PPP Test Course',
            'division' => 'ppp',
            'is_active' => true,
            'is_open' => true,
            'capacity' => 10,
            'registered' => 0,
            'fee' => 1000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $readyForCourse = $this->createEnrollment('ppp', $scheduleId);
        $registrationOnly = $this->createEnrollment('ppp');
        PaymentConfirmation::create([
            'enrollment_id' => $readyForCourse->id,
            'student_name' => $readyForCourse->student_name,
            'student_email' => $readyForCourse->student_email,
            'bank_name' => 'Test Bank',
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'payment_for' => 'registration',
            'status' => 'verified',
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/admin/enrollments?workflow_stage=courses&search=' . urlencode($readyForCourse->student_email))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $readyForCourse->id);

        $this->getJson('/api/v1/admin/enrollments?workflow_stage=registrations&search=' . urlencode($registrationOnly->student_email))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $registrationOnly->id);
    }

    public function test_admin_enrollment_list_returns_pagination_metadata_for_requested_page(): void
    {
        $admin = $this->createAdmin('admin_ppp');
        $searchTerm = 'pagination-' . Str::uuid();
        $firstEnrollment = $this->createEnrollment('ppp');
        $secondEnrollment = $this->createEnrollment('ppp');
        $firstEnrollment->update(['course_name' => $searchTerm]);
        $secondEnrollment->update(['course_name' => $searchTerm]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/admin/enrollments?per_page=1&page=2&search=' . urlencode($searchTerm))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_admin_enrollment_list_rejects_invalid_page_size(): void
    {
        $admin = $this->createAdmin('admin_ppp');

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/admin/enrollments?per_page=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_relief_requested_is_normalized_and_synced_in_metadata(): void
    {
        $admin = $this->createAdmin('administrator');
        $enrollment = $this->createEnrollment('ppb');

        $this->actingAs($admin, 'api')
            ->putJson("/api/v1/admin/enrollments/{$enrollment->id}/workflow", [
                'relief_requested' => false,
                'relief_approved' => false,
            ])
            ->assertOk();

        $enrollment->refresh();
        $this->assertFalse($enrollment->metadata['relief_requested']);
        $this->assertFalse($enrollment->metadata['admin_workflow']['relief_requested']);
        $this->assertFalse($enrollment->metadata['admin_workflow']['relief_approved']);

        $this->putJson("/api/v1/admin/enrollments/{$enrollment->id}/workflow", [
            'relief_requested' => true,
        ])
            ->assertOk();

        $enrollment->refresh();
        $this->assertTrue($enrollment->metadata['relief_requested']);
        $this->assertTrue($enrollment->metadata['admin_workflow']['relief_requested']);
    }

    private function createAdmin(string $role): User
    {
        $suffix = Str::uuid();

        return User::create([
            'name' => 'Enrollment Test Admin',
            'email' => "enrollment-test-{$suffix}@example.test",
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function createEnrollment(string $division, ?int $scheduleId = null): CourseEnrollment
    {
        $suffix = Str::uuid();

        return CourseEnrollment::create([
            'division' => $division,
            'course_schedule_id' => $scheduleId,
            'student_name' => 'Enrollment Test Participant',
            'student_email' => "participant-{$suffix}@example.test",
            'student_phone' => '081234567890',
            'course_name' => 'Enrollment Test Course',
            'registration_track' => 'no_test',
            'registration_fee' => 100000,
            'tuition_fee' => 1000000,
            'status' => 'pending_payment',
        ]);
    }
}
