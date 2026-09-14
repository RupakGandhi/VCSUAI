<?php

use App\Models\SurveyResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/panel'));

// Prompt sample file downloads. Served through Laravel on purpose — NOT via
// the public/storage symlink: on Hostinger the app root is the web docroot,
// so "/storage/..." URLs get captured by the real storage/ directory before
// they ever reach Laravel. A dedicated path avoids that collision on any host.
Route::get('/files/prompt-samples/{file}', function (string $file) {
    abort_if(basename($file) !== $file, 404); // no path traversal
    $path = storage_path('app/public/prompt-samples/'.$file);
    abort_unless(is_file($path), 404);

    return response()->file($path, ['Cache-Control' => 'public, max-age=3600']);
});

// Per-client PWA icons uploaded via Platform Settings. Same Hostinger
// docroot collision reasoning as prompt-samples above.
Route::get('/files/platform-icons/{file}', function (string $file) {
    abort_if(basename($file) !== $file, 404); // no path traversal
    $path = storage_path('app/public/platform-icons/'.$file);
    abort_unless(is_file($path), 404);

    return response()->file($path, ['Cache-Control' => 'public, max-age=3600']);
});

// Plain GET download — bypasses Livewire so Hostinger's WAF doesn't block it.
// Requires an active Filament panel session (same auth guard as /panel).
Route::get('/panel/survey-export', function () {
    abort_unless(auth()->check(), 403);

    $rows = SurveyResponse::orderBy('created_at')
        ->get(['module_id', 'role', 'q1_score', 'q2_score', 'q3_text', 'created_at']);

    $filename = 'survey-responses-' . now()->format('Y-m-d') . '.csv';

    return response()->streamDownload(function () use ($rows) {
        $handle = fopen('php://output', 'w');
        fputcsv($handle, ['Module', 'Role', 'Q1 Confidence', 'Q2 Relevance', 'Q3 Improvement', 'Date']);
        foreach ($rows as $r) {
            fputcsv($handle, [$r->module_id, $r->role, $r->q1_score, $r->q2_score, $r->q3_text, $r->created_at]);
        }
        fclose($handle);
    }, $filename, ['Content-Type' => 'text/csv']);
})->middleware(['web', App\Http\Middleware\SetActiveClient::class]);

// Static HTML export: downloads a fully self-contained client site with all
// CMS content baked in — no runtime API dependency, survey disabled. Exports
// the session-selected client by default; ?client={id} overrides (used by the
// per-row Download button on the Clients page). Plain GET for the same WAF
// reason as above.
Route::get('/panel/static-export', function () {
    abort_unless(auth()->check(), 403);

    $client = request('client')
        ? App\Models\Client::findOrFail(request('client'))
        : App\Support\ActiveClient::currentOrMaster();

    App\Support\ActiveClient::apply($client);
    $bundle = app(App\Services\StaticSiteBuilder::class)->buildBundle();
    // Restore the session-selected client for anything later in the request
    App\Support\ActiveClient::apply(App\Support\ActiveClient::current());

    // One self-contained ZIP: built index.html + this client's uploaded
    // sample files (relative links) + the sibling assets the page needs
    // (jsPDF, fonts, icons, manifest, sw.js, artifacts). Extract and host
    // the folder anywhere — everything works with no backend.
    $zipPath = tempnam(sys_get_temp_dir(), 'vcsu-export-');
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::OVERWRITE);

    $zip->addFromString('index.html', $bundle['html']);

    foreach ($bundle['files'] as $inZip => $absPath) {
        $zip->addFile($absPath, $inZip);
    }

    $base = storage_path('app/static-template');
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
        if ($relative === 'index.html') {
            continue; // replaced by the freshly built one
        }
        $zip->addFile($file->getPathname(), $relative);
    }

    $zip->close();

    $filename = Illuminate\Support\Str::slug($client?->name ?? 'client') . '-static-' . now()->format('Y-m-d') . '.zip';

    return response()->download($zipPath, $filename, ['Content-Type' => 'application/zip'])
        ->deleteFileAfterSend(true);
})->middleware(['web', App\Http\Middleware\SetActiveClient::class]);

// Live per-client site: each client's unique URL on this server. Built fresh
// from the client's database on every request, so a CMS edit shows up on the
// next page load. The unguessable token is the access control — the link is
// only shared with that client.
Route::get('/site/{token}', function (string $token) {
    $client = App\Models\Client::where('public_token', $token)->firstOrFail();

    App\Support\ActiveClient::apply($client);
    $html = app(App\Services\StaticSiteBuilder::class)->build($client);

    return response($html, 200, [
        'Content-Type' => 'text/html; charset=UTF-8',
        'Cache-Control' => 'no-store', // always rebuilt — edits must show on reload
    ]);
})->where('token', '[A-Za-z0-9]{24}');

// Sibling files the frontend references by relative path (assets/jspdf,
// fonts, icons, artifacts/ sample downloads). Relative URLs on a
// /site/{token} page resolve to /site/<path>, so serve them from the same
// template directory. sw.js is deliberately NOT served here: its cache-first
// strategy would pin the page HTML and defeat "edits show on next reload".
Route::get('/site/{path}', function (string $path) {
    abort_if(basename($path) === 'sw.js', 404);

    $base = realpath(storage_path('app/static-template'));
    $full = realpath(storage_path('app/static-template/'.$path));
    abort_unless($base && $full && str_starts_with($full, $base.DIRECTORY_SEPARATOR) && is_file($full), 404);

    return response()->file($full, ['Cache-Control' => 'public, max-age=3600']);
})->where('path', '.+');
