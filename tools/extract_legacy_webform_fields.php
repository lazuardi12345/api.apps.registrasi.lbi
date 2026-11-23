<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php extract_legacy_webform_fields.php <dump.sql> <inventory.json> [output.json]\n");
    exit(1);
}

$dumpPath = $argv[1];
$inventoryPath = $argv[2];
$outputPath = $argv[3] ?? null;

if (!is_readable($dumpPath) || !is_readable($inventoryPath)) {
    fwrite(STDERR, "Dump or inventory file is not readable.\n");
    exit(1);
}

$inventory = json_decode((string) file_get_contents($inventoryPath), true, 512, JSON_THROW_ON_ERROR);
$forms = [];

foreach ($inventory['forms'] as $form) {
    if (in_array($form['program'], ['ppb', 'ppp', 'bipa'], true)) {
        $forms[$form['machine_name']] = [
            'machine_name' => $form['machine_name'],
            'program' => $form['program'],
            'preferred' => $form['preferred'],
            'submission_rows' => 0,
            'fields' => [],
        ];
    }
}

$reader = new SplFileObject($dumpPath, 'r');
$inSubmissionData = false;
$buffer = '';

while (!$reader->eof()) {
    $line = $reader->fgets();
    if ($line === false) {
        continue;
    }

    if (stripos($line, 'INSERT INTO') !== false && stripos($line, 'webform_submission_data') !== false) {
        $inSubmissionData = true;
    }

    if (!$inSubmissionData) {
        continue;
    }

    $chunk = $buffer . $line;
    preg_match_all("/\\((\\d+),\\s*'([^']+)',\\s*'([^']+)'/", $chunk, $matches, PREG_SET_ORDER);
    $buffer = substr($chunk, -16384);

    foreach ($matches as $match) {
        $webformId = strtolower($match[2]);
        $fieldName = strtolower($match[3]);

        if (!isset($forms[$webformId]) || !preg_match('/^[a-z][a-z0-9_:-]*$/', $fieldName)) {
            continue;
        }

        $forms[$webformId]['submission_rows']++;
        $forms[$webformId]['fields'][$fieldName] = ($forms[$webformId]['fields'][$fieldName] ?? 0) + 1;
    }

    if ($inSubmissionData && str_contains($line, ';')) {
        $inSubmissionData = false;
        $buffer = '';
    }
}

foreach ($forms as &$form) {
    arsort($form['fields']);
    $form['field_count'] = count($form['fields']);
}
unset($form);

uksort($forms, static function (string $left, string $right) use ($forms): int {
    return [$forms[$right]['preferred'], $left] <=> [$forms[$left]['preferred'], $right];
});

$result = [
    'generated_at' => date(DATE_ATOM),
    'source' => realpath($dumpPath),
    'forms' => array_values($forms),
];

$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
if ($outputPath !== null) {
    file_put_contents($outputPath, $json);
    fwrite(STDOUT, "Field inventory written to {$outputPath}\n");
} else {
    fwrite(STDOUT, $json);
}