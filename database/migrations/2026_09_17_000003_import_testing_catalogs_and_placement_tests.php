<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Test catalogs are imported explicitly from the legacy SQL backup.
    }

    public function down(): void
    {
        // Legacy catalog records are retained when rolling back this import.
    }
};
