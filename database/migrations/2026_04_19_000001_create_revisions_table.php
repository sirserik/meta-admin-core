<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per snapshot of a revisionable model. `data` holds the full
 * pre-update attribute payload (JSON). Read back via the Revisionable
 * trait — models that use it auto-save a snapshot on every `updating`
 * event, and `$model->restoreRevision($id)` writes the snapshot back.
 *
 * Polymorphic so one table covers PageBlock + consumer resources
 * (Article, News, Page, …) without per-model boilerplate.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('revisions')) return;

        Schema::create('revisions', function (Blueprint $t) {
            $t->id();
            $t->string('revisionable_type');
            $t->unsignedBigInteger('revisionable_id');
            $t->foreignId('user_id')->nullable()
                ->comment('Editor who triggered the snapshot; nullable for CLI / seed writes.');
            $t->json('data');
            $t->string('note', 255)->nullable();
            $t->timestamp('created_at')->useCurrent();

            $t->index(['revisionable_type', 'revisionable_id'], 'revisions_morph_idx');
            $t->index('user_id');
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisions');
    }
};
