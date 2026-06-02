<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('documents')) {
            return;
        }

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');          // documentable_type + _id (+ index)
            $table->string('title');
            $table->string('description', 1000)->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type', 16)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type')->nullable();
            $table->string('locale', 8)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('downloads')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
