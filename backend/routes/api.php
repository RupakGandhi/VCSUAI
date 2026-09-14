<?php

use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\SurveyResponseController;
use App\Http\Controllers\Api\UsageEventController;
use Illuminate\Support\Facades\Route;

// Public, unauthenticated: the front end calls these with no login. Read-only
// content is cacheable; event ingestion is anonymous and rate-limited.
Route::prefix('v1')->group(function () {
    Route::get('/content', [ContentController::class, 'show']);
    Route::post('/events', [UsageEventController::class, 'store'])
        ->middleware('throttle:60,1');
    Route::post('/survey', [SurveyResponseController::class, 'store'])
        ->middleware('throttle:30,1');
});
