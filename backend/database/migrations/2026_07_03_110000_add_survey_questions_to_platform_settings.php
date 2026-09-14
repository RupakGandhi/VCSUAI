<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CMS-editable survey question text. Defaults are the questions that were
 * previously hardcoded in the frontend survey modal, so existing installs
 * see no change until an admin edits them. Q1/Q2 stay 1-5 scales and Q3
 * stays open text — only the wording is editable (the response table and
 * analytics widgets depend on that shape).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('survey_q1_text')->default("How confident do you feel applying this module's strategies?");
            $table->string('survey_q2_text')->default('How relevant was this content to your role?');
            $table->string('survey_q3_text')->default('What would improve this module?');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['survey_q1_text', 'survey_q2_text', 'survey_q3_text']);
        });
    }
};
