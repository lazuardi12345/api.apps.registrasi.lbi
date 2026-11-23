<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_catalogs', function (Blueprint $table) {
            $table->json('payment_steps')->nullable()->after('registration_url');
        });
    }

    public function down(): void
    {
        Schema::table('course_catalogs', function (Blueprint $table) {
            $table->dropColumn('payment_steps');
        });
    }
};