<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settings')) return;

        // Schema matches what existing consumer sites (ETU, etec) already
        // have so a fresh install ends up with a compatible table out of
        // the box. Existing sites hit the Schema::hasTable guard above
        // and keep their rows unchanged.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 50)->default('text'); // text | textarea | boolean | number | json
            $table->text('description')->nullable();
            $table->string('group', 100)->default('general');
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
