<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard against consumers that already shipped this migration
        // locally before meta/admin-core v0.44 took ownership. Without
        // this, fresh installs of consumers that kept the old file in
        // place would double-create the table.
        if (Schema::hasTable('rector_questions')) {
            return;
        }

        Schema::create('rector_questions', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('category')->nullable();
            $table->string('subject');
            $table->text('question');
            $table->text('answer')->nullable();
            $table->enum('status', ['new', 'in_review', 'answered', 'rejected'])->default('new');
            $table->boolean('is_published')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('is_published');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rector_questions');
    }
};
