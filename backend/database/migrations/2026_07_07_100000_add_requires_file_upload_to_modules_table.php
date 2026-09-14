<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File upload is a property of the MODULE (per the client's spec, exactly
 * 7 modules have an upload exercise), not of each simulation entry — the
 * per-simulation flag made admins toggle it on every prompt. The old
 * simulations.requires_file_upload column stays (frontend still honors it
 * as a fallback for choosing which simulation responds to an upload).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->boolean('requires_file_upload')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('requires_file_upload');
        });
    }
};
