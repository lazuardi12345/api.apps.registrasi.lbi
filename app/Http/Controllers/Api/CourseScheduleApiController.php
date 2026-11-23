<?php

namespace App\Http\Controllers\Api;

use App\Actions\Public\GetCourseSchedulesAction;
use App\Http\Resources\CourseScheduleResource;
use App\Models\CourseSchedule;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseScheduleApiController extends BaseApiController
{
    use ApiResponse;

    /**
     * GET /api/v1/public/schedules
     *
     * Query params:
     *   ?division=bipa|ppb_english|ppp
     *   ?open=1   (only open for registration)
     */
    public function index(Request $request, GetCourseSchedulesAction $action): JsonResponse
    {
        $division = $request->query('division');
        $openOnly = $request->boolean('open', false);
        $serviceId = $request->integer('service_id') ?: null;
        $termId = $request->integer('term_id') ?: null;

        $schedules = $action->execute($division, $openOnly, $serviceId, $termId);

        return $this->sendSuccess(
            CourseScheduleResource::collection($schedules),
            'Course schedules retrieved successfully'
        );
    }

    /**
     * GET /api/v1/public/schedules/{id}
     */
    public function show(int $id): JsonResponse
    {
        $schedule = CourseSchedule::active()->with(['service', 'courseCatalog'])->findOrFail($id);

        return $this->sendSuccess(
            new CourseScheduleResource($schedule),
            'Schedule detail retrieved'
        );
    }

    public function showBySlug(string $slug): JsonResponse
    {
        $schedule = CourseSchedule::active()->with(['service', 'courseCatalog'])->where('course_slug', $slug)->firstOrFail();

        return $this->sendSuccess(
            new CourseScheduleResource($schedule),
            'Course detail retrieved'
        );
    }

    /**
     * GET /api/v1/public/schedules/master-options
     * Return standard days, times, locations, formats, divisions, and level presets from Backend
     */
    public function masterOptions(): JsonResponse
    {
        $dbDays = \App\Models\ScheduleDay::where('is_active', true)->orderBy('sort_order')->get();
        $dbTimes = \App\Models\ScheduleTime::where('is_active', true)->orderBy('sort_order')->get();
        $dbFormats = \App\Models\LearningFormat::where('is_active', true)->orderBy('sort_order')->get();
        $dbLocations = \App\Models\ClassLocation::where('is_active', true)->orderBy('sort_order')->get();
        $dbTerms = \App\Models\Term::where('is_active', true)->orderBy('sort_order')->get();

        return $this->sendSuccess([
            // Formats in standard value/label format
            'formats' => $dbFormats->map(fn($f) => ['value' => $f->code, 'label' => $f->name, 'id' => $f->id]),
            'days' => $dbDays->pluck('name')->toArray(),
            'times' => $dbTimes->pluck('time_range')->toArray(),
            'locations' => $dbLocations->map(fn($l) => $l->name . ($l->building ? " ({$l->building})" : ''))->toArray(),

            // Master object lists for modern relational select
            'master_days' => $dbDays->map(fn($d) => ['id' => $d->id, 'name' => $d->name]),
            'master_times' => $dbTimes->map(fn($t) => ['id' => $t->id, 'time_range' => $t->time_range, 'start_time' => $t->start_time, 'end_time' => $t->end_time]),
            'master_formats' => $dbFormats->map(fn($f) => ['id' => $f->id, 'code' => $f->code, 'name' => $f->name]),
            'master_locations' => $dbLocations->map(fn($l) => ['id' => $l->id, 'code' => $l->code, 'name' => $l->name, 'building' => $l->building, 'display' => $l->name . ($l->building ? " ({$l->building})" : '')]),
            'master_terms' => $dbTerms->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'program' => $t->program, 'is_current' => $t->is_current, 'start_date' => $t->start_date?->toDateString(), 'end_date' => $t->end_date?->toDateString()]),
            'level_presets' => [
                'english conversation' => ['EC-1', 'EC-2', 'EC-3', 'EC-4'],
                'general english' => ['GE-1', 'GE-2', 'GE-3', 'GE-4', 'GE-5', 'GE-6', 'GE-7', 'GE-8', 'GE-9', 'GE-10'],
                'business english' => ['BE-1', 'BE-2', 'BE-3', 'BE-4', 'B-Presentation and Report Writing'],
                'eap' => ['EAP-1', 'EAP-2', 'EAP-3', 'EAP-4'],
                'academic purposes' => ['EAP-1', 'EAP-2', 'EAP-3', 'EAP-4'],
                'grammar' => ['GW-1', 'GW-2', 'GW-3'],
                'writing' => ['GW-1', 'GW-2', 'GW-3', 'Writing and Speaking'],
                'legal english' => ['LE-1', 'LE-2'],
                'ielts' => ['IELTS-Intro', 'IELTS-1', 'IELTS-2', 'IELTS-3', 'GF-IELTS'],
                'toefl' => ['iBT Preparation', 'ITP Preparation'],

                // PPB Bahasa Asing
                'arab' => ['ARB-1', 'ARB-2', 'ARB-3', 'ARB-4', 'ARB-5', 'Arab Tamhidi'],
                'belanda' => ['BEL-1', 'BEL-2', 'BEL-3'],
                'jepang dasar' => ['JPD-1', 'JPD-1B', 'JPD-2', 'JPD-2B', 'JPD-2C', 'JPD-2D', 'JPD-3', 'JPD-3B', 'JPD-3C', 'JPD-4', 'JPD-4B', 'JPD-5', 'JPD-6', 'JPD-7'],
                'jepang menengah' => ['JPM-1', 'JPM-2', 'JPM-3'],
                'jepang' => ['JPD-1', 'JPD-2', 'JPD-3', 'JPD-4', 'JPD-5', 'JPD-6', 'JPD-7', 'JPM-1', 'JPM-2', 'JPM-3', 'JP-INTRO-1', 'JP-INTRO-2'],
                'korea' => ['KORD-1', 'KORD-2', 'KORD-3', 'KORM-1', 'KORM-2', 'KORM-3', 'K-TOPIK-2'],
                'jerman' => ['JER-A1.1', 'JER-A1.2', 'JER-A1.3', 'JER-A2.1'],
                'mandarin dasar' => ['MAN-1', 'MAN-2', 'MAN-3', 'MAN-4', 'MAN-5', 'MAN-6', 'MAN-7'],
                'mandarin menengah' => ['MANM-1', 'MANM-2', 'MANM-3', 'MANM-4'],
                'mandarin' => ['MAN-1', 'MAN-2', 'MAN-3', 'MAN-4', 'MAN-5', 'MAN-6', 'MAN-7', 'MANM-1', 'MANM-2', 'MANM-3', 'MANM-4'],
                'prancis' => ['PRA-A.1.1', 'PRA-A.1.2', 'PRA-A.2.1'],
                'rusia' => ['RUS-1', 'RUS-2'],
                'spanyol' => ['SPA-A1-1', 'SPA-A1-2', 'SPA-A2-1', 'SPA-B1-3', 'SPA-B2-1', 'SPA-B2-2'],
                'isyarat' => ['BJK-DASAR', 'Level Dasar', 'Level Lanjutan'],
                'italia' => ['IT-1', 'IT-2', 'A1', 'A2'],

                // BIPA
                'bipa' => ['BIPA 1', 'BIPA 2', 'BIPA 3', 'BIPA 4'],

                // PPP (Penerjemahan & Penjurubahasaan)
                'hukum' => ['Tingkat 1', 'Tingkat 2', 'Dasar', 'Lanjutan'],
                'teks hukum' => ['Tingkat 1', 'Tingkat 2'],
                'penerjemahan' => ['Dasar', 'Menengah', 'Lanjutan', 'Tingkat 1', 'Tingkat 2'],
                'penjurubahasaan' => ['Novice', 'Paraprofesional', 'Profesional'],
                'ppp' => ['Dasar', 'Menengah', 'Lanjutan', 'Tingkat 1', 'Tingkat 2', 'Novice', 'Paraprofesional', 'Profesional']
            ],
            'divisions' => [
                ['value' => 'ppb', 'label' => 'PPB (English)'],
                ['value' => 'ppb_foreign', 'label' => 'PPB Non Inggris'],
                ['value' => 'ppp', 'label' => 'PPP (Penerjemahan)'],
                ['value' => 'bipa', 'label' => 'BIPA'],
            ]
        ], 'Master schedule options retrieved successfully');
    }

    /**
     * GET /api/v1/public/courses/{catalogId}/levels
     * Return all levels for a specific course catalog
     */
    public function levelsByCatalog(int $catalogId): JsonResponse
    {
        $levels = \App\Models\CourseLevel::where('course_catalog_id', $catalogId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->sendSuccess($levels, 'Course levels retrieved successfully');
    }
}
