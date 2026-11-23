<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('legacy_webforms')) {
            Schema::create('legacy_webforms', function (Blueprint $table) {
                $table->id();
                $table->string('legacy_webform_id', 128)->unique();
                $table->string('program', 50)->nullable()->index();
                $table->boolean('preferred')->default(false);
                $table->string('title')->nullable();
                $table->string('source', 100)->nullable();
                $table->longText('raw_config')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('legacy_webform_submissions')) {
            Schema::create('legacy_webform_submissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('legacy_sid')->unique();
                $table->string('legacy_webform_id', 128)->index();
                $table->unsignedBigInteger('legacy_uid')->nullable()->index();
                $table->uuid('legacy_uuid')->nullable()->index();
                $table->string('state', 50)->nullable()->index();
                $table->timestamp('submitted_at')->nullable()->index();
                $table->json('raw_payload')->nullable();
                $table->boolean('mapped')->default(false)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('legacy_webform_submission_data')) {
            Schema::create('legacy_webform_submission_data', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('legacy_sid')->index();
                $table->string('legacy_webform_id', 128)->index();
                $table->string('field_name', 128)->index();
                $table->string('property', 128)->default('');
                $table->unsignedInteger('delta')->default(0);
                $table->longText('value')->nullable();
                $table->timestamps();
                $table->unique(['legacy_sid', 'field_name', 'property', 'delta'], 'legacy_webform_data_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_webform_submission_data');
        Schema::dropIfExists('legacy_webform_submissions');
        Schema::dropIfExists('legacy_webforms');
    }
};