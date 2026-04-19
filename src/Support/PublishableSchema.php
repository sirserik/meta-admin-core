<?php

namespace Meta\AdminCore\Support;

use Illuminate\Database\Schema\Blueprint;

/**
 * Migration helper: adds the two timestamp columns the Publishable
 * trait expects. Use inside a Schema::table() callback.
 *
 *   Schema::table('page_blocks', function (Blueprint $t) {
 *       \Meta\AdminCore\Support\PublishableSchema::columns($t);
 *   });
 */
class PublishableSchema
{
    public static function columns(Blueprint $table): void
    {
        $table->timestamp('publish_at')->nullable()->after('status');
        $table->timestamp('unpublish_at')->nullable()->after('publish_at');
        $table->index('publish_at');
        $table->index('unpublish_at');
    }

    public static function drop(Blueprint $table): void
    {
        $table->dropIndex(['publish_at']);
        $table->dropIndex(['unpublish_at']);
        $table->dropColumn(['publish_at', 'unpublish_at']);
    }
}
