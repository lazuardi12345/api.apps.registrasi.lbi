<?php

namespace App\Console\Commands;

use App\Support\LegacySqlInsertReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ImportLegacyImportFolder extends Command
{
    protected $signature = 'legacy:imports
        {--directory=../private-backend-data/legacy-imports : Directory containing legacy import sources}
        {--sql-file= : Optional path to the legacy Webform SQL source}
        {--dry-run : Validate and report without writing to the database}';

    protected $description = 'Import legacy source artifacts directly into Laravel database tables';

    private const WEBFORM_SQL_TABLES = [
        'webform',
        'webform_submission',
        'webform_submission_data',
        'webform_submission_log',
    ];

    public function handle(LegacySqlInsertReader $reader): int
    {
        $directory = base_path((string) $this->option('directory'));
        $sqlFile = (string) $this->option('sql-file');
        $sqlFile = $sqlFile !== '' ? (realpath($sqlFile) ?: base_path($sqlFile)) : null;
        $files = $this->loadImportFiles($directory, $sqlFile);
        $this->validateDestinationTables();

        $this->info(sprintf(
            'Validated %d legacy content records, %d taxonomy terms, %d mapping decisions, %d Webform inventory records.',
            count($files['services']),
            count($files['taxonomy']),
            count($files['mappings']),
            count($files['inventory']['forms'])
        ));

        $sqlCounts = $this->processWebformSql($reader, $files['sql'], true, $files);
        $this->info(sprintf(
            'Webform SQL contains %d definitions, %d submissions, %d field values, and %d logs.',
            $sqlCounts['webform'],
            $sqlCounts['webform_submission'],
            $sqlCounts['webform_submission_data'],
            $sqlCounts['webform_submission_log']
        ));

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($files): void {
            $this->importSiteSettings($files['settings']);
            $this->importSourceContent($files['services']);
            $this->importTaxonomyTerms($files['taxonomy']);
            $this->importCatalogMappings($files['mappings']);
            $this->importWebformInventory($files['inventory'], $files['fields']);
            $this->importApplicationServices($files['services']);
            $this->importCatalogDescriptions($files['services'], $files['mapping_report']);
            $this->resolveExistingTaxonomyValues();
        });

        $this->processWebformSql($reader, $files['sql'], false, $files);
        $this->info('Legacy source data has been imported into Laravel database tables.');

        return self::SUCCESS;
    }

    private function loadImportFiles(string $directory, ?string $sqlFile = null): array
    {
        $names = [
            'settings' => 'site_settings.json',
            'services' => 'legacy_services_and_classes.json',
            'mapping_report' => 'legacy_service_catalog_mapping.json',
            'manual_mapping' => 'manual_catalog_mapping.json',
            'taxonomy' => 'legacy_taxonomy_terms.json',
            'taxonomy_by_vid' => 'legacy_taxonomy_terms_by_vid.json',
            'inventory' => 'legacy_webform_inventory.json',
            'fields' => 'legacy_webform_fields.json',
            'sql' => 'legacy_webform_subset.sql',
        ];
        $data = [];

        foreach ($names as $key => $name) {
            $path = $key === 'sql' && $sqlFile !== null
                ? $sqlFile
                : $directory . DIRECTORY_SEPARATOR . $name;
            if (! is_file($path) || ! is_readable($path)) {
                throw new RuntimeException("Required import source is not readable: {$path}");
            }

            if ($key === 'sql') {
                $data[$key] = $path;
                continue;
            }

            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                throw new RuntimeException("Import source has an invalid JSON root: {$path}");
            }
            $data[$key] = $decoded;
        }

        if (
            ! isset($data['inventory']['forms'])
            || ! isset($data['fields']['forms'])
            || ! isset($data['mapping_report']['matches'], $data['mapping_report']['unmatched'])
        ) {
            throw new RuntimeException('One or more legacy import JSON structures are invalid.');
        }

        $data['taxonomy'] = $this->mergeTaxonomySources(
            $data['taxonomy'],
            $data['taxonomy_by_vid']
        );
        $data['mappings'] = $this->normalizeMappings(
            $data['mapping_report'],
            $data['manual_mapping']
        );

        return $data;
    }

    private function validateDestinationTables(): void
    {
        foreach ([
            'services',
            'course_catalogs',
            'course_schedules',
            'site_settings',
            'legacy_webforms',
            'legacy_webform_submissions',
            'legacy_webform_submission_data',
            'legacy_taxonomy_terms',
            'legacy_catalog_mappings',
            'legacy_source_content',
            'legacy_webform_fields',
            'legacy_webform_submission_logs',
        ] as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                throw new RuntimeException("Destination table is missing: {$table}. Run php artisan migrate first.");
            }
        }
    }

    private function mergeTaxonomySources(array $flatTerms, array $termsByVocabulary): array
    {
        $terms = [];
        $knownIds = [];
        foreach ($termsByVocabulary as $vocabulary => $vocabularyTerms) {
            foreach ($vocabularyTerms as $legacyId => $name) {
                $legacyId = (int) $legacyId;
                $knownIds[$legacyId] = true;
                $terms[$legacyId . ':' . $vocabulary] = [
                    'legacy_tid' => $legacyId,
                    'vocabulary' => (string) $vocabulary,
                    'term_key' => $legacyId . ':' . $vocabulary,
                    'name' => trim((string) $name),
                ];
            }
        }

        foreach ($flatTerms as $legacyId => $name) {
            $legacyId = (int) $legacyId;
            if (! isset($knownIds[$legacyId])) {
                $terms[$legacyId . ':*'] = [
                    'legacy_tid' => $legacyId,
                    'vocabulary' => null,
                    'term_key' => $legacyId . ':*',
                    'name' => trim((string) $name),
                ];
            }
        }

        return array_values($terms);
    }

    private function normalizeMappings(array $report, array $manual): array
    {
        $mappings = [];
        foreach ($report['matches'] as $match) {
            $mappings[] = [
                'mapping_key' => 'service:' . $match['catalog_id'] . ':' . $match['legacy_nid'],
                'mapping_source' => 'service_catalog',
                'source_catalog_id' => (int) $match['catalog_id'],
                'legacy_nid' => (int) $match['legacy_nid'],
                'catalog_slug' => Str::slug((string) $match['catalog_title']),
                'catalog_title' => $match['catalog_title'],
                'legacy_title' => $match['legacy_title'],
                'match_type' => $match['match_type'],
                'confidence' => null,
                'body_length' => isset($match['body_length']) ? (int) $match['body_length'] : null,
                'note' => null,
                'metadata' => json_encode($match, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ];
        }

        foreach ($report['unmatched'] as $unmatched) {
            $mappings[] = [
                'mapping_key' => 'service-unmatched:' . $unmatched['catalog_id'],
                'mapping_source' => 'service_catalog',
                'source_catalog_id' => (int) $unmatched['catalog_id'],
                'legacy_nid' => null,
                'catalog_slug' => $unmatched['slug'] ?? null,
                'catalog_title' => $unmatched['catalog_title'] ?? null,
                'legacy_title' => null,
                'match_type' => 'unmatched',
                'confidence' => null,
                'body_length' => null,
                'note' => null,
                'metadata' => json_encode($unmatched, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ];
        }

        foreach ($manual as $index => $match) {
            $mappings[] = [
                'mapping_key' => 'manual:' . $index . ':' . $match['catalog_id'] . ':' . $match['legacy_nid'],
                'mapping_source' => 'manual_catalog',
                'source_catalog_id' => (int) $match['catalog_id'],
                'legacy_nid' => (int) $match['legacy_nid'],
                'catalog_slug' => null,
                'catalog_title' => null,
                'legacy_title' => null,
                'match_type' => $match['confidence'] ?? null,
                'confidence' => $match['confidence'] ?? null,
                'body_length' => null,
                'note' => $match['note'] ?? null,
                'metadata' => json_encode($match, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ];
        }

        return $mappings;
    }

    private function processWebformSql(
        LegacySqlInsertReader $reader,
        string $path,
        bool $dryRun,
        array $files
    ): array {
        $counts = array_fill_keys(self::WEBFORM_SQL_TABLES, 0);
        $batches = array_fill_keys(self::WEBFORM_SQL_TABLES, []);
        $batchBytes = array_fill_keys(self::WEBFORM_SQL_TABLES, 0);
        $batchSizes = [
            'webform' => 250,
            'webform_submission' => 250,
            'webform_submission_data' => 500,
            'webform_submission_log' => 250,
        ];
        $maxBatchBytes = 1_000_000;

        foreach ($reader->read($path, self::WEBFORM_SQL_TABLES) as $parsed) {
            $table = $parsed['table'];
            $row = $parsed['row'];
            $counts[$table]++;

            if ($dryRun) {
                continue;
            }

            $destination = match ($table) {
                'webform' => $this->webformRow($row, $files),
                'webform_submission' => $this->submissionRow($row),
                'webform_submission_data' => $this->submissionDataRow($row),
                'webform_submission_log' => $this->submissionLogRow($row),
            };
            $rowBytes = array_sum(array_map(
                static fn (mixed $value): int => strlen((string) $value),
                $destination
            )) + (count($destination) * 16);
            if ($batches[$table] !== [] && $batchBytes[$table] + $rowBytes > $maxBatchBytes) {
                $this->upsertSqlBatch($table, $batches[$table]);
                $batches[$table] = [];
                $batchBytes[$table] = 0;
            }
            $batches[$table][] = $destination;
            $batchBytes[$table] += $rowBytes;

            if (
                count($batches[$table]) >= $batchSizes[$table]
                || $batchBytes[$table] >= $maxBatchBytes
            ) {
                $this->upsertSqlBatch($table, $batches[$table]);
                $batches[$table] = [];
                $batchBytes[$table] = 0;
            }
        }

        if (! $dryRun) {
            foreach ($batches as $table => $rows) {
                if ($rows !== []) {
                    $this->upsertSqlBatch($table, $rows);
                }
            }
        }

        return $counts;
    }

    private function webformRow(array $row, array $files): array
    {
        $formId = (string) $row['webform_id'];
        $inventoryForm = collect($files['inventory']['forms'])
            ->firstWhere('machine_name', $formId);
        $fieldForm = collect($files['fields']['forms'])
            ->firstWhere('machine_name', $formId);
        $metadata = [
            'next_serial' => isset($row['next_serial']) ? (int) $row['next_serial'] : null,
            'inventory_sources' => $inventoryForm['sources'] ?? [],
            'field_count' => (int) ($fieldForm['field_count'] ?? 0),
            'fields_source_generated_at' => $files['fields']['generated_at'] ?? null,
        ];

        return [
            'legacy_webform_id' => $formId,
            'program' => $inventoryForm['program'] ?? $this->programForWebform($formId),
            'preferred' => (bool) ($inventoryForm['preferred'] ?? $this->isPreferredWebform($formId)),
            'source' => 'lbifib_db_backup.sql',
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function submissionRow(array $row): array
    {
        $safePayload = [];
        foreach ([
            'langcode', 'serial', 'completed', 'changed', 'in_draft',
            'current_page', 'entity_type', 'entity_id', 'locked', 'sticky', 'notes',
        ] as $key) {
            $safePayload[$key] = $row[$key] ?? null;
        }
        $completed = (int) ($row['completed'] ?? 0);
        $inDraft = (int) ($row['in_draft'] ?? 0) === 1;

        return [
            'legacy_sid' => (int) $row['sid'],
            'legacy_webform_id' => (string) $row['webform_id'],
            'legacy_uid' => isset($row['uid']) && $row['uid'] !== null ? (int) $row['uid'] : null,
            'legacy_uuid' => $row['uuid'] ?: null,
            'state' => $inDraft ? 'draft' : ($completed > 0 ? 'completed' : 'submitted'),
            'submitted_at' => $this->legacyTimestamp($row['created'] ?? null),
            'raw_payload' => json_encode($safePayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
            'created_at' => now(),
        ];
    }

    private function submissionDataRow(array $row): array
    {
        return [
            'legacy_sid' => (int) $row['sid'],
            'legacy_webform_id' => (string) $row['webform_id'],
            'field_name' => (string) $row['name'],
            'property' => (string) ($row['property'] ?? ''),
            'delta' => (int) ($row['delta'] ?? 0),
            'value' => $row['value'],
            'updated_at' => now(),
            'created_at' => now(),
        ];
    }

    private function submissionLogRow(array $row): array
    {
        return [
            'legacy_lid' => (int) $row['lid'],
            'legacy_webform_id' => (string) $row['webform_id'],
            'legacy_sid' => isset($row['sid']) && $row['sid'] !== null ? (int) $row['sid'] : null,
            'handler_id' => $row['handler_id'] ?: null,
            'legacy_uid' => isset($row['uid']) && $row['uid'] !== null ? (int) $row['uid'] : null,
            'operation' => $row['operation'] ?: null,
            'message' => $row['message'],
            'variables' => $row['variables'],
            'data' => $row['data'],
            'logged_at' => $this->legacyTimestamp($row['timestamp'] ?? null),
            'updated_at' => now(),
            'created_at' => now(),
        ];
    }

    private function upsertSqlBatch(string $table, array $rows): void
    {
        $uniqueBy = match ($table) {
            'webform' => ['legacy_webform_id'],
            'webform_submission' => ['legacy_sid'],
            'webform_submission_data' => ['legacy_sid', 'field_name', 'property', 'delta'],
            'webform_submission_log' => ['legacy_lid'],
        };
        $updateColumns = array_values(array_diff(array_keys($rows[0]), $uniqueBy, ['created_at']));
        $destination = match ($table) {
            'webform' => 'legacy_webforms',
            'webform_submission' => 'legacy_webform_submissions',
            'webform_submission_data' => 'legacy_webform_submission_data',
            'webform_submission_log' => 'legacy_webform_submission_logs',
        };

        DB::table($destination)->upsert($rows, $uniqueBy, $updateColumns);
    }

    private function importSiteSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $exists = DB::table('site_settings')->where('setting_key', (string) $key)->exists();
            if (! $exists) {
                DB::table('site_settings')->insert([
                    'setting_key' => (string) $key,
                    'value' => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function importSourceContent(array $records): void
    {
        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                'source' => 'legacy_services_and_classes',
                'legacy_nid' => (int) $record['legacy_nid'],
                'record_type' => (string) $record['type'],
                'title' => $record['title'] ?? null,
                'is_active' => (string) ($record['status'] ?? '0') === '1',
                'payload' => json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('legacy_source_content')->upsert(
                $chunk,
                ['source', 'legacy_nid'],
                ['record_type', 'title', 'is_active', 'payload', 'updated_at']
            );
        }
    }

    private function importTaxonomyTerms(array $terms): void
    {
        $rows = array_map(static fn (array $term): array => $term + [
            'created_at' => now(),
            'updated_at' => now(),
        ], $terms);

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('legacy_taxonomy_terms')->upsert(
                $chunk,
                ['term_key'],
                ['legacy_tid', 'vocabulary', 'name', 'updated_at']
            );
        }
    }

    private function importCatalogMappings(array $mappings): void
    {
        $rows = array_map(static fn (array $mapping): array => $mapping + [
            'created_at' => now(),
            'updated_at' => now(),
        ], $mappings);

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('legacy_catalog_mappings')->upsert(
                $chunk,
                ['mapping_key'],
                array_values(array_diff(array_keys($chunk[0]), ['mapping_key', 'created_at']))
            );
        }
    }

    private function importWebformInventory(array $inventory, array $fieldData): void
    {
        $fieldsByForm = collect($fieldData['forms'])->keyBy('machine_name');
        $rows = [];
        foreach ($inventory['forms'] as $form) {
            $machineName = (string) $form['machine_name'];
            $fieldForm = $fieldsByForm->get($machineName, []);
            $metadata = [
                'inventory_sources' => $form['sources'] ?? [],
                'field_count' => (int) ($fieldForm['field_count'] ?? 0),
                'fields_source_generated_at' => $fieldData['generated_at'] ?? null,
            ];
            $rows[] = [
                'legacy_webform_id' => $machineName,
                'program' => $form['program'] ?? $this->programForWebform($machineName),
                'preferred' => (bool) ($form['preferred'] ?? $this->isPreferredWebform($machineName)),
                'source' => 'lbifib_db_backup.sql',
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('legacy_webforms')->upsert(
                $chunk,
                ['legacy_webform_id'],
                ['program', 'preferred', 'source', 'metadata', 'updated_at']
            );
        }

        foreach ($fieldData['forms'] as $form) {
            $formId = (string) $form['machine_name'];
            $fieldDefinitions = $form['fields'] ?? [];
            foreach ($fieldDefinitions as $name => $configuration) {
                $fieldName = (string) (is_array($configuration) && isset($configuration['name'])
                    ? $configuration['name']
                    : $name);
                DB::table('legacy_webform_fields')->updateOrInsert(
                    ['legacy_webform_id' => $formId, 'field_name' => $fieldName],
                    [
                        'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }

    private function importApplicationServices(array $records): void
    {
        $rootDefinitions = [
            129 => ['slug' => 'program-bipa-129', 'icon' => 'bipa', 'menu_category' => 'bipa', 'menu_order' => 1],
            127 => ['slug' => 'program-pelayanan-bahasa-127', 'icon' => 'ppb', 'menu_category' => 'ppb_english', 'menu_order' => 2],
            1283 => ['slug' => 'program-penerjemahan-dan-penjurubahasaan-1283', 'icon' => 'ppp', 'menu_category' => 'ppp', 'menu_order' => 3],
        ];
        $menuChildren = [
            264 => ['parent' => 129, 'category' => 'bipa_services', 'order' => 1],
            262 => ['parent' => 129, 'category' => 'bipa_services', 'order' => 2],
            263 => ['parent' => 129, 'category' => 'bipa_services', 'order' => 3],
            7983 => ['parent' => 129, 'category' => 'bipa_services', 'order' => 4],
            279 => ['parent' => 1283, 'category' => 'ppp', 'order' => 1],
            280 => ['parent' => 1283, 'category' => 'ppp', 'order' => 2],
            281 => ['parent' => 1283, 'category' => 'ppp', 'order' => 3],
            282 => ['parent' => 1283, 'category' => 'ppp', 'order' => 4],
        ];
        $recordsById = collect($records)->keyBy('legacy_nid');

        foreach ($rootDefinitions as $legacyId => $definition) {
            $record = $recordsById->get($legacyId);
            if (! $record) {
                throw new RuntimeException("Missing legacy root service record {$legacyId}.");
            }

            $existing = DB::table('services')
                ->where('legacy_nid', $legacyId)
                ->orWhere('slug', $definition['slug'])
                ->first();
            $body = trim((string) ($record['body'] ?? ''));
            $attributes = [
                'legacy_nid' => $legacyId,
                'title' => $record['title'],
                'slug' => $definition['slug'],
                'body' => $existing && trim((string) $existing->body) !== ''
                    ? $existing->body
                    : ($body !== '' ? $body : null),
                'summary' => $existing && trim((string) $existing->summary) !== ''
                    ? $existing->summary
                    : ($body !== '' ? Str::limit(trim(strip_tags($body)), 240) : null),
                'icon' => $existing?->icon ?: $definition['icon'],
                'menu_category' => $existing?->menu_category ?: $definition['menu_category'],
                'menu_order' => $existing?->menu_order ?: $definition['menu_order'],
                'is_active' => $existing?->is_active ?? ((string) ($record['status'] ?? '0') === '1'),
                'updated_at' => now(),
            ];
            if ($existing) {
                DB::table('services')->where('id', $existing->id)->update($attributes);
            } else {
                DB::table('services')->insert($attributes + [
                    'created_at' => now(),
                    'image_url' => null,
                ]);
            }
        }

        $parentIds = DB::table('services')
            ->whereIn('legacy_nid', [129, 127, 1283])
            ->pluck('id', 'legacy_nid');

        foreach ($menuChildren as $legacyId => $child) {
            $record = $recordsById->get($legacyId);
            if (! $record || (string) ($record['status'] ?? '0') !== '1') {
                continue;
            }
            $existing = DB::table('services')->where('legacy_nid', $legacyId)->first();
            $title = trim((string) $record['title']);
            $slug = $existing?->slug ?: Str::slug($title);
            if (! $existing && DB::table('services')->where('slug', $slug)->exists()) {
                $slug .= '-' . $legacyId;
            }
            $body = trim((string) ($record['body'] ?? ''));
            $attributes = [
                'legacy_nid' => $legacyId,
                'parent_service_id' => $parentIds[$child['parent']] ?? null,
                'title' => $existing?->title ?: $title,
                'slug' => $slug,
                'body' => $existing && trim((string) $existing->body) !== ''
                    ? $existing->body
                    : ($body !== '' ? $body : null),
                'summary' => $existing && trim((string) $existing->summary) !== ''
                    ? $existing->summary
                    : ($body !== '' ? Str::limit(trim(strip_tags($body)), 160) : null),
                'menu_category' => $child['category'],
                'menu_order' => $child['order'],
                'is_active' => true,
                'updated_at' => now(),
            ];
            if ($existing) {
                DB::table('services')->where('id', $existing->id)->update($attributes);
            } else {
                DB::table('services')->insert($attributes + [
                    'created_at' => now(),
                    'icon' => null,
                    'image_url' => null,
                ]);
            }
        }

        $registrationRecord = $recordsById->get(263);
        if ($registrationRecord) {
            $serviceId = $parentIds[127] ?? null;
            if ($serviceId) {
                $title = trim((string) $registrationRecord['title']);
                $slug = Str::slug($title);
                $existing = DB::table('course_catalogs')->where('legacy_nid', 263)->first();
                if (! $existing) {
                    $existing = DB::table('course_catalogs')->where('slug', $slug)->first();
                }
                $body = trim((string) ($registrationRecord['body'] ?? ''));
                $values = [
                    'legacy_nid' => 263,
                    'service_id' => $serviceId,
                    'title' => $existing?->title ?: $title,
                    'slug' => $existing?->slug ?: $slug,
                    'summary' => $existing && trim((string) $existing->summary) !== ''
                        ? $existing->summary
                        : ($body !== '' ? Str::limit(trim(strip_tags($body)), 500) : null),
                    'description' => $existing && trim((string) $existing->description) !== ''
                        ? $existing->description
                        : ($body !== '' ? $body : null),
                    'division' => 'ppb',
                    'category' => 'registration_info',
                    'is_active' => true,
                    'sort_order' => $existing?->sort_order ?? 990,
                    'updated_at' => now(),
                ];
                if ($existing) {
                    DB::table('course_catalogs')->where('id', $existing->id)->update($values);
                } else {
                    DB::table('course_catalogs')->insert($values + ['created_at' => now()]);
                }
            }
        }

        $serviceIds = [
            'bipa' => $parentIds[129] ?? null,
            'ppb_english' => $parentIds[127] ?? null,
            'ppp' => $parentIds[1283] ?? null,
        ];
        foreach ($serviceIds as $division => $serviceId) {
            if ($serviceId) {
                DB::table('course_schedules')
                    ->where('division', $division)
                    ->whereNull('service_id')
                    ->update(['service_id' => $serviceId]);
            }
        }
    }

    private function importCatalogDescriptions(array $records, array $mappingReport): void
    {
        $recordsById = collect($records)->keyBy('legacy_nid');
        foreach ($mappingReport['matches'] as $mapping) {
            if (($mapping['match_type'] ?? null) !== 'exact') {
                continue;
            }
            $record = $recordsById->get($mapping['legacy_nid']);
            $body = trim((string) ($record['body'] ?? ''));
            if (! $record || $body === '') {
                continue;
            }

            $slug = Str::slug((string) $mapping['catalog_title']);
            $catalogs = DB::table('course_catalogs')
                ->where('title', $mapping['catalog_title'])
                ->orWhere('slug', $slug)
                ->get(['id', 'summary', 'description']);
            if ($catalogs->count() !== 1) {
                continue;
            }

            $catalog = $catalogs->first();
            $updates = ['updated_at' => now()];
            if (trim((string) $catalog->description) === '') {
                $updates['description'] = $body;
            }
            if (trim((string) $catalog->summary) === '') {
                $updates['summary'] = Str::limit(trim(strip_tags($body)), 500);
            }
            if (count($updates) > 1) {
                DB::table('course_catalogs')->where('id', $catalog->id)->update($updates);
            }
        }
    }

    private function resolveExistingTaxonomyValues(): void
    {
        $terms = DB::table('legacy_taxonomy_terms')
            ->orderBy('vocabulary')
            ->get(['legacy_tid', 'name'])
            ->unique('legacy_tid')
            ->pluck('name', 'legacy_tid');
        if ($terms->isEmpty()) {
            return;
        }

        foreach ([
            ['course_enrollments', 'course_name'],
            ['course_enrollments', 'level_name'],
            ['payment_confirmations', 'course_name'],
            ['payment_confirmations', 'payment_for'],
        ] as [$table, $column]) {
            if (! \Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
                continue;
            }
            $numericValues = DB::table($table)
                ->distinct()
                ->pluck($column)
                ->filter(static fn ($value) => ctype_digit((string) $value));
            foreach ($numericValues as $legacyId) {
                $name = $terms->get((int) $legacyId);
                if ($name !== null) {
                    DB::table($table)->where($column, $legacyId)->update([$column => $name]);
                }
            }
        }
    }

    private function programForWebform(string $machineName): string
    {
        $name = strtolower($machineName);
        if (str_contains($name, 'bipa')) {
            return 'bipa';
        }
        if (str_contains($name, 'ppp')) {
            return 'ppp';
        }
        if (str_contains($name, 'ppb') || str_contains($name, 'inggris') || str_contains($name, 'ingg')) {
            return 'ppb';
        }

        return 'unclassified';
    }

    private function isPreferredWebform(string $machineName): bool
    {
        return str_ends_with($machineName, '_new') || str_contains($machineName, '_new_');
    }

    private function legacyTimestamp(mixed $value): ?string
    {
        $timestamp = (int) $value;

        return $timestamp >= 31_536_000 ? date('Y-m-d H:i:s', $timestamp) : null;
    }
}
