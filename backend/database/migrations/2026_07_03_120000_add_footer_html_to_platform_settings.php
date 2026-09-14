<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->text('footer_html')->nullable();
        });

        // Seed existing rows with the footer currently hardcoded in the
        // frontend so admins see (and can edit) the real current value.
        DB::table('platform_settings')->whereNull('footer_html')->update([
            'footer_html' => '<p>VCSU AI Institute for Teaching and Learning — SEEC K-12 AI Mastery Platform</p>'
                .'<p>Developed by <a href="https://optimizedstrategicsolutions.com" target="_blank" rel="noopener">OptimizED Strategic Solutions</a> in partnership with Valley City State University and SEEC</p>'
                .'<p>© 2026 OptimizED Strategic Solutions, Fargo, ND.</p>',
        ]);
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn('footer_html');
        });
    }
};
