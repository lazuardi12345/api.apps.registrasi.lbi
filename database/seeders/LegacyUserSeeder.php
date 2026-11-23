<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LegacyUserSeeder extends Seeder
{
    public function run(): void
    {
        $path = env('LEGACY_USER_DUMP', base_path('../lbifib_db_backup.sql/lbifib_db_backup.sql'));

        if (!is_file($path)) {
            $this->command?->error("Legacy user dump not found: {$path}");
            return;
        }

        $handle = fopen($path, 'rb');
        $legacyRoles = $this->readLegacyRoles($path);
        $imported = 0;
        $skipped = 0;

        while (($line = fgets($handle)) !== false) {
            if (!str_contains($line, 'INSERT INTO `users_field_data`') || !str_contains($line, 'VALUES')) {
                continue;
            }

            foreach ($this->tuples($line) as $tuple) {
                $values = $this->parseTuple($tuple);
                if (count($values) < 15) {
                    $skipped++;
                    continue;
                }

                [$uid, , , , $name, $password, $email, , $status, $created] = $values;
                $email = strtolower(trim((string) $email));
                $name = trim((string) $name);

                if (!ctype_digit(trim((string) $uid)) || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $skipped++;
                    continue;
                }

                $legacyUid = (string) (int) $uid;
                $roles = $legacyRoles[(int) $uid] ?? [];
                $attributes = [
                    'legacy_uid' => $legacyUid,
                    'name' => $name,
                    'email' => $email,
                    'password' => trim((string) $password) !== '' ? (string) $password : '!',
                    'role' => $this->primaryRole($roles),
                    'status' => (bool) ((int) $status),
                    'email_verified_at' => null,
                    'created_at' => ctype_digit(trim((string) $created)) ? date('Y-m-d H:i:s', (int) $created) : now(),
                    'updated_at' => now(),
                ];

                $existing = DB::table('users')
                    ->where('legacy_uid', $legacyUid)
                    ->orWhere('email', $email)
                    ->first(['id']);

                if ($existing) {
                    DB::table('users')->where('id', $existing->id)->update($attributes);
                    $userId = $existing->id;
                } else {
                    $userId = DB::table('users')->insertGetId($attributes);
                }

                DB::table('user_roles')->where('user_id', $userId)->delete();
                foreach (array_unique(array_merge([$attributes['role']], $roles)) as $role) {
                    DB::table('user_roles')->insert([
                        'user_id' => $userId,
                        'role_name' => $role,
                    ]);
                }

                $imported++;
            }
        }

        fclose($handle);

        $this->command?->info("Imported {$imported} legacy users; skipped {$skipped} records.");
    }

    private function readLegacyRoles(string $path): array
    {
        $roles = [];
        $handle = fopen($path, 'rb');

        while (($line = fgets($handle)) !== false) {
            if (!str_contains($line, 'INSERT INTO `user__roles`') || !str_contains($line, 'VALUES')) {
                continue;
            }

            foreach ($this->tuples($line) as $tuple) {
                $values = $this->parseTuple($tuple);
                $uid = $values[2] ?? null;
                $role = trim((string) ($values[6] ?? ''));

                if (ctype_digit(trim((string) $uid)) && $role !== '') {
                    $roles[(int) $uid][] = $role;
                }
            }
        }

        fclose($handle);

        return $roles;
    }

    private function primaryRole(array $roles): string
    {
        $priority = [
            'administrator',
            'finance',
            'admin_ppb',
            'admin_bipa',
            'admin_ppp',
            'content_writer',
            'teacher',
            'student',
        ];

        foreach ($priority as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return 'student';
    }

    private function tuples(string $line): array
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
                if ($depth > 0) {
                    $buffer .= $character;
                }
                $escaped = false;
                continue;
            }

            if ($character === '\\' && $quoted) {
                if ($depth > 0) {
                    $buffer .= $character;
                }
                $escaped = true;
                continue;
            }

            if ($character === "'") {
                $quoted = !$quoted;
            }

            if (!$quoted && $character === '(') {
                $depth++;
                if ($depth === 1) {
                    $buffer = '';
                }
            } elseif ($depth > 0) {
                $buffer .= $character;
            }

            if (!$quoted && $character === ')') {
                $depth--;
                if ($depth === 0) {
                    $result[] = substr($buffer, 0, -1);
                }
            }
        }

        return $result;
    }

    private function parseTuple(string $tuple): array
    {
        $values = str_getcsv($tuple, ',', "'", '\\');

        return array_map(static function ($value) {
            $value = trim($value);
            return strtoupper($value) === 'NULL' ? null : stripcslashes($value);
        }, $values);
    }
}
