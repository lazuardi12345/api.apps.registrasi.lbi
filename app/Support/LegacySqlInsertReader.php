<?php

namespace App\Support;

use Generator;
use RuntimeException;

class LegacySqlInsertReader
{
    /**
     * @param array<int, string> $tables
     * @return Generator<array{table: string, row: array<string, string|null>}>
     */
    public function read(string $path, array $tables): Generator
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open SQL source: {$path}");
        }

        $schemas = [];
        $currentTable = null;

        try {
            while (($line = fgets($handle)) !== false) {
                if (preg_match('/^CREATE TABLE `([^`]+)`/', $line, $match) === 1) {
                    $currentTable = in_array($match[1], $tables, true) ? $match[1] : null;
                    if ($currentTable !== null) {
                        $schemas[$currentTable] = [];
                    }
                    continue;
                }

                if (
                    $currentTable !== null
                    && preg_match('/^\s*`([^`]+)`\s+/', $line, $match) === 1
                ) {
                    $schemas[$currentTable][] = $match[1];
                }

                if (
                    preg_match('/^INSERT INTO `([^`]+)`(?: \(([^)]*)\))? VALUES (.*);\\s*$/s', $line, $match) !== 1
                    || ! in_array($match[1], $tables, true)
                ) {
                    continue;
                }

                $table = $match[1];
                if (! isset($schemas[$table]) || $schemas[$table] === []) {
                    throw new RuntimeException("No CREATE TABLE schema found for {$table}.");
                }

                $columns = isset($match[2]) && $match[2] !== ''
                    ? array_map(static fn (string $column): string => trim($column, " `"), explode(',', $match[2]))
                    : $schemas[$table];

                foreach ($this->parseValues($match[3], $columns, $table) as $row) {
                    yield ['table' => $table, 'row' => $row];
                }
            }

            if (! feof($handle)) {
                throw new RuntimeException("Failed while reading SQL source: {$path}");
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param array<int, string> $columns
     * @return Generator<array<string, string|null>>
     */
    private function parseValues(string $values, array $columns, string $table): Generator
    {
        $rowValues = [];
        $value = '';
        $inTuple = false;
        $quoted = false;
        $length = strlen($values);

        for ($index = 0; $index < $length; $index++) {
            $character = $values[$index];

            if (! $inTuple) {
                if ($character === '(') {
                    $inTuple = true;
                    $rowValues = [];
                    $value = '';
                }
                continue;
            }

            if ($quoted && $character === '\\' && $index + 1 < $length) {
                $value .= $character . $values[++$index];
                continue;
            }

            if ($character === "'") {
                if ($quoted && ($values[$index + 1] ?? null) === "'") {
                    $value .= "''";
                    $index++;
                    continue;
                }
                $quoted = ! $quoted;
                $value .= $character;
                continue;
            }

            if (! $quoted && $character === ',') {
                $rowValues[] = $this->decodeValue($value);
                $value = '';
                continue;
            }

            if (! $quoted && $character === ')') {
                $rowValues[] = $this->decodeValue($value);
                if (count($rowValues) !== count($columns)) {
                    throw new RuntimeException(
                        "Unexpected column count while parsing {$table}: expected "
                        . count($columns) . ', got ' . count($rowValues) . '.'
                    );
                }
                yield array_combine($columns, $rowValues);
                $rowValues = [];
                $value = '';
                $inTuple = false;
                continue;
            }

            $value .= $character;
        }

        if ($inTuple || $quoted) {
            throw new RuntimeException("Unterminated SQL tuple while parsing {$table}.");
        }
    }

    private function decodeValue(string $value): ?string
    {
        $value = trim($value);
        if (strtoupper($value) === 'NULL') {
            return null;
        }

        if (strlen($value) >= 2 && $value[0] === "'" && $value[strlen($value) - 1] === "'") {
            $value = substr($value, 1, -1);
            $value = str_replace("''", "'", $value);

            return strtr($value, [
                '\\0' => "\0",
                '\\b' => "\x08",
                '\\n' => "\n",
                '\\r' => "\r",
                '\\t' => "\t",
                '\\Z' => "\x1a",
                '\\\\' => '\\',
                "\\'" => "'",
                '\\"' => '"',
                '\\%' => '%',
                '\\_' => '_',
            ]);
        }

        return $value;
    }
}
