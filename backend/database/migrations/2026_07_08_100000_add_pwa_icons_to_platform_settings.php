<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-client PWA home-screen icons. Store the uploaded file's relative path
 * on the 'public' disk (served via /files/platform-icons/{file}, same
 * pattern as prompt sample files — see routes/web.php for why that's not a
 * /storage/ symlink URL). Null = fall back to the shared default icons in
 * storage/app/static-template/assets/icons/.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('icon_192')->nullable();
            $table->string('icon_512')->nullable();
            $table->string('icon_512_maskable')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['icon_192', 'icon_512', 'icon_512_maskable']);
        });
    }
};
