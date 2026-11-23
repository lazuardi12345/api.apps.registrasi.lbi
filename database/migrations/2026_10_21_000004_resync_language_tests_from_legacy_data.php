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
        // The imported legacy catalog is retained when rolling back this sync.
    }
};
