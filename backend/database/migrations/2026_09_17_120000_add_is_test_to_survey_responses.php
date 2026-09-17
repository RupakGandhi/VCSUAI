<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lets a QA/retest submission (or any known-bad row) be excluded
        // from survey evaluation without deleting it -- the only prior
        // options were "Clear responses" per-module or "Clear Survey Data"
        // entirely, both of which take real participant data down with it.
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->boolean('is_test')->default(false)->after('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->dropColumn('is_test');
        });
    }
};
