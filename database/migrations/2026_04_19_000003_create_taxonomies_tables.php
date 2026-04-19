<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-table taxonomy schema:
 *
 *   taxonomy_terms    — the vocabulary: {type, slug, label}
 *                       type is a free-form bucket ('tag', 'category',
 *                       'audience', …) so one table covers any number
 *                       of logical vocabularies.
 *
 *   taxonomy_term_model — pivot: morphs a term to any Eloquent model
 *                       via (taxable_type, taxable_id, term_id).
 *
 * Polymorphic so one schema covers articles, programs, news, blocks,
 * etc. without per-resource pivots.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('taxonomy_terms')) {
            Schema::create('taxonomy_terms', function (Blueprint $t) {
                $t->id();
                $t->string('type', 50)->index()
                    ->comment('Vocabulary bucket: tag | category | audience | …');
                $t->string('slug', 120);
                $t->string('label', 255);
                $t->json('label_translations')->nullable()
                    ->comment('Optional per-locale labels {ru, kk, en, …}.');
                $t->unsignedInteger('sort_order')->default(0);
                $t->timestamps();

                $t->unique(['type', 'slug']);
            });
        }

        if (!Schema::hasTable('taxonomy_term_model')) {
            Schema::create('taxonomy_term_model', function (Blueprint $t) {
                $t->id();
                $t->foreignId('term_id')->constrained('taxonomy_terms')->cascadeOnDelete();
                $t->string('taxable_type');
                $t->unsignedBigInteger('taxable_id');
                $t->timestamps();

                $t->index(['taxable_type', 'taxable_id'], 'taxonomy_term_model_morph_idx');
                $t->unique(['term_id', 'taxable_type', 'taxable_id'], 'taxonomy_term_model_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_term_model');
        Schema::dropIfExists('taxonomy_terms');
    }
};
