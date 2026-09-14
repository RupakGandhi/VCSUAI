<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $cascadeDeleteTables = [
        'module_contents',
        'apply_deliverables',
        'practice_prompts',
        'simulations',
        'module_overview_sections',
    ];

    private array $tables = [
        'module_contents',
        'apply_deliverables',
        'practice_prompts',
        'simulations',
        'module_overview_sections',
        'survey_responses',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropForeign(['module_id']);
                $foreign = $table->foreign('module_id')->references('id')->on('modules')->cascadeOnUpdate();

                if (in_array($tableName, $this->cascadeDeleteTables, true)) {
                    $foreign->cascadeOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropForeign(['module_id']);
                $foreign = $table->foreign('module_id')->references('id')->on('modules');

                if ($tableName !== 'survey_responses') {
                    $foreign->cascadeOnDelete();
                }
            });
        }
    }
};
