<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Procurements directory — backs the ProcurementsFeature.
 * Idempotent: skipped on consumers that created the table from a
 * local migration before the package shipped this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('procurements')) {
            return;
        }

        Schema::create('procurements', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->index();
            $table->string('slug')->unique();
            $table->string('procurement_type', 64)->index();
            $table->string('status', 32)->default('announced')->index();
            $table->text('title');
            $table->text('summary')->nullable();
            $table->text('customer')->nullable();
            $table->decimal('budget', 18, 2)->nullable();
            $table->string('budget_currency', 8)->default('KZT');
            $table->boolean('budget_public')->default(true);
            $table->timestamp('announced_at')->nullable()->index();
            $table->timestamp('deadline_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->integer('sort_order')->default(0);
            $table->json('meta_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurements');
    }
};
