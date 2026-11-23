<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Taxonomy terms are imported and resolved by legacy:imports.
    }

    public function down(): void
    {
        // Resolved legacy values are retained as application data.
    }
};
