<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('redirects')) return;

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_url', 2000);
            $table->string('to_url', 2000);
            $table->integer('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['from_url', 'is_active'], 'redirects_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
