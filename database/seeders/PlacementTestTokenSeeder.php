<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlacementTestToken;
use RuntimeException;

class PlacementTestTokenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = config('services.dpt_token_seed_file')
            ?: dirname(base_path()) . DIRECTORY_SEPARATOR . 'private-backend-data'
                . DIRECTORY_SEPARATOR . 'testing' . DIRECTORY_SEPARATOR . 'placement-test-tokens.json';

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("DPT test-token data file is missing or unreadable: {$path}");
        }

        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (
            ! is_array($data)
            || ! is_array($data['tokens'] ?? null)
            || ! is_string($data['test_type'] ?? null)
            || ! is_string($data['activation_start_date'] ?? null)
            || ! is_string($data['expiry_date'] ?? null)
            || ! is_numeric($data['licence_length_days'] ?? null)
        ) {
            throw new RuntimeException("DPT test-token data file has an invalid structure: {$path}");
        }

        foreach ($data['tokens'] as $tokenCode) {
            if (! is_string($tokenCode) || $tokenCode === '') {
                throw new RuntimeException("DPT test-token data file contains an invalid token: {$path}");
            }

            PlacementTestToken::firstOrCreate(
                ['token_code' => $tokenCode],
                [
                    'test_type' => $data['test_type'],
                    'activation_start_date' => $data['activation_start_date'],
                    'expiry_date' => $data['expiry_date'],
                    'licence_length_days' => (int) $data['licence_length_days'],
                    'is_used' => false,
                ]
            );
        }
    }
}
