<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('redirects') || Schema::hasColumn('redirects', 'hits')) {
            return;
        }

        Schema::table('redirects', function (Blueprint $table) {
            $table->unsignedBigInteger('hits')->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('redirects') && Schema::hasColumn('redirects', 'hits')) {
            Schema::table('redirects', function (Blueprint $table) {
                $table->dropColumn('hits');
            });
        }
    }
};
