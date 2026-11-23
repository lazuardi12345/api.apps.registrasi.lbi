<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LanguageTestRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_language_test_catalogs_appear_in_the_test_language_nav_menu(): void
    {
        $serviceId = $this->createLanguageTestService();
        $catalogId = $this->createLanguageTestCatalog($serviceId);

        $response = $this->getJson('/api/v1/public/services/nav-menu')->assertOk();
        $categories = collect($response->json('data'));
        $testMenu = $categories->firstWhere('label', 'Tes Bahasa');

        $this->assertNotNull($testMenu);
        $this->assertSame('language-test-' . $catalogId, $testMenu['items'][0]['slug']);
        $this->assertSame('Informasi Pendaftaran', end($testMenu['items'])['title']);
        $testTitles = collect($testMenu['items'])->pluck('title')->all();
        $this->assertContains(
            'TOEFL ITP (Institutional Testing Program) Paper Based (On Site)',
            $testTitles
        );
        $this->assertNotContains(
            'TIBA (Indonesian as a Foreign Language Test)',
            $testTitles
        );

        $courseMenu = $categories->firstWhere('label', 'Kursus Bahasa');
        $placementMenu = collect($courseMenu['items'])->firstWhere('id', 'placement-test');
        $this->assertSame('Tes Penempatan', $placementMenu['title']);
        $ieltsPreparation = collect($placementMenu['children'])
            ->firstWhere('title', 'IELTS Preparation');
        $this->assertNotNull($ieltsPreparation);
        $this->assertSame(
            '/courses/catalog/ielts-preparation',
            $ieltsPreparation['href']
        );
        $englishMenu = collect($courseMenu['items'])->firstWhere('id', 'english');
        $this->assertNotContains(
            'ielts-preparation',
            collect($englishMenu['children'])->pluck('slug')->all()
        );
    }

    public function test_language_test_registration_is_saved_and_visible_to_ppb_admins(): void
    {
        $serviceId = $this->createLanguageTestService();
        $catalogId = $this->createLanguageTestCatalog($serviceId);
        DB::table('course_fee_rules')->insert([
            'name' => 'Language test registration fee',
            'fee_type' => 'registration',
            'division' => 'testing',
            'course_catalog_id' => $catalogId,
            'amount' => 175000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $email = 'language-test-' . Str::uuid() . '@example.test';

        $registration = $this->postJson('/api/v1/public/enrollments/testing', [
            'course_catalog_id' => $catalogId,
            'course_name' => 'Client-supplied title',
            'student_name' => 'Test Participant',
            'student_email' => $email,
            'student_phone' => '081234567890',
            'metadata' => ['test_location' => 'depok'],
        ])->assertCreated()
            ->assertJsonPath('data.division', 'testing')
            ->assertJsonPath('data.registration_track', 'language_test')
            ->assertJsonPath('data.course_name', 'Language Test ' . $catalogId)
            ->assertJsonPath('data.registration_fee', 175000);

        $admin = User::create([
            'name' => 'Language Test Admin',
            'email' => 'language-test-admin-' . Str::uuid() . '@example.test',
            'password' => 'password',
            'role' => 'admin_ppb',
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/admin/enrollments?division=testing&search=' . urlencode($email))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $registration->json('data.id'));

        $this->getJson('/api/v1/admin/course-fees')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Language test registration fee']);

        $pppAdmin = User::create([
            'name' => 'Unrelated Program Admin',
            'email' => 'unrelated-admin-' . Str::uuid() . '@example.test',
            'password' => 'password',
            'role' => 'admin_ppp',
        ]);
        $this->actingAs($pppAdmin, 'api')
            ->getJson('/api/v1/admin/enrollments?division=testing&search=' . urlencode($email))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function createLanguageTestService(): int
    {
        return DB::table('services')->insertGetId([
            'title' => 'Language test service',
            'slug' => 'language-test-service-' . Str::uuid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createLanguageTestCatalog(int $serviceId): int
    {
        $catalogId = DB::table('course_catalogs')->insertGetId([
            'service_id' => $serviceId,
            'title' => 'Language Test',
            'slug' => 'language-test-' . Str::uuid(),
            'division' => 'testing',
            'category' => 'language_test',
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('course_catalogs')->where('id', $catalogId)->update([
            'title' => 'Language Test ' . $catalogId,
            'slug' => 'language-test-' . $catalogId,
        ]);

        return $catalogId;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $serviceId = $this->createLanguageTestService();
        DB::table('course_catalogs')->insert([
            'service_id' => $serviceId,
            'title' => 'IELTS Preparation',
            'slug' => 'ielts-preparation',
            'division' => 'ppb_english',
            'category' => 'test_preparation',
            'is_active' => true,
            'sort_order' => 810,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
