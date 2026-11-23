<?php

if ($argc < 3) {
    fwrite(STDERR, "Usage: php extract_legacy_services.php BACKUP.sql OUTPUT.json\n");
    exit(1);
}

$source = $argv[1];
$output = $argv[2];
if (!is_file($source)) exit("Backup file not found\n");

function tuples(string $line): array
{
    $marker = strpos($line, 'VALUES');
    if ($marker === false) return [];
    $result = []; $buffer = ''; $depth = 0; $quoted = false; $escaped = false;
    for ($i = $marker + 6, $length = strlen($line); $i < $length; $i++) {
        $character = $line[$i];
        if ($escaped) { if ($depth > 0) $buffer .= $character; $escaped = false; continue; }
        if ($character === '\\' && $quoted) { if ($depth > 0) $buffer .= $character; $escaped = true; continue; }
        if ($character === "'") $quoted = !$quoted;
        if (!$quoted && $character === '(') { $depth++; if ($depth === 1) $buffer = ''; }
        elseif ($depth > 0) $buffer .= $character;
        if (!$quoted && $character === ')') { $depth--; if ($depth === 0) $result[] = substr($buffer, 0, -1); }
    }
    return $result;
}

function parseTuple(string $raw): array
{
    return array_map(static function ($value) {
        $value = trim($value);
        return strtoupper($value) === 'NULL' ? null : stripcslashes($value);
    }, str_getcsv($raw, ',', "'", '\\'));
}

function tableFrom(string $line, string $keyword): ?string
{
    return preg_match('/' . preg_quote($keyword, '/') . ' `([^`]+)`/', $line, $match) ? $match[1] : null;
}

function clean($value): ?string
{
    if ($value === null) return null;
    $value = trim(str_replace(['\\r\\n', '\\n', '\\r'], ["\n", "\n", "\n"], (string) $value));
    return $value === '' ? null : $value;
}

$schemas = []; $currentTable = null; $rows = [];
$handle = fopen($source, 'rb');
while (($line = fgets($handle)) !== false) {
    $created = tableFrom($line, 'CREATE TABLE');
    if ($created !== null) {
        $currentTable = in_array($created, ['node_field_data', 'node__body'], true) ? $created : null;
        if ($currentTable) $schemas[$currentTable] = [];
        continue;
    }
    if ($currentTable && str_starts_with(ltrim($line), '`') && preg_match('/^\s*`([^`]+)`\s+/', $line, $match)) {
        $schemas[$currentTable][] = $match[1];
    }
    $inserted = tableFrom($line, 'INSERT INTO');
    if (!$inserted || !isset($schemas[$inserted])) continue;
    foreach (tuples($line) as $raw) {
        $row = array_combine($schemas[$inserted], parseTuple($raw));
        if ($row === false) continue;
        $id = $row['nid'] ?? $row['entity_id'] ?? null;
        if ($id !== null) $rows[$inserted][(string) $id][] = $row;
    }
}
fclose($handle);

$records = [];
foreach ($rows['node_field_data'] ?? [] as $nid => $nodeRows) {
    $node = $nodeRows[0] ?? [];
    if (!in_array($node['type'] ?? '', ['service', 'kelas'], true)) continue;
    $body = $rows['node__body'][$nid][0]['body_value'] ?? null;
    $records[] = [
        'legacy_nid' => ctype_digit((string) $nid) ? (int) $nid : null,
        'type' => $node['type'],
        'title' => clean($node['title'] ?? null),
        'status' => $node['status'] ?? null,
        'body' => clean($body),
        'body_length' => $body ? strlen($body) : 0,
        'source' => 'lbifib_db_backup.sql',
    ];
}
usort($records, static fn ($a, $b) => [$a['type'], strtolower($a['title'] ?? '')] <=> [$b['type'], strtolower($b['title'] ?? '')]);
if (!is_dir(dirname($output))) mkdir(dirname($output), 0777, true);
file_put_contents($output, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
$withBody = count(array_filter($records, static fn ($record) => $record['body_length'] > 0));
echo 'Extracted ' . count($records) . ' service/class nodes; ' . $withBody . " include body content\n";
echo "Wrote staging file: {$output}\n";
