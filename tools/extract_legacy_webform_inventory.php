<?php

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php extract_legacy_webform_inventory.php <dump.sql> [output.json]\n");
    exit(1);
}

$dumpPath = $argv[1];
$outputPath = $argv[2] ?? null;

if (!is_file($dumpPath) || !is_readable($dumpPath)) {
    fwrite(STDERR, "SQL dump is not readable: {$dumpPath}\n");
    exit(1);
}

$programs = [
    'ppb' => ['ppb', 'inggris', 'non_ingg', 'non_inggris'],
    'ppp' => ['ppp'],
    'bipa' => ['bipa'],
];

$forms = [];
$inWebformTable = false;
$reader = new SplFileObject($dumpPath, 'r');

while (!$reader->eof()) {
    $line = $reader->fgets();
    if ($line === false) {
        continue;
    }

    if (stripos($line, 'INSERT INTO `webform`') !== false) {
        $inWebformTable = true;
    }

    if ($inWebformTable) {
        preg_match_all("/\\('([a-z][a-z0-9_-]{2,})',\\s*\\d+\\)/i", $line, $matches);
        foreach ($matches[1] ?? [] as $machineName) {
            addForm($forms, $machineName, 'webform_table');
        }
    }

    if ($inWebformTable && str_contains($line, ';')) {
        $inWebformTable = false;
    }
}

foreach ($forms as $machineName => &$form) {
    $form['program'] = resolveProgram($machineName, $programs);
    $form['preferred'] = str_ends_with($machineName, '_new') || str_contains($machineName, '_new_');
}
unset($form);

uksort($forms, static function (string $left, string $right) use ($forms): int {
    return [$forms[$right]['preferred'], $left] <=> [$forms[$left]['preferred'], $right];
});

$result = [
    'generated_at' => date(DATE_ATOM),
    'source' => realpath($dumpPath),
    'selection_rule' => 'Keep PPB, PPP, and BIPA separate. Prefer machine names containing _new or ending in _new.',
    'forms' => array_values($forms),
];

$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
if ($outputPath !== null) {
    file_put_contents($outputPath, $json);
    fwrite(STDOUT, "Inventory written to {$outputPath}\n");
} else {
    fwrite(STDOUT, $json);
}

function addForm(array &$forms, string $machineName, string $source): void
{
    $machineName = strtolower(trim($machineName));
    if (!preg_match('/^[a-z][a-z0-9_-]{2,}$/', $machineName)) {
        return;
    }

    if (isset($forms[$machineName])) {
        $forms[$machineName]['sources'][] = $source;
        return;
    }

    $forms[$machineName] = [
        'machine_name' => $machineName,
        'sources' => [$source],
    ];
}

function resolveProgram(string $machineName, array $programs): string
{
    foreach ($programs as $program => $keywords) {
        foreach ($keywords as $keyword) {
            if (str_contains($machineName, $keyword)) {
                return $program;
            }
        }
    }

    return 'unclassified';
}