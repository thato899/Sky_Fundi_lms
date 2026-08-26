<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * See modules/Materials/README.md and docs/adr/011-materials-retrieval.md.
 * `materials` holds the raw ingested text (this pass: text only, no
 * PDF extraction — see the ADR); `material_chunks` holds the async
 * chunking job's output, one row per chunk with its embedding vector
 * stored as a JSON float array (no vector column type is available in
 * either MySQL 8.0 or SQLite here — see the ADR for why that's a
 * deliberate, documented choice, not an oversight).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->nullable()->constrained('academics_subjects')->nullOnDelete();
            $table->foreignUuid('uploaded_by')->constrained('users')->restrictOnDelete();

            $table->string('title');
            $table->string('source_type', 24)->default('text');
            $table->longText('content');
            $table->string('status', 24)->default('pending');
            $table->text('failure_message')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'subject_id']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('material_chunks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('material_id')->constrained('materials')->cascadeOnDelete();

            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->json('embedding')->nullable();
            $table->unsignedInteger('token_count')->nullable();

            $table->timestamps();

            $table->unique(['material_id', 'chunk_index']);
            // The retrieval hot path always filters by organization_id
            // first (see Application\MaterialRetrievalService) — never
            // by material_id alone — so cross-organization chunks are
            // never loaded into memory for a query.
            $table->index(['organization_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_chunks');
        Schema::dropIfExists('materials');
    }
};
