<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Program descriptions are imported explicitly by legacy:imports.
    }

    public function down(): void
    {
        // Imported program content is retained as application data.
    }
};
