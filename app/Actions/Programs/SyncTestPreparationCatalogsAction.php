<?php

namespace App\Actions\Programs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SyncTestPreparationCatalogsAction
{
    public function execute(array $courseRecords, array $serviceRecords): void
    {
        $serviceId = DB::table('services')
            ->where('slug', 'program-pelayanan-bahasa-127')
            ->value('id');

        if (! $serviceId) {
            throw new RuntimeException('The language program service is missing.');
        }

        $courses = collect($courseRecords)
            ->filter(fn (array $record) =>
                (string) ($record['status'] ?? '0') === '1'
                && $this->isTestPreparation($record['title'] ?? '')
            )
            ->values();
        $representedTitles = $courses
            ->map(fn (array $record) => $this->normalizeTitle($record['title'] ?? ''))
            ->all();
        $services = collect($serviceRecords)
            ->filter(fn (array $record) =>
                ($record['type'] ?? null) === 'service'
                && (string) ($record['status'] ?? '0') === '1'
                && $this->isTestPreparation($record['title'] ?? '')
            )
            ->reject(fn (array $record) =>
                in_array($this->normalizeTitle($record['title'] ?? ''), $representedTitles, true)
            );

        $records = $courses
            ->map(fn (array $record) => ['record' => $record, 'source' => 'course'])
            ->concat($services->map(fn (array $record) => ['record' => $record, 'source' => 'service']))
            ->values();

        if ($records->isEmpty()) {
            throw new RuntimeException('No test preparation courses were found in the legacy import data.');
        }

        $candidateLegacyIds = $records
            ->pluck('record.legacy_nid')
            ->filter()
            ->map(fn ($legacyId) => (int) $legacyId)
            ->all();
        $sourceLegacyIds = collect($courseRecords)
            ->concat($serviceRecords)
            ->pluck('legacy_nid')
            ->filter()
            ->map(fn ($legacyId) => (int) $legacyId)
            ->unique()
            ->values()
            ->all();

        if ($sourceLegacyIds !== []) {
            DB::table('course_catalogs')
                ->where('category', 'test_preparation')
                ->whereIn('legacy_nid', $sourceLegacyIds)
                ->whereNotIn('legacy_nid', $candidateLegacyIds)
                ->update([
                    'category' => null,
                    'updated_at' => now(),
                ]);
        }

        $nextSortOrder = ((int) DB::table('course_catalogs')
            ->where('category', 'test_preparation')
            ->max('sort_order')) + 1;

        foreach ($records as $item) {
            $record = $item['record'];
            $legacyId = (int) ($record['legacy_nid'] ?? 0);
            $title = $this->cleanTitle((string) ($record['title'] ?? ''));

            if ($legacyId <= 0 || $title === '') {
                continue;
            }

            $existing = DB::table('course_catalogs')
                ->where('legacy_nid', $legacyId)
                ->first(['id', 'category']);

            if ($existing) {
                $updates = [
                    'category' => 'test_preparation',
                    'updated_at' => now(),
                ];
                if ($existing->category !== 'test_preparation') {
                    $updates['sort_order'] = $nextSortOrder++;
                }
                DB::table('course_catalogs')->where('id', $existing->id)->update($updates);
                continue;
            }

            $body = trim((string) ($record['body'] ?? $record['description'] ?? ''));
            $plainSummary = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(
                strip_tags($body),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )));
            $slug = trim((string) ($record['slug'] ?? '')) ?: Str::slug($title);

            if (DB::table('course_catalogs')->where('slug', $slug)->exists()) {
                $slug .= '-' . $legacyId;
            }

            DB::table('course_catalogs')->insert([
                'legacy_nid' => $legacyId,
                'service_id' => $serviceId,
                'title' => $title,
                'slug' => $slug,
                'summary' => $plainSummary !== '' ? Str::limit($plainSummary, 500) : null,
                'description' => $body !== '' ? $body : null,
                'division' => $this->divisionForTitle($title),
                'category' => 'test_preparation',
                'is_active' => true,
                'sort_order' => $nextSortOrder++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function isTestPreparation(string $title): bool
    {
        $title = Str::lower(Str::ascii($title));
        $isExam = preg_match('/\b(ielts|toefl|ibt|delf|jlpt|topik)\b/u', $title) === 1
            || preg_match('/\btes jerman\s+[ab]\d\b/u', $title) === 1;

        return $isExam && preg_match('/\b(preparation|persiapan|preparasi|grammar|writing|speaking)\b/u', $title) === 1;
    }

    private function normalizeTitle(string $title): string
    {
        $title = Str::lower(Str::ascii($title));
        $title = preg_replace('/\s*\((?:daring|tatap muka|luring|online|offline)\)\s*/u', '', $title);

        return preg_replace('/[^a-z0-9]+/u', '', (string) $title) ?? '';
    }

    private function cleanTitle(string $title): string
    {
        $title = preg_replace('/\s*\((?:daring|tatap muka|luring|daring\/luring|online|offline)\)\s*/i', '', $title);
        $title = preg_replace('/\s+(?:senin|selasa|rabu|kamis|jumat|sabtu|minggu).*$/i', '', trim((string) $title));

        return trim((string) $title, " \t\n\r\0\x0B-");
    }

    private function divisionForTitle(string $title): string
    {
        return preg_match('/\b(english|inggris|grammar|ielts|toefl|ibt)\b/i', Str::ascii($title)) === 1
            ? 'ppb_english'
            : 'ppb_foreign';
    }
}
