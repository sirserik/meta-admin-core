<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('page_blocks')) return;

        Schema::create('page_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('page_name');
            $table->string('block_key');
            $table->string('block_type');
            $table->string('title')->nullable();
            $table->text('subtitle')->nullable();
            $table->longText('content')->nullable();
            $table->json('data')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('published');     // draft | published
            $table->timestamp('published_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['page_name', 'block_key']);
            $table->index(['page_name', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_blocks');
    }
};
