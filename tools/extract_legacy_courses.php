<?php

if ($argc < 3) {
    fwrite(STDERR, "Usage: php extract_legacy_courses.php BACKUP.sql OUTPUT.json\n");
    exit(1);
}

$source = $argv[1];
$output = $argv[2];

if (!is_file($source)) {
    fwrite(STDERR, "Backup file not found: {$source}\n");
    exit(1);
}

function slug(string $value): string
{
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function classify(string $title): string
{
    $value = strtolower($title);
    if (str_contains($value, 'bipa') || str_contains($value, 'bahasa indonesia')) {
        return 'bipa';
    }
    if (str_contains($value, 'penerjemah') || str_contains($value, 'penerjemahan') || str_contains($value, 'interpretasi') || str_contains($value, 'jurubahasa')) {
        return 'ppp';
    }
    foreach (['english', 'inggris', 'grammar', 'writing', 'speaking', 'conversation', 'ibt', 'toefl', 'ielts'] as $keyword) {
        if (str_contains($value, $keyword)) {
            return 'ppb_english';
        }
    }
    return 'ppb_foreign';
}

function tuples(string $line): array
{
    $marker = strpos($line, 'VALUES');
    if ($marker === false) {
        return [];
    }

    $result = [];
    $buffer = '';
    $depth = 0;
    $quoted = false;
    $escaped = false;

    for ($i = $marker + 6, $length = strlen($line); $i < $length; $i++) {
        $character = $line[$i];
        if ($escaped) {
            if ($depth > 0) $buffer .= $character;
            $escaped = false;
            continue;
        }
        if ($character === '\\' && $quoted) {
            if ($depth > 0) $buffer .= $character;
            $escaped = true;
            continue;
        }
        if ($character === "'") $quoted = !$quoted;
        if (!$quoted && $character === '(') {
            $depth++;
            if ($depth === 1) $buffer = '';
        } elseif ($depth > 0) {
            $buffer .= $character;
        }
        if (!$quoted && $character === ')') {
            $depth--;
            if ($depth === 0) $result[] = substr($buffer, 0, -1);
        }
    }
    return $result;
}

function parseTuple(string $raw): array
{
    $values = str_getcsv($raw, ',', "'", '\\');
    return array_map(static function ($value) {
        $value = trim($value);
        return strtoupper($value) === 'NULL' ? null : stripcslashes($value);
    }, $values);
}

$records = [];
$handle = fopen($source, 'rb');
while (($line = fgets($handle)) !== false) {
    if (!str_contains($line, 'node_field_data') || !str_contains($line, 'VALUES')) continue;
    foreach (tuples($line) as $raw) {
        $values = parseTuple($raw);
        if (count($values) < 7 || trim((string) ($values[2] ?? '')) !== 'kursus') continue;
        $title = trim((string) ($values[4] ?? ''));
        $courseSlug = slug($title);
        if ($title === '' || $courseSlug === '') continue;
        $records[$courseSlug] = [
            'legacy_nid' => ctype_digit(trim((string) ($values[0] ?? ''))) ? (int) $values[0] : null,
            'title' => $title,
            'slug' => $courseSlug,
            'division' => classify($title),
            'source' => 'lbifib_db_backup.sql',
        ];
    }
}
fclose($handle);

$records = array_values($records);
usort($records, static fn ($left, $right) => [$left['division'], strtolower($left['title'])] <=> [$right['division'], strtolower($right['title'])]);

if (!is_dir(dirname($output))) mkdir(dirname($output), 0777, true);
file_put_contents($output, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
echo 'Extracted ' . count($records) . " unique course records to {$output}\n";
