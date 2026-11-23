<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StageLegacyWebformData extends Command
{
    protected $signature = 'legacy:webforms-stage
        {--only=all : Import scope: webforms, submissions, data, or all}
        {--program=all : Program scope: all, ppb, ppp, bipa, or unclassified}
        {--limit=0 : Optional maximum number of rows for submissions or data}
        {--chunk=1000 : Number of field rows to read per query}';

    protected $description = 'Stage Webform definitions, submissions, and field values from the legacy database';

    public function handle(): int
    {
        $scope = strtolower((string) $this->option('only'));
        $program = strtolower((string) $this->option('program'));
        if (!in_array($scope, ['all', 'webforms', 'submissions', 'data'], true)) {
            $this->error('The --only option must be all, webforms, submissions, or data.');
            return self::FAILURE;
        }
        if (!in_array($program, ['all', 'ppb', 'ppp', 'bipa', 'unclassified'], true)) {
            $this->error('The --program option must be all, ppb, ppp, bipa, or unclassified.');
            return self::FAILURE;
        }

        $source = DB::connection('legacy_mysql');
        foreach (['webform', 'webform_submission', 'webform_submission_data'] as $table) {
            if (!$source->getSchemaBuilder()->hasTable($table)) {
                $this->error("Legacy table is missing: {$table}");
                return self::FAILURE;
            }
        }

        $limit = max(0, (int) $this->option('limit'));
        $chunkSize = max(100, (int) $this->option('chunk'));
        $webformIds = $this->resolveWebformIds($program);
        if ($scope === 'all' || $scope === 'webforms') {
            $this->stageWebforms($source);
        }
        if ($scope === 'all' || $scope === 'submissions') {
            $this->stageSubmissions($source, $limit, $webformIds);
        }
        if ($scope === 'all' || $scope === 'data') {
            $this->stageSubmissionData($source, $limit, $chunkSize, $webformIds);
        }

        return self::SUCCESS;
    }

    private function stageWebforms($source): void
    {
        $rows = [];
        foreach ($source->table('webform')->orderBy('webform_id')->cursor() as $form) {
            $program = $this->resolveProgram($form->webform_id);
            $rows[] = [
                'legacy_webform_id' => $form->webform_id,
                'program' => $program,
                'preferred' => $this->isPreferred($form->webform_id),
                'source' => 'lbifib_db_backup.sql',
                'metadata' => json_encode(['next_serial' => $form->next_serial], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($rows) >= 500) {
                $this->upsertWebforms($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $this->upsertWebforms($rows);
        }

        $this->info('Legacy Webform definitions staged.');
    }

    private function stageSubmissions($source, int $limit, ?array $webformIds): void
    {
        $query = $source->table('webform_submission')
            ->select(['sid', 'webform_id', 'uuid', 'created', 'completed', 'in_draft', 'uid'])
            ->orderBy('sid');
        if ($webformIds !== null) {
            $query->whereIn('webform_id', $webformIds);
        }
        if ($limit > 0) {
            $query->limit($limit);
        }
        $processed = 0;

        foreach ($query->cursor() as $submission) {
            DB::table('legacy_webform_submissions')->upsert([[
                'legacy_sid' => $submission->sid,
                'legacy_webform_id' => $submission->webform_id,
                'legacy_uid' => $submission->uid,
                'legacy_uuid' => $submission->uuid,
                'state' => $this->submissionState($submission),
                'submitted_at' => $submission->created ? date('Y-m-d H:i:s', $submission->created) : null,
                'raw_payload' => json_encode((array) $submission, JSON_THROW_ON_ERROR),
                'mapped' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]], ['legacy_sid'], ['legacy_webform_id', 'legacy_uid', 'legacy_uuid', 'state', 'submitted_at', 'raw_payload', 'updated_at']);

            $processed++;
            if ($processed % 1000 === 0) {
                $this->info("Submissions staged: {$processed}");
            }
            if ($limit > 0 && $processed >= $limit) {
                break;
            }
        }

        $this->info("Legacy submissions staged: {$processed}");
    }

    private function stageSubmissionData($source, int $limit, int $chunkSize, ?array $webformIds): void
    {
        $stagedQuery = DB::table('legacy_webform_submissions');
        if ($webformIds !== null) {
            $stagedQuery->whereIn('legacy_webform_id', $webformIds);
        }
        if ($limit > 0) {
            $stagedQuery->limit($limit);
        }

        if ($stagedQuery->exists()) {
            $this->stageDataForStagedSubmissions($source, $stagedQuery, $chunkSize);
            return;
        }

        if ($webformIds !== null) {
            foreach ($webformIds as $webformId) {
                $this->stageSubmissionDataForWebform($source, $limit, $chunkSize, $webformId);
            }

            return;
        }

        $this->stageSubmissionDataForWebform($source, $limit, $chunkSize);
    }

    private function stageDataForStagedSubmissions($source, $stagedQuery, int $chunkSize): void
    {
        $processed = 0;
        $totalSubmissions = 0;
        $sidBatchSize = max(10, min($chunkSize, 100));

        $stagedQuery->select(['legacy_sid', 'legacy_webform_id'])->orderBy('legacy_sid')->chunk($sidBatchSize, function ($submissions) use ($source, &$processed, &$totalSubmissions) {
            $sids = $submissions->pluck('legacy_sid')->all();
            $fields = $source->table('webform_submission_data')
                ->select(['webform_id', 'sid', 'name', 'property', 'delta', 'value'])
                ->whereIn('sid', $sids)
                ->get();

            if ($fields->isNotEmpty()) {
                $rows = [];
                foreach ($fields as $field) {
                    $rows[] = [
                        'legacy_sid' => $field->sid,
                        'legacy_webform_id' => $field->webform_id,
                        'field_name' => $field->name,
                        'property' => $field->property ?? '',
                        'delta' => $field->delta ?? 0,
                        'value' => $field->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                foreach (array_chunk($rows, 500) as $chunkRows) {
                    $this->upsertSubmissionData($chunkRows);
                }
                $processed += count($rows);
            }

            $totalSubmissions += count($submissions);
            $this->info("Staged data for {$totalSubmissions} submissions ({$processed} field rows)...");
        });

        $this->info("Completed staging data for {$totalSubmissions} submissions ({$processed} field rows).");
    }

    private function stageSubmissionDataForWebform($source, int $limit, int $chunkSize, ?string $webformId = null): void
    {
        $processed = 0;
        $last = null;

        while (true) {
            $query = $source->table('webform_submission_data')
                ->select(['webform_id', 'sid', 'name', 'property', 'delta', 'value'])
                ->orderBy('webform_id')
                ->orderBy('sid')
                ->orderBy('name')
                ->orderBy('property')
                ->orderBy('delta')
                ->limit($chunkSize);

            if ($webformId !== null) {
                $query->where('webform_id', $webformId);
            }

            if ($last !== null) {
                $query->where(function ($filter) use ($last, $webformId) {
                    if ($webformId === null) {
                        $filter->where('webform_id', '>', $last['webform_id'])
                            ->orWhere(function ($sameForm) use ($last) {
                                $sameForm->where('webform_id', $last['webform_id'])->where('sid', '>', $last['sid']);
                            });
                    } else {
                        $filter->where('sid', '>', $last['sid'])
                            ->orWhere(function ($sameSid) use ($last) {
                                $sameSid->where('sid', $last['sid'])->where('name', '>', $last['name']);
                            })
                            ->orWhere(function ($sameName) use ($last) {
                                $sameName->where('sid', $last['sid'])->where('name', $last['name'])->where('property', '>', $last['property']);
                            })
                            ->orWhere(function ($sameProperty) use ($last) {
                                $sameProperty->where('sid', $last['sid'])
                                    ->where('name', $last['name'])
                                    ->where('property', $last['property'])
                                    ->where('delta', '>', $last['delta']);
                            });
                    }
                });
            }

            $fields = $query->get();
            if ($fields->isEmpty()) {
                break;
            }

            $rows = [];
            foreach ($fields as $field) {
                $rows[] = [
                    'legacy_sid' => $field->sid,
                    'legacy_webform_id' => $field->webform_id,
                    'field_name' => $field->name,
                    'property' => $field->property,
                    'delta' => $field->delta,
                    'value' => $field->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $this->upsertSubmissionData($rows);
            $processed += count($rows);
            $lastField = $fields->last();
            $last = [
                'webform_id' => $lastField->webform_id,
                'sid' => $lastField->sid,
                'name' => $lastField->name,
                'property' => $lastField->property,
                'delta' => $lastField->delta,
            ];

            if ($processed % 10000 === 0) {
                $this->info("Submission data staged: {$processed}");
            }
            if ($limit > 0 && $processed >= $limit) {
                break;
            }
        }

        $this->info("Legacy submission data staged: {$processed}");
    }

    private function upsertWebforms(array $rows): void
    {
        DB::table('legacy_webforms')->upsert($rows, ['legacy_webform_id'], ['program', 'preferred', 'source', 'metadata', 'updated_at']);
    }

    private function resolveWebformIds(string $program): ?array
    {
        if ($program === 'all') {
            return null;
        }

        return DB::table('legacy_webforms')
            ->where('program', $program)
            ->pluck('legacy_webform_id')
            ->all();
    }

    private function upsertSubmissionData(array $rows): void
    {
        DB::table('legacy_webform_submission_data')->upsert($rows, ['legacy_sid', 'field_name', 'property', 'delta'], ['legacy_webform_id', 'value', 'updated_at']);
    }

    private function submissionState(object $submission): string
    {
        if ((int) $submission->in_draft === 1) {
            return 'draft';
        }

        return ((int) $submission->completed > 0) ? 'completed' : 'submitted';
    }

    private function isPreferred(string $machineName): bool
    {
        return str_ends_with($machineName, '_new') || str_contains($machineName, '_new_');
    }

    private function resolveProgram(string $machineName): string
    {
        $machineName = strtolower($machineName);
        if (str_contains($machineName, 'bipa')) {
            return 'bipa';
        }
        if (str_contains($machineName, 'ppp')) {
            return 'ppp';
        }
        if (str_contains($machineName, 'ppb') || str_contains($machineName, 'inggris') || str_contains($machineName, 'ingg')) {
            return 'ppb';
        }

        return 'unclassified';
    }
}