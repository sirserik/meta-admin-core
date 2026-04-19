<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Form builder storage:
 *
 *   forms               — definition (name, slug, fields JSON, settings)
 *   form_submissions    — every POST, payload + metadata
 *
 * Field schema lives in `fields` as an array of:
 *   {name, label, type, required?, placeholder?, options?, help?}
 * Supported types: text, textarea, email, tel, url, number,
 * select, checkbox, radio, date. Extensible — unknown types fall
 * back to a plain text input on the public side.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('forms')) {
            Schema::create('forms', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('slug', 120)->unique();
                $t->json('fields')->comment('Array of field definitions.');
                $t->string('notify_email')->nullable()
                    ->comment('Email address to notify on new submission (optional).');
                $t->string('success_message')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('form_submissions')) {
            Schema::create('form_submissions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
                $t->json('data');
                $t->string('ip_address', 45)->nullable();
                $t->string('user_agent', 500)->nullable();
                $t->string('status', 30)->default('new')
                    ->comment('new | read | replied | spam');
                $t->timestamp('created_at')->useCurrent();

                $t->index(['form_id', 'created_at']);
                $t->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('forms');
    }
};
