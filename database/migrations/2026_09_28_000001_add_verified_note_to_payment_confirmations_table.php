<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_confirmations', function (Blueprint $table) {
            // Note dari finance officer saat verifikasi atau reject
            $table->text('verified_note')->nullable()->after('verified_at');
            // ID user yang melakukan verifikasi/reject
            $table->unsignedBigInteger('verified_by')->nullable()->after('verified_note');
        });
    }

    public function down(): void
    {
        Schema::table('payment_confirmations', function (Blueprint $table) {
            $table->dropColumn(['verified_note', 'verified_by']);
        });
    }
};
