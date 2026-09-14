<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('module_id');
            $table->string('role'); // classroom, leader, sped, support, coach, higher_ed
            $table->string('title');
            $table->string('ai_tool')->nullable(); // model_rec, e.g. "Claude"
            $table->text('prompt_text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
            $table->index(['module_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_prompts');
    }
};
