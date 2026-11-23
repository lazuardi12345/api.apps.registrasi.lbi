<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // PPP catalog data is imported explicitly by legacy:imports.
    }

    public function down(): void
    {
        // Imported PPP records are retained as application data.
    }
};
