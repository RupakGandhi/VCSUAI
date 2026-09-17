<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SurveyResponse;
use App\Support\ActiveClient;
use Illuminate\Http\Request;

class SurveyResponseController extends Controller
{
    public function store(Request $request)
    {
        // Live per-client sites (/site/{token}) send their client token so
        // the response lands in that client's database. Without a token the
        // content connection stays on the .env (hosted client) database.
        if (is_string($request->input('client_token')) && $request->filled('client_token')) {
            $client = Client::where('public_token', $request->input('client_token'))->first();
            if ($client) {
                ActiveClient::apply($client);
            }
        }

        $validated = $request->validate([
            'module_id'  => ['required', 'string', 'exists:content.modules,id'],
            'q1_score'   => ['required', 'integer', 'min:1', 'max:5'],
            'q2_score'   => ['required', 'integer', 'min:1', 'max:5'],
            'q3_text'    => ['nullable', 'string', 'max:2000'],
            'role'       => ['nullable', 'string', 'max:50'],
            'session_id' => ['nullable', 'uuid'],
            // Lets a deliberate QA/retest submission mark itself so it can
            // be excluded from evaluation later without deleting real
            // participant data alongside it. Never set by the live survey
            // form -- only a controlled test call would send this.
            'is_test'    => ['nullable', 'boolean'],
        ]);

        SurveyResponse::create($validated);

        return response()->json(['ok' => true], 201);
    }
}
