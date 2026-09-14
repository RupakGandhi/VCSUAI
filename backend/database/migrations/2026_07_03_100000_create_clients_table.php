<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master-DB registry of clients. Each client's CONTENT lives in its own
 * database (the 'content' connection is pointed at it per request); this
 * table only records which databases exist and how to reach them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('database');
            $table->string('db_host')->nullable();      // null = same host as master
            $table->string('db_username')->nullable();  // null = master credentials
            $table->text('db_password')->nullable();    // encrypted cast on the model
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // The existing installation IS the first client (VCSU): its content
        // database is the master database itself, so everything currently
        // deployed keeps working with no data movement.
        DB::table('clients')->insert([
            'name' => 'VCSU',
            'database' => config('database.connections.mysql.database'),
            'notes' => 'Original client — content lives in the master database.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
