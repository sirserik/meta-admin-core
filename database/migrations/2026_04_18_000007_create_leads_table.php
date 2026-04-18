<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leads')) return;

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);

            $table->string('name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone', 20);
            $table->string('email')->nullable();

            $table->string('program')->nullable();
            $table->string('year', 4)->nullable();
            $table->text('message')->nullable();

            $table->json('interests')->nullable();
            $table->string('call_time')->nullable();
            $table->text('questions')->nullable();

            $table->string('source')->default('website');
            $table->string('status', 20)->default('new');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index('type');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
