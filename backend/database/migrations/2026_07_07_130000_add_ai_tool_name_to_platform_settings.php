<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-client name of the AI tool referenced in the deliverable step-by-step
 * guides ("Open Microsoft Copilot (or your preferred AI tool)..."). Null =
 * the frontend default, "Microsoft Copilot". Helena West Helena uses
 * "Gemini". Because this lives in platform_settings (per client DB) and
 * flows through export/import, it survives content refreshes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('ai_tool_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn('ai_tool_name');
        });
    }
};
