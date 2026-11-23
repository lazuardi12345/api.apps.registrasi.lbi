<?php

namespace Tests\Unit;

use App\Support\LegacySqlInsertReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LegacySqlInsertReaderTest extends TestCase
{
    public function test_it_reads_insert_rows_with_mysql_escaped_values(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'legacy-sql-');
        if ($path === false) {
            self::fail('Unable to create a temporary SQL source.');
        }

        file_put_contents($path, <<<'SQL'
CREATE TABLE `example` (
  `id` int NOT NULL,
  `body` text,
  `optional` text
);
INSERT INTO `example` VALUES (7,'It\'s, fine','first\nsecond'),(8,'O''Brien',NULL);
SQL);

        try {
            $rows = iterator_to_array((new LegacySqlInsertReader())->read($path, ['example']));
        } finally {
            unlink($path);
        }

        self::assertSame([
            ['table' => 'example', 'row' => ['id' => '7', 'body' => "It's, fine", 'optional' => "first\nsecond"]],
            ['table' => 'example', 'row' => ['id' => '8', 'body' => "O'Brien", 'optional' => null]],
        ], $rows);
    }

    public function test_it_honors_insert_column_lists(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'legacy-sql-');
        if ($path === false) {
            self::fail('Unable to create a temporary SQL source.');
        }

        file_put_contents($path, <<<'SQL'
CREATE TABLE `example` (
  `id` int NOT NULL,
  `name` varchar(32) NOT NULL
);
INSERT INTO `example` (`name`,`id`) VALUES ('Row',9);
SQL);

        try {
            $rows = iterator_to_array((new LegacySqlInsertReader())->read($path, ['example']));
        } finally {
            unlink($path);
        }

        self::assertSame(['name' => 'Row', 'id' => '9'], $rows[0]['row']);
    }

    public function test_it_rejects_malformed_tuple_column_counts(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'legacy-sql-');
        if ($path === false) {
            self::fail('Unable to create a temporary SQL source.');
        }

        file_put_contents($path, <<<'SQL'
CREATE TABLE `example` (
  `id` int NOT NULL,
  `name` varchar(32) NOT NULL
);
INSERT INTO `example` VALUES (9);
SQL);

        try {
            $this->expectException(RuntimeException::class);
            iterator_to_array((new LegacySqlInsertReader())->read($path, ['example']));
        } finally {
            unlink($path);
        }
    }
}
