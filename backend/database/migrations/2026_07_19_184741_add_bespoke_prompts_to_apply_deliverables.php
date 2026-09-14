<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('apply_deliverables', function (Blueprint $table) {
            // Bespoke, hand-authored Step-by-Step Guide prompts. When either
            // is null, the frontend falls back to generating that step's
            // prompt algorithmically from role + description, exactly as it
            // does today -- so deliverables not covered by a given content
            // revision keep working unchanged.
            $table->text('initial_prompt')->nullable()->after('description');
            $table->text('refine_prompt')->nullable()->after('initial_prompt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apply_deliverables', function (Blueprint $table) {
            $table->dropColumn(['initial_prompt', 'refine_prompt']);
        });
    }
};
