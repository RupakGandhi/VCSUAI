<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-client display names for the six role lenses. Keys are internal and
 * fixed (classroom, leader, sped, support, coach, higher_ed) — only the
 * labels shown in the frontend dropdown change. Null = frontend defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->json('role_labels')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn('role_labels');
        });
    }
};
