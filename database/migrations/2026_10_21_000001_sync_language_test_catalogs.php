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
        // Catalog data is retained as application data.
    }
};
