<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LegacyCourseCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn(
            'This seeder no longer imports staging JSON. Run legacy:catalogs-import with the legacy SQL backup.'
        );
    }
}