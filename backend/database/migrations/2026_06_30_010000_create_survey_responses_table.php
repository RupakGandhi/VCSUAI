<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->string('module_id');
            $table->unsignedTinyInteger('q1_score'); // 1-5
            $table->unsignedTinyInteger('q2_score'); // 1-5
            $table->text('q3_text')->nullable();
            $table->string('role')->nullable();
            $table->uuid('session_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('module_id')->references('id')->on('modules');
            $table->index('module_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
