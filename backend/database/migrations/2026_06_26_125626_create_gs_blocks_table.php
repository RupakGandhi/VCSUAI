<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gs_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gs_page_id')->constrained('gs_pages')->cascadeOnDelete();
            $table->string('title');
            $table->longText('content_html');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gs_blocks');
    }
};
