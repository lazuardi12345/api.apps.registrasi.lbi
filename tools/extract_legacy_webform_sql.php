<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php extract_legacy_webform_sql.php <dump.sql> <output.sql>\n");
    exit(1);
}

$sourcePath = $argv[1];
$outputPath = $argv[2];
$targetTables = [
    'webform',
    'webform_submission',
    'webform_submission_data',
    'webform_submission_log',
];

if (!is_readable($sourcePath)) {
    fwrite(STDERR, "Legacy dump is not readable: {$sourcePath}\n");
    exit(1);
}

$reader = new SplFileObject($sourcePath, 'r');
$writer = fopen($outputPath, 'wb');
$activeTable = null;
$blocks = 0;

fwrite($writer, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");

while (!$reader->eof()) {
    $line = $reader->fgets();
    if ($line === false) {
        continue;
    }

    if ($activeTable === null) {
        foreach ($targetTables as $table) {
            if (preg_match('/(?:DROP TABLE IF EXISTS|CREATE TABLE|INSERT INTO)\\s+`?' . preg_quote($table, '/') . '`?/i', $line)) {
                $activeTable = $table;
                $blocks++;
                break;
            }
        }
    }

    if ($activeTable === null) {
        continue;
    }

    $line = str_replace('utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci', $line);
    fwrite($writer, $line);

    if (str_contains($line, 'UNLOCK TABLES;')) {
        $activeTable = null;
    }
}

fwrite($writer, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($writer);

fwrite(STDOUT, "Extracted {$blocks} Webform SQL blocks to {$outputPath}\n");