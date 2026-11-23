<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Catalog and schedule divisions come from the explicit legacy SQL import.
    }

    public function down(): void
    {
        // Data correction migration; the previous classifications are not restored.
    }
};
