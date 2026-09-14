<?php

namespace App\Support;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

/**
 * Resolves which client the current request is working on and points the
 * 'content' database connection at that client's database.
 *
 * Contexts:
 * - Admin panel: the client chosen via the Clients page (kept in session).
 * - Public API / artisan / queue: no session choice -> the hosted client,
 *   i.e. the master database from .env. This is what keeps the live VCSU
 *   site reading VCSU content no matter what the admin is editing.
 */
class ActiveClient
{
    public static function current(): ?Client
    {
        $id = session('active_client_id');
        if (! $id) {
            return null;
        }

        return Client::find($id);
    }

    /** The client whose content the panel is currently managing (for display). */
    public static function currentOrMaster(): ?Client
    {
        return self::current()
            ?? Client::where('database', config('database.connections.mysql.database'))->first();
    }

    /**
     * Point the 'content' connection at the given client's database
     * (null = master/.env database). Purges the connection so the next
     * query reconnects with the new settings.
     */
    public static function apply(?Client $client): void
    {
        $master = config('database.connections.mysql');

        if ($client === null || $client->isMaster()) {
            config([
                'database.connections.content.host' => $master['host'],
                'database.connections.content.database' => $master['database'],
                'database.connections.content.username' => $master['username'],
                'database.connections.content.password' => $master['password'],
            ]);
        } else {
            config([
                'database.connections.content.host' => $client->db_host ?: $master['host'],
                'database.connections.content.database' => $client->database,
                'database.connections.content.username' => $client->db_username ?: $master['username'],
                'database.connections.content.password' => $client->db_password ?: $master['password'],
            ]);
        }

        DB::purge('content');
    }
}
