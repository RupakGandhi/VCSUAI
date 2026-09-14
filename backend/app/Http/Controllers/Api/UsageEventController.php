<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UsageEvent;
use Illuminate\Http\Request;

/**
 * Anonymous usage event ingestion. No auth, no PII: callers identify
 * themselves only by a random UUID the front end generates and stores in
 * localStorage (not a cookie) purely to de-duplicate within a session.
 */
class UsageEventController extends Controller
{
    public function store(Request $request)
    {
        // Same per-client routing as the survey endpoint: live /site/{token}
        // pages send their client token so events land in that client's DB.
        if (is_string($request->input('client_token')) && $request->filled('client_token')) {
            $client = \App\Models\Client::where('public_token', $request->input('client_token'))->first();
            if ($client) {
                \App\Support\ActiveClient::apply($client);
            }
        }

        $validated = $request->validate([
            'event_type' => ['required', 'string', 'in:'.implode(',', UsageEvent::EVENT_TYPES)],
            'module_id' => ['nullable', 'string', 'max:20'],
            'role' => ['nullable', 'string', 'max:50'],
            'search_term' => ['nullable', 'string', 'max:255'],
            'session_id' => ['nullable', 'uuid'],
        ]);

        UsageEvent::create($validated);

        return response()->json(['ok' => true], 201);
    }
}
