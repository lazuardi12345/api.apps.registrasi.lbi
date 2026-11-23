<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Registration catalog data is imported explicitly by legacy:imports.
    }

    public function down(): void
    {
        // Imported catalog data is retained as application data.
    }
};
