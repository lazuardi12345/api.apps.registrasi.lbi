<?php

namespace App\Console\Commands;

use App\Models\CourseCatalog;
use App\Models\CourseEnrollment;
use App\Models\CourseSchedule;
use App\Models\PaymentConfirmation;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MapLegacyWebformsToEnrollments extends Command
{
    protected $signature = 'legacy:webforms-map
        {--program=all : Program scope: all, ppb, ppp, or bipa}
        {--chunk=50 : Number of submissions to transform per chunk}
        {--limit=0 : Optional limit of submissions to map}
        {--force : Force re-mapping of already mapped submissions}';

    protected $description = 'Transform staged legacy Drupal webform submissions into modern Laravel models (Enrollments, Users, Profiles)';

    /**
     * Cache catalogs and schedules in memory to prevent N+1 queries.
     */
    private array $catalogs = [];
    private array $schedules = [];
    private array $taxonomyTerms = [];

    public function handle(): int
    {
        $program = strtolower((string) $this->option('program'));
        $chunkSize = max(10, (int) $this->option('chunk'));
        $limit = max(0, (int) $this->option('limit'));
        $force = (bool) $this->option('force');

        if (!in_array($program, ['all', 'ppb', 'ppp', 'bipa'], true)) {
            $this->error('The --program option must be all, ppb, ppp, or bipa.');
            return self::FAILURE;
        }

        $this->info("Initializing Legacy Webforms Mapping Pipeline [Program: {$program}, Chunk: {$chunkSize}]...");

        $this->loadCatalogAndScheduleCache();

        $query = DB::table('legacy_webform_submissions')
            ->join('legacy_webforms', 'legacy_webform_submissions.legacy_webform_id', '=', 'legacy_webforms.legacy_webform_id')
            ->select([
                'legacy_webform_submissions.id',
                'legacy_webform_submissions.legacy_sid',
                'legacy_webform_submissions.legacy_webform_id',
                'legacy_webform_submissions.legacy_uid',
                'legacy_webform_submissions.state',
                'legacy_webform_submissions.submitted_at',
                'legacy_webform_submissions.mapped',
                'legacy_webforms.program',
                'legacy_webforms.title as form_title',
            ])
            ->orderBy('legacy_webform_submissions.legacy_sid');

        if ($program !== 'all') {
            $query->where('legacy_webforms.program', $program);
        }

        if (!$force) {
            $query->where('legacy_webform_submissions.mapped', false);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $totalSubmissions = $query->count();
        if ($totalSubmissions === 0) {
            $this->info('No unmapped submissions found for the selected criteria.');
            return self::SUCCESS;
        }

        $this->info("Found {$totalSubmissions} submissions to map. Starting processing in chunks...");

        $processed = 0;
        $createdEnrollments = 0;
        $createdUsers = 0;
        $createdPayments = 0;

        while (true) {
            $submissions = (clone $query)->limit($chunkSize)->get();
            if ($submissions->isEmpty()) {
                break;
            }
            $sids = $submissions->pluck('legacy_sid')->all();

            // 1. Fetch all raw EAV data for this chunk in one fast query
            $rawData = DB::table('legacy_webform_submission_data')
                ->whereIn('legacy_sid', $sids)
                ->get()
                ->groupBy('legacy_sid');

            DB::beginTransaction();
            try {
                $mappedSids = [];

                foreach ($submissions as $submission) {
                    $sid = $submission->legacy_sid;
                    $fieldRows = $rawData->get($sid, collect());

                    $fields = [];
                    foreach ($fieldRows as $row) {
                        $fields[$row->field_name] = $row->value;
                    }

                    // 2. Resolve Student Identity
                    $name = $this->resolveStudentName($fields, $sid);
                    $email = $this->resolveStudentEmail($fields, $sid);
                    $phone = $this->resolveStudentPhone($fields);

                    // 3. Resolve or Create User (Deduplicate by legacy_uid or email)
                    $user = null;
                    if (!empty($submission->legacy_uid)) {
                        $user = User::where('legacy_uid', (string) $submission->legacy_uid)->first();
                    }
                    if (!$user) {
                        $user = User::where('email', $email)->first();
                    }
                    if (!$user) {
                        $user = User::create([
                            'name' => $name,
                            'email' => $email,
                            'password' => Hash::make(Str::random(24)),
                            'role' => 'student',
                            'status' => true,
                            'legacy_uid' => $submission->legacy_uid ? (string) $submission->legacy_uid : null,
                        ]);
                        $createdUsers++;
                    }

                    // 4. Resolve Student Profile
                    $this->syncStudentProfile($user->id, $fields, $name, $phone);

                    // 5. Normalization per Division
                    $division = strtolower($submission->program ?: 'ppb');
                    $courseInfo = $this->resolveCourseDetails($fields, $submission, $division);

                    // 6. Match Catalog & Schedule
                    $catalogId = $this->matchCatalogId($courseInfo['course_name'], $division);
                    $scheduleId = $this->matchScheduleId($catalogId, $courseInfo['schedule_days'], $courseInfo['schedule_time']);

                    // 7. Payment status
                    $paymentInfo = $this->resolvePaymentStatus($fields, $submission);

                    // 8. Upsert CourseEnrollment
                    $enrollmentData = [
                        'legacy_sid' => $sid,
                        'webform_id' => $submission->legacy_webform_id,
                        'legacy_uid' => $submission->legacy_uid,
                        'user_id' => $user->id,
                        'course_catalog_id' => $catalogId,
                        'course_schedule_id' => $scheduleId,
                        'registration_source' => 'drupal_webform',
                        'division' => $division,
                        'student_type' => $this->resolveStudentType($fields),
                        'term_period' => $courseInfo['term_period'],
                        'student_name' => $name,
                        'student_email' => $email,
                        'student_phone' => $phone,
                        'course_name' => $courseInfo['course_name'],
                        'level_name' => $courseInfo['level_name'],
                        'campus_location' => $courseInfo['campus_location'],
                        'learning_mode' => $courseInfo['learning_mode'],
                        'schedule_days' => $courseInfo['schedule_days'],
                        'schedule_time' => $courseInfo['schedule_time'],
                        'discount_percent' => $courseInfo['discount_percent'],
                        'registration_fee' => $courseInfo['registration_fee'],
                        'postpone_reason' => $courseInfo['postpone_reason'],
                        'status' => $paymentInfo['enrollment_status'],
                        'payment_status' => $paymentInfo['payment_status'],
                        'submitted_at' => $submission->submitted_at,
                        'metadata' => $this->cleanMetadata($fields),
                        'updated_at' => now(),
                    ];

                    $enrollment = CourseEnrollment::updateOrCreate(
                        ['legacy_sid' => $sid],
                        array_merge($enrollmentData, ['created_at' => now()])
                    );
                    $createdEnrollments++;

                    // 9. Payment Confirmation Record (if payment proof exists)
                    if ($paymentInfo['has_payment_record']) {
                        PaymentConfirmation::updateOrCreate(
                            ['legacy_sid' => $sid],
                            [
                                'enrollment_id' => $enrollment->id,
                                'user_id' => $user->id,
                                'student_name' => $name,
                                'student_email' => $email,
                                'course_name' => $courseInfo['course_name'],
                                'payment_for' => $courseInfo['course_name'] ?: 'Kursus LBI FIB UI',
                                'bank_name' => $paymentInfo['bank_name'] ?: 'BNI',
                                'account_number' => $paymentInfo['account_number'],
                                'account_holder' => $name,
                                'amount' => $courseInfo['registration_fee'] ?: 0,
                                'payment_date' => $paymentInfo['payment_date'] ?: ($submission->submitted_at ? substr($submission->submitted_at, 0, 10) : now()->toDateString()),
                                'proof_file' => $paymentInfo['proof_file'],
                                'status' => $paymentInfo['payment_status'] === 'paid' ? 'verified' : 'pending',
                                'verified_at' => $paymentInfo['payment_status'] === 'paid' ? now() : null,
                            ]
                        );
                        $createdPayments++;
                    }

                    $mappedSids[] = $sid;
                }

                // 10. Mark Submissions as Mapped
                if (!empty($mappedSids)) {
                    DB::table('legacy_webform_submissions')
                        ->whereIn('legacy_sid', $mappedSids)
                        ->update(['mapped' => true, 'updated_at' => now()]);
                }

                DB::commit();
                $processed += count($submissions);
                $this->info("Mapped chunk of {$processed} submissions (Enrollments: {$createdEnrollments}, Users: {$createdUsers}, Payments: {$createdPayments})...");
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("Error processing chunk: " . $e->getMessage() . " on line " . $e->getLine());
                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info("=================================================");
        $this->info(" MAPPING COMPLETE SUMMARY");
        $this->info("=================================================");
        $this->info(" Total Submissions Processed : {$processed}");
        $this->info(" Course Enrollments Created  : {$createdEnrollments}");
        $this->info(" Student Users Created/Synced: {$createdUsers}");
        $this->info(" Payment Confirmations Staged: {$createdPayments}");
        $this->info("=================================================");

        return self::SUCCESS;
    }

    private function loadCatalogAndScheduleCache(): void
    {
        $this->catalogs = CourseCatalog::select(['id', 'title', 'slug', 'division'])->get()->toArray();
        $this->schedules = CourseSchedule::select(['id', 'course_catalog_id', 'program_name', 'schedule_text', 'division'])->get()->toArray();

        $this->taxonomyTerms = DB::table('legacy_taxonomy_terms')
            ->orderBy('vocabulary')
            ->get(['legacy_tid', 'name'])
            ->unique('legacy_tid')
            ->mapWithKeys(fn ($term) => [(string) $term->legacy_tid => $term->name])
            ->all();
    }

    private function resolveStudentName(array $fields, int $sid): string
    {
        $candidates = ['full_name', 'nama_lengkap', 'nama', 'full_name_sof', 'nama_siswa', 'student_name'];
        foreach ($candidates as $key) {
            if (!empty($fields[$key])) {
                return trim((string) $fields[$key]);
            }
        }

        return "Peserta {$sid}";
    }

    private function resolveStudentEmail(array $fields, int $sid): string
    {
        $candidates = ['email', 'gmail_account', 'email_sof', 'alamat_email', 'surel'];
        foreach ($candidates as $key) {
            if (!empty($fields[$key]) && filter_var(trim((string) $fields[$key]), FILTER_VALIDATE_EMAIL)) {
                return strtolower(trim((string) $fields[$key]));
            }
        }

        return "peserta_{$sid}@legacy.lbifibui.ac.id";
    }

    private function resolveStudentPhone(array $fields): ?string
    {
        $candidates = ['whatsapp_number', 'phone_number_sof', 'handphone', 'nomor_telepon', 'no_hp', 'phone', 'telepon'];
        foreach ($candidates as $key) {
            if (!empty($fields[$key])) {
                return trim((string) $fields[$key]);
            }
        }

        return null;
    }

    private function syncStudentProfile(int $userId, array $fields, string $name, ?string $phone): void
    {
        $identity = !empty($fields['passport_number']) ? $fields['passport_number'] : (!empty($fields['passport_numberr_sof']) ? $fields['passport_numberr_sof'] : (!empty($fields['no_ktp']) ? $fields['no_ktp'] : (!empty($fields['nik']) ? $fields['nik'] : null)));
        $gender = strtolower((string) ($fields['gender'] ?? $fields['gender_sof'] ?? $fields['jenis_kelamin'] ?? ''));
        $gender = match ($gender) {
            'male', 'laki-laki', 'pria', 'l' => 'Laki-laki',
            'female', 'perempuan', 'wanita', 'p' => 'Perempuan',
            default => null,
        };

        $birthPlace = !empty($fields['place_of_birth_city_country_']) ? $fields['place_of_birth_city_country_'] : (!empty($fields['tempat_lahir']) ? $fields['tempat_lahir'] : null);
        $rawBirthDate = trim((string) ($fields['date_of_birth'] ?? $fields['date_of_birth_sof'] ?? $fields['tanggal_lahir'] ?? ''));
        $birthDate = null;
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $rawBirthDate, $m)) {
            $birthDate = $m[1];
        }

        $occupation = !empty($fields['occupation']) ? $fields['occupation'] : (!empty($fields['pekerjaan']) ? $fields['pekerjaan'] : null);
        $education = !empty($fields['education']) ? $fields['education'] : (!empty($fields['degree']) ? $fields['degree'] : (!empty($fields['edukasi_terakhir']) ? $fields['edukasi_terakhir'] : (!empty($fields['pendidikan']) ? $fields['pendidikan'] : null)));
        $address = !empty($fields['indonesian_address']) ? $fields['indonesian_address'] : (!empty($fields['home_address']) ? $fields['home_address'] : (!empty($fields['alamat']) ? $fields['alamat'] : null));

        StudentProfile::updateOrCreate(
            ['user_id' => $userId],
            [
                'full_name' => $name,
                'identity_number' => $identity,
                'gender' => $gender,
                'birth_place' => $birthPlace,
                'birth_date' => $birthDate,
                'phone' => $phone,
                'education' => $education,
                'occupation' => $occupation,
                'address' => $address,
            ]
        );
    }

    private function resolveCourseDetails(array $fields, object $submission, string $division): array
    {
        $courseName = null;
        $levelName = null;
        $campusLocation = null;
        $learningMode = null;
        $scheduleDays = null;
        $scheduleTime = null;
        $discountPercent = 0.00;
        $registrationFee = null;
        $termPeriod = $fields['periode'] ?? $fields['registration_number'] ?? null;
        $postponeReason = null;

        // PPB specifics
        if ($division === 'ppb') {
            $campusLocation = !empty($fields['lokasi']) ? ucfirst(strtolower($fields['lokasi'])) : 'Depok';
            $learningMode = (!empty($fields['daring']) && $fields['daring'] == '1') ? 'Online' : 'Offline';

            // Find course & level
            $courseName = $fields['kursus_en_depok'] ?? $fields['kursus_en_salemba'] ?? $fields['program_kursus'] ?? $fields['kursus'] ?? null;
            $levelName = $fields['level_en_depok'] ?? $fields['level_en_salemba'] ?? $fields['level'] ?? null;

            // Discount
            if (!empty($fields['besar_keringanan'])) {
                $discountPercent = (float) filter_var($fields['besar_keringanan'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            }
            if (!empty($fields['biaya_pendaftaran'])) {
                $registrationFee = (float) filter_var($fields['biaya_pendaftaran'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            }
        } elseif ($division === 'bipa') {
            $courseName = 'BIPA Regular Program';
            $levelName = $fields['level_registration_23'] ?? $fields['level_registration'] ?? 'Level 1';
            $campusLocation = 'Depok';
            $learningMode = 'Offline';
        } elseif ($division === 'ppp') {
            $courseName = $fields['kursus'] ?? $fields['kursus_ppp'] ?? 'Pelatihan Penerjemahan PPP';
            $campusLocation = 'Salemba';
            $learningMode = 'Online';
        }

        // Detect schedule day & time from checkboxes if present
        foreach ($fields as $key => $val) {
            if ($val == '1' || $val === 1) {
                if (str_contains($key, 'senin') || str_contains($key, 'selasa') || str_contains($key, 'rabu') || str_contains($key, 'kamis') || str_contains($key, 'jumat') || str_contains($key, 'sabtu')) {
                    $cleanKey = str_replace('_', ' ', $key);
                    if (preg_match('/(\d{2}\s\d{2}\s\d{2}\s\d{2})/i', $cleanKey) || preg_match('/(\d{2}\s\d{2})/i', $cleanKey)) {
                        $scheduleTime = $cleanKey;
                    } else {
                        $scheduleDays = $scheduleDays ? "{$scheduleDays}, {$cleanKey}" : $cleanKey;
                    }
                }
            }
        }

        // Resolve taxonomy term IDs if numeric
        if (!empty($courseName) && isset($this->taxonomyTerms[(string) $courseName])) {
            $courseName = $this->taxonomyTerms[(string) $courseName];
        }
        if (!empty($levelName) && isset($this->taxonomyTerms[(string) $levelName])) {
            $levelName = $this->taxonomyTerms[(string) $levelName];
        }

        if (empty($courseName)) {
            $courseName = $submission->form_title ?: "Kursus {$division}";
        }

        return [
            'course_name' => (string) $courseName,
            'level_name' => (string) $levelName,
            'campus_location' => $campusLocation,
            'learning_mode' => $learningMode,
            'schedule_days' => $scheduleDays,
            'schedule_time' => $scheduleTime,
            'discount_percent' => $discountPercent,
            'registration_fee' => $registrationFee,
            'term_period' => $termPeriod,
            'postpone_reason' => $postponeReason,
        ];
    }

    private function resolveStudentType(array $fields): string
    {
        if (!empty($fields['siswa_lanjutan']) && $fields['siswa_lanjutan'] == '1') {
            return 'lanjutan';
        }
        if (!empty($fields['siswa_transfer']) && $fields['siswa_transfer'] == '1') {
            return 'transfer';
        }
        if (!empty($fields['siswa_postpone']) && ($fields['siswa_postpone'] == '1' || strtolower($fields['siswa_postpone']) === 'ya')) {
            return 'postpone';
        }

        return 'baru';
    }

    private function resolvePaymentStatus(array $fields, object $submission): array
    {
        $isPaid = false;
        if (!empty($fields['payment_injected']) && $fields['payment_injected'] == '1') {
            $isPaid = true;
        } elseif (!empty($fields['payment_completed']) && $fields['payment_completed'] == '1') {
            $isPaid = true;
        } elseif (!empty($fields['payment_status']) && strtolower($fields['payment_status']) === 'paid') {
            $isPaid = true;
        } elseif ($submission->state === 'completed') {
            $isPaid = true;
        }

        $hasPaymentRecord = !empty($fields['bukti_pembayaran']) || !empty($fields['payment_confirmation']) || $isPaid;

        return [
            'enrollment_status' => $isPaid ? 'approved' : 'pending',
            'payment_status' => $isPaid ? 'paid' : 'pending',
            'has_payment_record' => $hasPaymentRecord,
            'bank_name' => !empty($fields['dari_bank']) ? $fields['dari_bank'] : (!empty($fields['bank_name']) ? $fields['bank_name'] : 'BNI'),
            'account_number' => !empty($fields['account_number']) ? $fields['account_number'] : null,
            'payment_date' => (!empty($fields['tanggal_pembayaran']) && preg_match('/^(\d{4}-\d{2}-\d{2})/', $fields['tanggal_pembayaran'], $m)) ? $m[1] : null,
            'proof_file' => !empty($fields['bukti_pembayaran']) ? $fields['bukti_pembayaran'] : null,
        ];
    }

    private function matchCatalogId(?string $courseName, string $division): ?int
    {
        if (!$courseName) {
            return null;
        }

        $cleanCourse = strtolower(trim($courseName));
        foreach ($this->catalogs as $cat) {
            $catTitle = strtolower($cat['title']);
            if (str_contains($catTitle, $cleanCourse) || str_contains($cleanCourse, $catTitle)) {
                return (int) $cat['id'];
            }
        }

        return null;
    }

    private function matchScheduleId(?int $catalogId, ?string $days, ?string $time): ?int
    {
        if (!$catalogId) {
            return null;
        }

        foreach ($this->schedules as $sched) {
            if ($sched['course_catalog_id'] === $catalogId) {
                return (int) $sched['id'];
            }
        }

        return null;
    }

    private function cleanMetadata(array $fields): array
    {
        $excluded = ['nama_lengkap', 'email', 'full_name', 'handphone'];
        $clean = [];
        foreach ($fields as $k => $v) {
            if (!in_array($k, $excluded, true) && $v !== '' && $v !== null) {
                $clean[$k] = $v;
            }
        }

        return $clean;
    }
}
