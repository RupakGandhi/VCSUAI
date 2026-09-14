<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row settings table (id = 1 always)
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('page_title')->nullable();
            $table->string('hero_title')->nullable();
            $table->text('hero_desc')->nullable();
            $table->json('hero_stats')->nullable();
            $table->string('primary_color')->nullable();
            $table->string('accent_color')->nullable();
            // Access codes: kept here only because they already lived in plaintext
            // in the existing front-end source; not used for any new auth.
            $table->string('password')->nullable();
            $table->string('fac_password')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
