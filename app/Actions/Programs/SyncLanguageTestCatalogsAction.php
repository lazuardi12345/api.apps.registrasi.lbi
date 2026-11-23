<?php

namespace App\Actions\Programs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SyncLanguageTestCatalogsAction
{
    public function execute(array $sourceRecords): void
    {
        $serviceId = DB::table('services')
            ->where('slug', 'program-pelayanan-bahasa-127')
            ->value('id');

        if (! $serviceId) {
            throw new RuntimeException('The language testing service is missing.');
        }

        $serviceLegacyIds = DB::table('services')
            ->whereNotNull('legacy_nid')
            ->pluck('legacy_nid')
            ->map(fn ($legacyId) => (int) $legacyId)
            ->all();
        $existingCatalogs = DB::table('course_catalogs')
            ->whereNotNull('legacy_nid')
            ->get(['legacy_nid', 'slug', 'sort_order'])
            ->keyBy('legacy_nid');

        $tests = collect($sourceRecords)
            ->filter(fn (array $record) => $this->isLanguageTest($record))
            ->reject(fn (array $record) => in_array((int) $record['legacy_nid'], $serviceLegacyIds, true))
            ->sortBy(fn (array $record) =>
                $existingCatalogs->get($record['legacy_nid'])?->sort_order ?? PHP_INT_MAX
            )
            ->values();

        if ($tests->isEmpty()) {
            throw new RuntimeException('No active language tests were found in the legacy import data.');
        }

        DB::table('course_catalogs')
            ->whereIn('legacy_nid', $serviceLegacyIds)
            ->where('category', 'language_test')
            ->update([
                'division' => 'bipa',
                'category' => null,
                'is_active' => false,
                'updated_at' => now(),
            ]);

        $nextSortOrder = ((int) DB::table('course_catalogs')
            ->where('division', 'testing')
            ->max('sort_order')) + 1;

        foreach ($tests as $record) {
            $legacyId = (int) $record['legacy_nid'];
            $existing = $existingCatalogs->get($legacyId);
            $body = trim((string) ($record['body'] ?? ''));
            $plainSummary = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(
                strip_tags($body),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )));

            $values = [
                'service_id' => $serviceId,
                'title' => trim($record['title']),
                'slug' => $existing?->slug ?: Str::slug($record['title']),
                'summary' => $plainSummary !== ''
                    ? Str::limit($plainSummary, 500)
                    : null,
                'description' => $body !== '' ? $body : null,
                'division' => 'testing',
                'category' => 'language_test',
                'is_active' => true,
                'sort_order' => $existing?->sort_order ?? $nextSortOrder++,
                'updated_at' => now(),
            ];
            if (! $existing) {
                $values['created_at'] = now();
            }

            DB::table('course_catalogs')->updateOrInsert(
                ['legacy_nid' => $legacyId],
                $values
            );
        }
    }

    private function isLanguageTest(array $record): bool
    {
        if (
            ($record['type'] ?? null) !== 'service'
            || (string) ($record['status'] ?? '0') !== '1'
            || empty($record['legacy_nid'])
            || empty($record['title'])
        ) {
            return false;
        }

        $title = Str::lower($record['title']);
        if (preg_match('/\b(preparation|persiapan|grammar|writing|speaking)\b/u', $title)) {
            return false;
        }
        if (preg_match('/\btiba\b/u', $title)) {
            return false;
        }

        return preg_match('/\b(test|tes|toefl\s*itp|ept)\b/u', $title) === 1
            || preg_match('/\bofficial\s+ielts\b/u', $title) === 1;
    }
}
