<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class RoleBasedApiTest extends TestCase
{
    /**
     * Test public health check.
     */
    public function test_health_check_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    /**
     * Test public landing overview returns correct schema.
     */
    public function test_public_landing_overview(): void
    {
        $response = $this->getJson('/api/v1/public/landing/overview');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'sliders',
                    'banners',
                    'partners',
                    'services',
                    'latest_posts',
                ],
            ]);
    }

    /**
     * Test unauthenticated access to protected role route is rejected.
     */
    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/v1/finance/reports');
        $response->assertStatus(401);
    }

    /**
     * Test student role cannot access finance route (403 forbidden).
     */
    public function test_student_cannot_access_finance_routes(): void
    {
        $student = User::where('role', 'student')->first();
        if ($student) {
            $this->actingAs($student, 'api');
            $response = $this->getJson('/api/v1/finance/reports');
            $response->assertStatus(403);
        }
    }

    /**
     * Test student role can access student portal.
     */
    public function test_student_can_access_student_portal(): void
    {
        $student = User::where('role', 'student')->first();
        if ($student) {
            $this->actingAs($student, 'api');
            $response = $this->getJson('/api/v1/student/my-enrollments');
            $response->assertStatus(200);
        }
    }

    /**
     * Test admin can access admin dashboard and finance reports.
     */
    public function test_admin_can_access_admin_and_finance_routes(): void
    {
        $admin = User::where('role', 'administrator')->first();
        if ($admin) {
            $this->actingAs($admin, 'api');

            $adminResp = $this->getJson('/api/v1/admin/dashboard');
            $adminResp->assertStatus(200);

            $financeResp = $this->getJson('/api/v1/finance/reports');
            $financeResp->assertStatus(200);
        }
    }
}
