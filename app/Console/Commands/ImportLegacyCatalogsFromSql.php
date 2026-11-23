<?php

namespace App\Console\Commands;

use App\Actions\Programs\SyncLanguageTestCatalogsAction;
use App\Actions\Programs\SyncTestPreparationCatalogsAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ImportLegacyCatalogsFromSql extends Command
{
    protected $signature = 'legacy:catalogs-import
        {backup : Path to the legacy SQL backup}
        {--dry-run : Parse and report without writing to the database}';

    protected $description = 'Import test and test-preparation catalogs directly from the legacy SQL backup';

    public function handle(): int
    {
        $path = (string) $this->argument('backup');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Backup is not readable: {$path}");

            return self::FAILURE;
        }

        $records = $this->readLegacyRecords($path);
        $courseRecords = array_values(array_filter(
            $records,
            static fn (array $record) => ($record['type'] ?? null) === 'kursus'
        ));
        $serviceRecords = array_values(array_filter(
            $records,
            static fn (array $record) => ($record['type'] ?? null) === 'service'
        ));

        if ($courseRecords === [] && $serviceRecords === []) {
            $this->error('No course or service records were found in the backup.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Parsed %d course and %d service records from the backup.',
            count($courseRecords),
            count($serviceRecords)
        ));

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($courseRecords, $serviceRecords): void {
            $this->importCourseCatalogs($courseRecords);
            (new SyncLanguageTestCatalogsAction())->execute($serviceRecords);
            (new SyncTestPreparationCatalogsAction())->execute($courseRecords, $serviceRecords);
        });

        $this->info('Legacy course and test catalogs imported directly into course_catalogs.');

        return self::SUCCESS;
    }

    private function importCourseCatalogs(array $courses): void
    {
        $services = [
            'bipa' => DB::table('services')->where('slug', 'program-bipa-129')->value('id'),
            'ppb' => DB::table('services')->where('slug', 'program-pelayanan-bahasa-127')->value('id'),
            'ppp' => DB::table('services')->where('slug', 'program-penerjemahan-dan-penjurubahasaan-1283')->value('id'),
        ];
        $sortOrder = ((int) DB::table('course_catalogs')->max('sort_order')) + 1;
        $imported = 0;

        foreach ($courses as $course) {
            $legacyId = (int) ($course['legacy_nid'] ?? 0);
            $legacyTitle = trim((string) ($course['title'] ?? ''));
            $title = $this->cleanCourseTitle($legacyTitle);
            if ($legacyId <= 0 || $title === '') {
                continue;
            }

            $division = $this->courseDivision($title);
            $serviceId = $services[$division === 'ppp' ? 'ppp' : ($division === 'bipa' ? 'bipa' : 'ppb')] ?? null;
            if (! $serviceId) {
                throw new RuntimeException("Missing service for legacy course {$legacyId} ({$division}).");
            }

            $slug = Str::slug($title);
            $existing = DB::table('course_catalogs')
                ->where('legacy_nid', $legacyId)
                ->first([
                    'id', 'slug', 'sort_order', 'summary', 'description', 'level',
                    'learning_outcomes', 'instructor_info', 'registration_url', 'image_url',
                ]);
            if (! $existing) {
                $existing = DB::table('course_catalogs')
                    ->where('slug', $slug)
                    ->first([
                        'id', 'slug', 'sort_order', 'summary', 'description', 'level',
                        'learning_outcomes', 'instructor_info', 'registration_url', 'image_url',
                    ]);
            }
            $slug = $existing?->slug ?: $slug;
            if (! $existing && DB::table('course_catalogs')->where('slug', $slug)->exists()) {
                $slug .= '-' . $legacyId;
            }

            $body = trim((string) ($course['body'] ?? ''));
            $summary = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(
                strip_tags($body),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )));
            $values = [
                'service_id' => $serviceId,
                'title' => $title,
                'slug' => $slug,
                'division' => $division,
                'is_active' => (string) ($course['status'] ?? '0') === '1',
                'sort_order' => $existing?->sort_order ?? $sortOrder++,
                'updated_at' => now(),
                'legacy_nid' => $legacyId,
            ];
            $details = [
                'summary' => $summary !== '' ? Str::limit($summary, 500) : null,
                'description' => $body !== '' ? $body : null,
                'level' => $course['level'] ?? null,
                'learning_outcomes' => $course['learning_outcomes'] ?? null,
                'instructor_info' => $course['instructor_info'] ?? null,
                'registration_url' => $course['registration_url'] ?? null,
                'image_url' => $course['image_url'] ?? null,
            ];
            foreach ($details as $column => $value) {
                if (! $existing || trim((string) ($existing->{$column} ?? '')) === '') {
                    $values[$column] = $value;
                }
            }

            if ($existing) {
                DB::table('course_catalogs')->where('id', $existing->id)->update($values);
            } else {
                DB::table('course_catalogs')->insert($values + ['created_at' => now()]);
            }
            $catalogId = $existing?->id ?? DB::table('course_catalogs')
                ->where('legacy_nid', $legacyId)
                ->value('id');
            DB::table('course_schedules')
                ->whereNull('course_catalog_id')
                ->where(function ($query) use ($legacyTitle, $title, $slug) {
                    $query->whereIn('course_slug', array_unique([$slug, Str::slug($legacyTitle)]))
                        ->orWhereIn('program_name', array_unique([$legacyTitle, $title]));
                })
                ->update([
                    'course_catalog_id' => $catalogId,
                    'division' => $division,
                ]);
            DB::table('course_schedules')
                ->where('course_catalog_id', $catalogId)
                ->update(['division' => $division]);
            $imported++;
        }

        $this->info("Imported or updated {$imported} legacy course catalogs.");
    }

    private function cleanCourseTitle(string $title): string
    {
        $title = preg_replace('/\s*\((?:daring|tatap muka|luring|daring\/luring|online|offline)\)\s*/i', '', $title);
        $title = preg_replace('/\s+(?:senin|selasa|rabu|kamis|jumat|sabtu|minggu).*$/i', '', trim((string) $title));

        return trim((string) $title, " \t\n\r\0\x0B-");
    }

    private function courseDivision(string $title): string
    {
        $normalized = Str::lower(Str::ascii($title));
        if (preg_match('/\b(bipa|bahasa indonesia)\b/u', $normalized) === 1) {
            return 'bipa';
        }
        if (preg_match('/\b(penerjemah|penerjemahan|interpretasi|jurubahasa)\b/u', $normalized) === 1) {
            return 'ppp';
        }

        return preg_match('/\b(english|inggris|grammar|academic|business|legal|writing|speaking|conversation|ibt|toefl|ielts)\b/u', $normalized) === 1
            ? 'ppb_english'
            : 'ppb_foreign';
    }

    private function readLegacyRecords(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open backup: {$path}");
        }

        $schemas = [];
        $currentTable = null;
        $nodes = [];
        $fieldTables = [];
        $fieldRows = [];
        $bodies = [];
        $fileUris = [];
        $fileSize = filesize($path) ?: 0;
        $lastProgress = 0;

        while (($line = fgets($handle)) !== false) {
            $position = ftell($handle);
            if ($fileSize > 0 && $position - $lastProgress >= 268435456) {
                $this->line(sprintf('Read %.0f%% of backup...', min(100, ($position / $fileSize) * 100)));
                $lastProgress = $position;
            }

            if (preg_match('/^CREATE TABLE `([^`]+)`/', $line, $match) === 1) {
                $table = $match[1];
                $isFieldTable = str_starts_with($table, 'node__field_')
                    && preg_match('/hasil_belajar|pengajar|level|image|link|lokasi|period|program/', $table) === 1;
                $currentTable = in_array($table, ['node_field_data', 'node__body', 'file_managed'], true)
                    || $isFieldTable
                    ? $table
                    : null;
                if ($currentTable !== null) {
                    $schemas[$currentTable] = [];
                    if ($isFieldTable) {
                        $fieldTables[$currentTable] = $this->fieldNameFromTable($currentTable);
                    }
                }
                continue;
            }

            if ($currentTable !== null && preg_match('/^\s*`([^`]+)`\s+/', $line, $match) === 1) {
                $schemas[$currentTable][] = $match[1];
            }

            if (preg_match('/^INSERT INTO `([^`]+)`(?: \(([^)]*)\))? VALUES /', $line, $match) !== 1) {
                continue;
            }

            $table = $match[1];
            if (! isset($schemas[$table])) {
                continue;
            }

            $columns = isset($match[2]) && $match[2] !== ''
                ? array_map(static fn ($column) => trim($column, " `"), explode(',', $match[2]))
                : $schemas[$table];

            foreach ($this->parseTuples($line) as $tuple) {
                $values = str_getcsv($tuple, ',', "'", '\\');
                if (count($values) !== count($columns)) {
                    fclose($handle);
                    throw new RuntimeException("Unexpected column count while parsing {$table}.");
                }
                $row = array_combine($columns, array_map(
                    static fn ($value) => strtoupper(trim($value)) === 'NULL' ? null : stripcslashes(trim($value)),
                    $values
                ));

                if ($table === 'file_managed') {
                    if (! empty($row['fid']) && ! empty($row['uri'])) {
                        $fileUris[(string) $row['fid']] = $row['uri'];
                    }
                    continue;
                }

                if ($table === 'node_field_data') {
                    if (! in_array($row['type'] ?? null, ['kursus', 'service'], true)) {
                        continue;
                    }
                    $id = (string) ($row['nid'] ?? '');
                    if ($id !== '') {
                        $nodes[$id] = [
                            'legacy_nid' => ctype_digit($id) ? (int) $id : null,
                            'type' => $row['type'],
                            'title' => $row['title'] ?? '',
                            'status' => $row['status'] ?? '0',
                            'body' => null,
                        ];
                    }
                    continue;
                }

                if (isset($fieldTables[$table])) {
                    $id = (string) ($row['entity_id'] ?? $row['nid'] ?? '');
                    if ($id !== '') {
                        $fieldRows[$id][$fieldTables[$table]][] = $row;
                    }
                    continue;
                }

                $id = (string) ($row['entity_id'] ?? '');
                if ($id !== '' && ! isset($bodies[$id])) {
                    $bodies[$id] = $row['body_value'] ?? null;
                }
            }
        }

        if (! feof($handle)) {
            fclose($handle);
            throw new RuntimeException("Failed while reading backup: {$path}");
        }
        fclose($handle);

        foreach ($nodes as $id => &$node) {
            $node['body'] = $bodies[$id] ?? null;
            if ($node['type'] !== 'kursus') {
                continue;
            }
            $fields = $fieldRows[$id] ?? [];
            $node['learning_outcomes'] = $this->firstField(
                $fields['hasil_belajar'] ?? [],
                ['hasil_belajar', 'field_hasil_belajar']
            );
            $node['instructor_info'] = $this->firstField(
                $fields['pengajar'] ?? [],
                ['pengajar', 'field_pengajar']
            );
            $node['level'] = $this->firstField($fields['level'] ?? [], ['level', 'field_level']);
            $imageId = $this->firstField($fields['image'] ?? [], ['image', 'field_image']);
            $node['image_url'] = $imageId !== null ? ($fileUris[(string) $imageId] ?? null) : null;
            $node['registration_url'] = $this->firstField($fields['link'] ?? [], ['link', 'field_link']);
        }
        unset($node);

        return array_values($nodes);
    }

    private function fieldNameFromTable(string $table): string
    {
        $fieldName = preg_replace('/^node__field_/', '', $table) ?? $table;
        foreach (['hasil_belajar', 'pengajar', 'level', 'image', 'link', 'lokasi', 'period', 'program'] as $field) {
            if (str_starts_with($fieldName, $field)) {
                return $field;
            }
        }

        return $fieldName;
    }

    private function firstField(array $rows, array $prefixes): ?string
    {
        foreach ($rows as $row) {
            foreach ($row as $key => $value) {
                foreach ($prefixes as $prefix) {
                    if (
                        ($key === $prefix || str_starts_with($key, $prefix . '_'))
                        && $value !== null
                        && $value !== ''
                    ) {
                        return (string) $value;
                    }
                }
            }
        }

        return null;
    }

    private function parseTuples(string $line): array
    {
        $marker = strpos($line, 'VALUES ');
        if ($marker === false) {
            return [];
        }

        $tuples = [];
        $start = null;
        $depth = 0;
        $quoted = false;
        $escaped = false;
        $length = strlen($line);

        for ($index = $marker + 7; $index < $length; $index++) {
            $character = $line[$index];
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($quoted && $character === '\\') {
                $escaped = true;
                continue;
            }
            if ($character === "'") {
                $quoted = ! $quoted;
                continue;
            }
            if ($quoted) {
                continue;
            }
            if ($character === '(') {
                if ($depth === 0) {
                    $start = $index + 1;
                }
                $depth++;
            } elseif ($character === ')' && $depth > 0) {
                $depth--;
                if ($depth === 0 && $start !== null) {
                    $tuples[] = substr($line, $start, $index - $start);
                    $start = null;
                }
            }
        }

        return $tuples;
    }
}
