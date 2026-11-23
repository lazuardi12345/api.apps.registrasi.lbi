<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('services', 'parent_service_id')) {
            Schema::table('services', function (Blueprint $table): void {
                $table->foreignId('parent_service_id')->nullable()->after('id')->constrained('services')->nullOnDelete();
                $table->index(['parent_service_id', 'is_active', 'menu_order']);
            });
        }

        // Legacy BIPA content is imported explicitly by legacy:imports.
    }

    public function down(): void
    {
        // Keep imported BIPA records when rolling back catalog categorization.
    }
};
