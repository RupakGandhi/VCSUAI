<?php

namespace App\Http\Middleware;

use App\Support\ActiveClient;
use Closure;
use Illuminate\Http\Request;

/**
 * Points the 'content' connection at the session-selected client for
 * admin-panel requests. Must run after StartSession. API requests never
 * pass through this: they stay on the .env (hosted client) database.
 */
class SetActiveClient
{
    public function handle(Request $request, Closure $next)
    {
        ActiveClient::apply(ActiveClient::current());

        return $next($request);
    }
}
