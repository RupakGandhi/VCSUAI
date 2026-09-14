<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('public_token', 32)->nullable()->unique();
        });

        // Backfill tokens for clients created before this migration.
        foreach (DB::table('clients')->whereNull('public_token')->pluck('id') as $id) {
            DB::table('clients')->where('id', $id)->update(['public_token' => Str::random(24)]);
        }
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });
    }
};
