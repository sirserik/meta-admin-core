<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('translations')) return; // already managed by consumer

        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->morphs('translatable'); // translatable_type + translatable_id
            $table->string('locale', 5);
            $table->string('field', 100);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['translatable_type', 'translatable_id', 'locale', 'field'], 'translations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
