<?php

if ($argc < 3) {
    fwrite(STDERR, "Usage: php extract_legacy_course_details.php BACKUP.sql OUTPUT.json\n");
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
    if (str_contains($value, 'bipa') || str_contains($value, 'bahasa indonesia')) return 'bipa';
    if (str_contains($value, 'penerjemah') || str_contains($value, 'penerjemahan') || str_contains($value, 'interpretasi') || str_contains($value, 'jurubahasa')) return 'ppp';
    if (str_contains($value, 'toefl') || str_contains($value, 'ielts') || str_contains($value, 'tes bahasa') || str_contains($value, 'test bahasa')) return 'testing';
    if (str_contains($value, 'english') || str_contains($value, 'inggris') || str_contains($value, 'grammar') || str_contains($value, 'writing') || str_contains($value, 'speaking') || str_contains($value, 'conversation')) return 'ppb_english';
    return 'ppb_foreign';
}

function cleanValue($value): ?string
{
    if ($value === null) return null;
    $value = str_replace(['\\r\\n', '\\n', '\\r'], ["\n", "\n", "\n"], (string) $value);
    $value = trim($value);
    return $value === '' ? null : $value;
}

function tuples(string $line): array
{
    $marker = strpos($line, 'VALUES');
    if ($marker === false) return [];
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

function tableFrom(string $line, string $keyword): ?string
{
    if (preg_match('/' . preg_quote($keyword, '/') . ' `([^`]+)`/', $line, $match)) return $match[1];
    return null;
}

function columnFrom(string $line): ?string
{
    return preg_match('/^\s*`([^`]+)`\s+/', $line, $match) ? $match[1] : null;
}

function relevant(string $table): bool
{
    if (in_array($table, ['node_field_data', 'node__body', 'file_managed'], true)) return true;
    return str_starts_with($table, 'node__field_') && preg_match('/hasil_belajar|pengajar|level|image|link|lokasi|period|program/', $table) === 1;
}

function firstField(array $row, array $prefixes)
{
    foreach ($row as $key => $value) {
        foreach ($prefixes as $prefix) {
            if (($key === $prefix || str_starts_with($key, $prefix . '_')) && $value !== null && $value !== '') return $value;
        }
    }
    return null;
}

$schemas = [];
$currentTable = null;
$rows = [];
$fileUris = [];
$handle = fopen($source, 'rb');

while (($line = fgets($handle)) !== false) {
    $created = tableFrom($line, 'CREATE TABLE');
    if ($created !== null) {
        $currentTable = relevant($created) ? $created : null;
        if ($currentTable !== null) $schemas[$currentTable] = [];
        continue;
    }

    if ($currentTable !== null && str_starts_with(ltrim($line), '`')) {
        $column = columnFrom($line);
        if ($column !== null) $schemas[$currentTable][] = $column;
    }

    $inserted = tableFrom($line, 'INSERT INTO');
    if ($inserted === null || !array_key_exists($inserted, $schemas)) continue;

    foreach (tuples($line) as $raw) {
        $row = array_combine($schemas[$inserted], parseTuple($raw));
        if ($row === false) continue;
        $entityId = $row['nid'] ?? $row['entity_id'] ?? null;

        if ($inserted === 'file_managed') {
            if (!empty($row['fid']) && !empty($row['uri'])) $fileUris[(string) $row['fid']] = $row['uri'];
            continue;
        }
        if ($entityId !== null) $rows[$inserted][(string) $entityId][] = $row;
    }
}
fclose($handle);

$records = [];
foreach ($rows['node_field_data'] ?? [] as $nid => $nodeRows) {
    $node = null;
    foreach ($nodeRows as $candidate) {
        if (($candidate['type'] ?? null) === 'kursus') {
            $node = $candidate;
            break;
        }
    }
    if ($node === null) continue;

    $title = cleanValue($node['title'] ?? null);
    $courseSlug = slug($title ?? '');
    if ($title === null || $courseSlug === '') continue;

    $fieldValue = static function (string $fragment) use (&$rows, $nid) {
        $table = null;
        foreach (array_keys($rows) as $name) {
            if ($name === 'node__field_' . $fragment || str_starts_with($name, 'node__field_' . $fragment . '_')) {
                $table = $name;
                break;
            }
        }
        if ($table === null) return null;
        return firstField(($rows[$table][$nid] ?? [[]])[0], [$fragment, 'field_' . $fragment]);
    };

    $body = firstField(($rows['node__body'][$nid] ?? [[]])[0], ['body']);
    $imageFid = $fieldValue('image');
    $records[$courseSlug] = [
        'legacy_nid' => ctype_digit((string) $nid) ? (int) $nid : null,
        'title' => $title,
        'slug' => $courseSlug,
        'division' => classify($title),
        'summary' => $body !== null ? mb_substr(cleanValue($body), 0, 500) : null,
        'description' => cleanValue($body),
        'learning_outcomes' => cleanValue($fieldValue('hasil_belajar')),
        'instructor_info' => cleanValue($fieldValue('pengajar')),
        'level' => cleanValue($fieldValue('level')),
        'image_url' => $imageFid !== null ? ($fileUris[(string) $imageFid] ?? null) : null,
        'location' => cleanValue($fieldValue('lokasi')),
        'period' => cleanValue($fieldValue('period')),
        'registration_url' => cleanValue($fieldValue('link')),
        'source' => 'lbifib_db_backup.sql',
    ];
}

$records = array_values($records);
usort($records, static fn ($left, $right) => [$left['division'], strtolower($left['title'])] <=> [$right['division'], strtolower($right['title'])]);
if (!is_dir(dirname($output))) mkdir(dirname($output), 0777, true);
file_put_contents($output, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
$withBody = count(array_filter($records, static fn ($record) => !empty($record['description'])));
echo 'Extracted ' . count($records) . ' course records; ' . $withBody . " include body content\n";
echo "Wrote staging file: {$output}\n";
