<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lives in the MASTER database (default connection) — never on the
 * per-client 'content' connection, or switching clients would make the
 * client registry itself disappear.
 */
class Client extends Model
{
    protected $connection = 'mysql';

    protected $fillable = ['name', 'database', 'db_host', 'db_username', 'db_password', 'notes', 'public_token'];

    protected $casts = [
        'db_password' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            $client->public_token ??= \Illuminate\Support\Str::random(24);
        });
    }

    public function isMaster(): bool
    {
        return $this->database === config('database.connections.mysql.database');
    }

    /** Live URL where this client's site is served from this server. */
    public function publicUrl(): string
    {
        return url('/site/'.$this->public_token);
    }
}
