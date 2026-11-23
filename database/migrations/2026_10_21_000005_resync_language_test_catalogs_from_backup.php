<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Catalog data is imported explicitly from the legacy SQL backup.
    }

    public function down(): void
    {
        // Imported catalog records are retained when rolling back this sync.
    }
};
