<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menu_items')) return;

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('content_type', 50); // 'schools', 'programs', 'link', ...
            $table->unsignedBigInteger('content_id')->nullable();
            $table->string('slug');
            $table->string('icon', 100)->nullable();
            $table->boolean('is_published')->default(true);
            $table->integer('menu_order')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('menu_items')->onDelete('cascade');
            $table->index(['content_type', 'content_id']);
            $table->index('menu_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
