<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_taxonomy_terms', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_tid');
            $table->string('vocabulary', 128)->nullable();
            $table->string('term_key', 160)->unique();
            $table->string('name', 1024);
            $table->timestamps();
            $table->index(['legacy_tid', 'vocabulary']);
        });

        Schema::create('legacy_catalog_mappings', function (Blueprint $table): void {
            $table->id();
            $table->string('mapping_key', 180)->unique();
            $table->string('mapping_source', 50);
            $table->unsignedBigInteger('source_catalog_id')->nullable();
            $table->unsignedBigInteger('legacy_nid')->nullable()->index();
            $table->string('catalog_slug')->nullable()->index();
            $table->string('catalog_title')->nullable();
            $table->string('legacy_title')->nullable();
            $table->string('match_type', 50)->nullable();
            $table->string('confidence', 50)->nullable();
            $table->unsignedInteger('body_length')->nullable();
            $table->longText('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['mapping_source', 'match_type']);
        });

        Schema::create('legacy_source_content', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 100);
            $table->unsignedBigInteger('legacy_nid');
            $table->string('record_type', 50);
            $table->string('title')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['source', 'legacy_nid']);
            $table->index(['record_type', 'is_active']);
        });

        Schema::create('legacy_webform_fields', function (Blueprint $table): void {
            $table->id();
            $table->string('legacy_webform_id', 128);
            $table->string('field_name', 128);
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['legacy_webform_id', 'field_name'], 'legacy_webform_field_unique');
        });

        Schema::create('legacy_webform_submission_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_lid')->unique();
            $table->string('legacy_webform_id', 128)->index();
            $table->unsignedBigInteger('legacy_sid')->nullable()->index();
            $table->string('handler_id', 128)->nullable();
            $table->unsignedBigInteger('legacy_uid')->nullable()->index();
            $table->string('operation', 128)->nullable();
            $table->longText('message')->nullable();
            $table->longText('variables')->nullable();
            $table->longText('data')->nullable();
            $table->timestamp('logged_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_webform_submission_logs');
        Schema::dropIfExists('legacy_webform_fields');
        Schema::dropIfExists('legacy_source_content');
        Schema::dropIfExists('legacy_catalog_mappings');
        Schema::dropIfExists('legacy_taxonomy_terms');
    }
};
