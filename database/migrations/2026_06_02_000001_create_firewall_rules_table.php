<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('firewall_rules')) {
            return;
        }

        Schema::create('firewall_rules', function (Blueprint $table) {
            $table->id();
            // IPv4 or IPv4/CIDR — an address allowed to reach SSH (port 22).
            $table->string('ip_address', 64)->unique();
            $table->string('label', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firewall_rules');
    }
};
