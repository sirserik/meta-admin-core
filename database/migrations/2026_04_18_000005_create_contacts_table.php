<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contacts')) return;

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('type', 20)->default('other'); // phone|email|address|social|other
            $table->string('value', 500);
            $table->string('label', 255)->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
