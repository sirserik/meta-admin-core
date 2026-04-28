<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add polymorphic `blockable_type/blockable_id` columns to `page_blocks`.
 *
 * Lets blocks attach to any Eloquent model (procurements, programs, news,
 * whatever) via a morphMany relation, in addition to the existing
 * `page_name`-based binding which stays as the canonical anchor for
 * static pages (about, contact, etc.).
 *
 * Idempotent — safe to run on consumers that already added these
 * columns locally before the package shipped this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('page_blocks')) {
            return;
        }

        Schema::table('page_blocks', function (Blueprint $table) {
            if (! Schema::hasColumn('page_blocks', 'blockable_type')) {
                $table->string('blockable_type')->nullable()->after('page_name');
            }
            if (! Schema::hasColumn('page_blocks', 'blockable_id')) {
                $table->unsignedBigInteger('blockable_id')->nullable()->after('blockable_type');
            }
        });

        // Composite index for "blocks of this owner, ordered" queries.
        // Wrapped in a try because a consumer may have added an index of the
        // same name from their own pre-package migration.
        try {
            Schema::table('page_blocks', function (Blueprint $table) {
                $table->index(
                    ['blockable_type', 'blockable_id', 'is_active', 'sort_order'],
                    'page_blocks_blockable_index'
                );
            });
        } catch (\Throwable) {
            // Index already exists — nothing to do.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('page_blocks')) {
            return;
        }

        Schema::table('page_blocks', function (Blueprint $table) {
            try { $table->dropIndex('page_blocks_blockable_index'); } catch (\Throwable) {}
            $cols = array_filter(
                ['blockable_type', 'blockable_id'],
                fn ($c) => Schema::hasColumn('page_blocks', $c)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
