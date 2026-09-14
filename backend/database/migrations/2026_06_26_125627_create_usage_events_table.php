<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();
            // event_type: page_view, module_start, module_complete, sim_used,
            // role_change, search, certificate_generated
            $table->string('event_type');
            $table->string('module_id')->nullable();
            $table->string('role')->nullable();
            $table->string('search_term')->nullable();
            // Random per-browser UUID stored client-side in localStorage (not a cookie).
            // No PII; used only to de-duplicate within a session.
            $table->uuid('session_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('event_type');
            $table->index('module_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
