<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound webhook registry. Each row = one HTTP endpoint + a list of
 * events that should trigger it. Consumers manage rows via /admin/webhooks
 * and programmatic hooks dispatch through \Meta\AdminCore\Services\WebhookDispatcher.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('webhooks')) return;

        Schema::create('webhooks', function (Blueprint $t) {
            $t->id();
            $t->string('label', 150)->comment('Human label for the admin list.');
            $t->string('url', 500);
            $t->json('events')->comment('Event names this webhook listens to, e.g. ["page_block.updated"].');
            $t->string('secret', 120)->nullable()->comment('HMAC secret; signs every payload when set.');
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_fired_at')->nullable();
            $t->timestamps();

            $t->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
