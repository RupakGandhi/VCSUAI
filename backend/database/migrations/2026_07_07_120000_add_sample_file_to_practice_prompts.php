<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional sample file per practice prompt (uploaded in the CMS, stored on
 * the public disk under prompt-samples/). The frontend shows it as a
 * download link on the prompt card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_prompts', function (Blueprint $table) {
            $table->string('sample_file')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('practice_prompts', function (Blueprint $table) {
            $table->dropColumn('sample_file');
        });
    }
};
