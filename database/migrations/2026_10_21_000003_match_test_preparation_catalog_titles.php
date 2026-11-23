<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Catalog titles come directly from the legacy SQL backup.
    }

    public function down(): void
    {
        // Catalog titles are retained as application data.
    }
};
