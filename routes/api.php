<?php

use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminEnrollmentController;
use App\Http\Controllers\Api\Admin\CourseFeeRuleController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\EnrollmentApiController;
use App\Http\Controllers\Api\Finance\FinancePaymentController;
use App\Http\Controllers\Api\Finance\FinanceReportController;
use App\Http\Controllers\Api\LandingApiController;
use App\Http\Controllers\Api\Marketing\MarketingBannerController;
use App\Http\Controllers\Api\Marketing\MarketingCourseScheduleController;
use App\Http\Controllers\Api\Marketing\MarketingCourseCatalogController;
use App\Http\Controllers\Api\Marketing\MarketingPartnerController;
use App\Http\Controllers\Api\Marketing\MarketingPostController;
use App\Http\Controllers\Api\Marketing\MarketingSliderController;
use App\Http\Controllers\Api\PaymentApiController;
use App\Http\Controllers\Api\PostApiController;
use App\Http\Controllers\Api\PublicCourseFeeController;
use App\Http\Controllers\Api\CourseScheduleApiController;
use App\Http\Controllers\Api\ServiceApiController;
use App\Http\Controllers\Api\Marketing\MarketingServiceController;
use App\Http\Controllers\Api\Student\StudentPortalController;
use App\Http\Controllers\Api\Student\StudentDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - V1 (Clean Architecture & Role-Based Modular Design)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // 0. HEALTH CHECK
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'database' => 'connected',
            'app' => 'LBI FIB UI - REST API',
        ]);
    });

    // 1. AUTHENTICATION (Public)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthApiController::class, 'register']);
        Route::post('/login', [AuthApiController::class, 'login']);

        Route::middleware('auth:api')->group(function () {
            Route::get('/me', [AuthApiController::class, 'me']);
            Route::post('/logout', [AuthApiController::class, 'logout']);
        });
    });

    // 2. PUBLIC / LANDING & CATALOG (No Auth Required)
    Route::prefix('public')->group(function () {
        // Landing Page Aggregations
        Route::get('/landing/overview', [LandingApiController::class, 'overview']);
        Route::get('/landing/sliders', [LandingApiController::class, 'sliders']);
        Route::get('/landing/banners', [LandingApiController::class, 'banners']);
        Route::get('/landing/partners', [LandingApiController::class, 'partners']);
        // Programs & Services Catalog
        Route::get('/services/nav-menu', [ServiceApiController::class, 'navMenu']);
        Route::get('/services', [ServiceApiController::class, 'index']);
        Route::get('/services/{slug}', [ServiceApiController::class, 'show']);
        Route::get('/courses/{slug}', [\App\Http\Controllers\Api\CourseCatalogApiController::class, 'show']);

        // Blog & News Posts
        Route::get('/posts', [PostApiController::class, 'index']);
        Route::get('/posts/{slug}', [PostApiController::class, 'show']);

        // Course Schedules (public - no auth, for landing page)
        // IMPORTANT: specific routes must come BEFORE wildcard {id} route
        Route::get('/schedules/master-options', [CourseScheduleApiController::class, 'masterOptions']);
        Route::get('/schedules/course/{slug}', [CourseScheduleApiController::class, 'showBySlug']);
        Route::get('/schedules', [CourseScheduleApiController::class, 'index']);
        Route::get('/schedules/{id}', [CourseScheduleApiController::class, 'show']);
        // Course Catalogs & Levels
        Route::get('/course-catalogs', [MarketingCourseCatalogController::class, 'index']);
        Route::get('/courses/{id}/levels', [CourseScheduleApiController::class, 'levelsByCatalog']);

        // Term Master Data (public - for registration form to display term info)
        Route::get('/terms', [\App\Http\Controllers\Api\Admin\AdminTermController::class, 'publicIndex']);
        Route::get('/terms/current', function (\Illuminate\Http\Request $req) {
            return app(\App\Http\Controllers\Api\Admin\AdminTermController::class)->publicIndex($req);
        });
        Route::get('/course-fees', [PublicCourseFeeController::class, 'show']);

        // Public Course Registration per Program
        Route::post('/enrollments', [EnrollmentApiController::class, 'store']);
        Route::post('/enrollments/program/{program}', [EnrollmentApiController::class, 'storeForProgram']);
        Route::post('/enrollments/ppb', fn(\App\Http\Requests\Enrollment\StoreEnrollmentRequest $req, \App\Actions\Enrollment\CreateEnrollmentAction $action, \App\Actions\Fees\ResolveCourseFeeAction $fees) => app(EnrollmentApiController::class)->storeForProgram('ppb', $req, $action, $fees));
        Route::post('/enrollments/bipa', fn(\App\Http\Requests\Enrollment\StoreEnrollmentRequest $req, \App\Actions\Enrollment\CreateEnrollmentAction $action, \App\Actions\Fees\ResolveCourseFeeAction $fees) => app(EnrollmentApiController::class)->storeForProgram('bipa', $req, $action, $fees));
        Route::post('/enrollments/ppp', fn(\App\Http\Requests\Enrollment\StoreEnrollmentRequest $req, \App\Actions\Enrollment\CreateEnrollmentAction $action, \App\Actions\Fees\ResolveCourseFeeAction $fees) => app(EnrollmentApiController::class)->storeForProgram('ppp', $req, $action, $fees));
        Route::post('/enrollments/testing', [EnrollmentApiController::class, 'storeForLanguageTest']);
        Route::get('/enrollments/{id}', [EnrollmentApiController::class, 'show']);

        // Public Payment Confirmation Submission & Check
        Route::post('/payments/confirm', [PaymentApiController::class, 'confirm']);
    Route::post('/payments/xendit/simulate', [PaymentApiController::class, 'simulateXendit']);
    Route::post('/payments/bni-va/simulate', [PaymentApiController::class, 'simulateBniVa']);
        Route::post('/payments/xendit/simulate', [PaymentApiController::class, 'simulateXendit']);
        Route::post('/payments/bni-va/simulate', [PaymentApiController::class, 'simulateBniVa']);
        // Program division shortcuts
        Route::get('/bipa', fn(\App\Actions\Programs\GetServiceBySlugAction $action) => app(ServiceApiController::class)->show('bipa', $action));
        Route::get('/ppb', fn(\App\Actions\Programs\GetServiceBySlugAction $action) => app(ServiceApiController::class)->show('ppb', $action));
        Route::get('/ppp', fn(\App\Actions\Programs\GetServiceBySlugAction $action) => app(ServiceApiController::class)->show('ppp', $action));
    });

    // Also support root shortcuts for compatibility
    Route::get('/landing/overview', [LandingApiController::class, 'overview']);
    Route::get('/bipa', fn(\App\Actions\Programs\GetServiceBySlugAction $action) => app(ServiceApiController::class)->show('bipa', $action));
    Route::get('/ppb', fn(\App\Actions\Programs\GetServiceBySlugAction $action) => app(ServiceApiController::class)->show('ppb', $action));
    Route::get('/ppp', fn(\App\Actions\Programs\GetServiceBySlugAction $action) => app(ServiceApiController::class)->show('ppp', $action));
    Route::get('/services', [ServiceApiController::class, 'index']);
    Route::get('/services/{slug}', [ServiceApiController::class, 'show']);
    Route::get('/course-catalogs', [MarketingCourseCatalogController::class, 'index']);
    Route::get('/schedules/master-options', [CourseScheduleApiController::class, 'masterOptions']);
    Route::get('/posts', [PostApiController::class, 'index']);
    Route::get('/posts/{slug}', [PostApiController::class, 'show']);
    Route::post('/enrollments', [EnrollmentApiController::class, 'store']);
    Route::post('/enrollments/program/{program}', [EnrollmentApiController::class, 'storeForProgram']);
    Route::post('/payments/confirm', [PaymentApiController::class, 'confirm']);
    Route::post('/payments/xendit/simulate', [PaymentApiController::class, 'simulateXendit']);
    Route::post('/payments/bni-va/simulate', [PaymentApiController::class, 'simulateBniVa']);

    // 3. STUDENT PORTAL (Authenticated Student / User)
    Route::middleware(['auth:api'])->prefix('student')->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index']);
        Route::get('/my-enrollments', [StudentPortalController::class, 'myEnrollments']);
        Route::get('/my-payments', [StudentPortalController::class, 'myPayments']);
        Route::get('/enrollments/{id}/available-schedules', [StudentPortalController::class, 'availableSchedules']);
        Route::post('/enrollments/{id}/select-schedule', [StudentPortalController::class, 'selectSchedule']);
        Route::put('/profile', [StudentPortalController::class, 'updateProfile']);
        // Fase 3D - Workflow
        Route::post('/enrollments/{id}/postpone', [StudentPortalController::class, 'postpone']);
        Route::post('/enrollments/{id}/continue', [StudentPortalController::class, 'continue']);
        Route::post('/enrollments/{id}/transfer', [StudentPortalController::class, 'transfer']);
    });

    // 4. MARKETING ROLE (Protected: content_writer, marketing, administrator, admin_ppb, admin_bipa, admin_ppp)
    Route::middleware(['auth:api', 'role:content_writer,marketing,administrator,admin_ppb,admin_bipa,admin_ppp'])->prefix('marketing')->group(function () {
        // Slider Management
        Route::apiResource('sliders', MarketingSliderController::class);

        // Banner Management
        Route::apiResource('banners', MarketingBannerController::class);

        // Post / Blog Management
        Route::apiResource('posts', MarketingPostController::class);

        // Partner / Client Management
        Route::apiResource('partners', MarketingPartnerController::class);
        Route::apiResource('courses', MarketingCourseScheduleController::class)->parameters(['courses' => 'courseSchedule']);
        Route::apiResource('course-catalogs', MarketingCourseCatalogController::class)->parameters(['course-catalogs' => 'courseCatalog']);
        Route::post('/content-images', [MarketingServiceController::class, 'uploadContentImage']);
        Route::get('/services', [MarketingServiceController::class, 'index']);
        Route::get('/services/{service}', [MarketingServiceController::class, 'show']);
        Route::put('/services/{service}', [MarketingServiceController::class, 'update']);
    });

    // 5. FINANCE ROLE (Protected: finance, administrator)
    Route::middleware(['auth:api', 'role:finance,administrator'])->prefix('finance')->group(function () {
        Route::get('/payments', [FinancePaymentController::class, 'index']);
        Route::get('/payments/{payment}', [FinancePaymentController::class, 'show']);
        Route::post('/payments/{payment}/verify', [FinancePaymentController::class, 'verify']);
        Route::post('/payments/{payment}/reject', [FinancePaymentController::class, 'reject']);
        Route::put('/enrollments/{enrollment}/payment-injected-date', [AdminEnrollmentController::class, 'updatePaymentInjectedDate']);
        Route::get('/reports', [FinanceReportController::class, 'index']);
    });

    // 6. ADMIN ROLE (Protected: administrator, admin_ppb, admin_bipa, admin_ppp)
    Route::middleware(['auth:api', 'role:administrator,admin_ppb,admin_bipa,admin_ppp'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/analytics/enrollments', [AdminDashboardController::class, 'enrollmentAnalytics']);
        Route::apiResource('course-fees', CourseFeeRuleController::class)->parameters(['course-fees' => 'courseFeeRule']);

        // Enrollment Management
        Route::get('/enrollments', [AdminEnrollmentController::class, 'index']);
        Route::get('/enrollments/{enrollment}', [AdminEnrollmentController::class, 'show']);
        Route::put('/enrollments/{enrollment}/status', [AdminEnrollmentController::class, 'updateStatus']);
        Route::put('/enrollments/{enrollment}/workflow', [AdminEnrollmentController::class, 'updateWorkflow']);
        Route::post('/enrollments/{enrollment}/inject-payment', [AdminEnrollmentController::class, 'injectPayment']);
        Route::get('/enrollments/{enrollment}/documents/{document}', [AdminEnrollmentController::class, 'downloadIdentityDocument']);

        // Course Schedule Management (Admin Akademik)
        // IMPORTANT: master-options MUST be before apiResource to avoid wildcard capture
        Route::get('/schedules/master-options', [\App\Http\Controllers\Api\Marketing\MarketingCourseScheduleController::class, 'masterOptions']);
        Route::apiResource('schedules', \App\Http\Controllers\Api\Marketing\MarketingCourseScheduleController::class)
            ->parameters(['schedules' => 'courseSchedule'])
            ->except(['show']);
        Route::get('/schedules/{courseSchedule}', [\App\Http\Controllers\Api\Marketing\MarketingCourseScheduleController::class, 'show']);
        Route::get('/course-catalogs', [MarketingCourseCatalogController::class, 'index']);

        // Term Master Data Management (Admin only)
        Route::apiResource('terms', \App\Http\Controllers\Api\Admin\AdminTermController::class);

        // Placement Test Tokens Management (PPB Inggris / DPT)
        Route::get('/placement-test-tokens', [\App\Http\Controllers\Api\Admin\AdminPlacementTestTokenController::class, 'index']);
        Route::post('/placement-test-tokens/{token}/send-email', [\App\Http\Controllers\Api\Admin\AdminPlacementTestTokenController::class, 'sendEmail']);

        // User & Role Management
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::put('/users/{user}/role', [AdminUserController::class, 'updateRole']);
    });
});
