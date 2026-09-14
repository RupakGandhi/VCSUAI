<?php

namespace App\Services;

use App\Http\Controllers\Api\ContentController;
use App\Models\Client;
use App\Models\PlatformSetting;
use App\Models\PracticePrompt;
use Illuminate\Support\Facades\Storage;

/**
 * Builds a self-contained HTML page: the frontend template with the ACTIVE
 * client's full content payload injected as window.VCSU_STATIC_DATA.
 *
 * The caller decides which client by pointing the 'content' connection
 * (ActiveClient::apply) BEFORE calling build(). Used by both the static
 * export download and the live per-client /site/{token} URLs — same file,
 * only the delivery differs (attachment vs. inline, cached never).
 */
class StaticSiteBuilder
{
    /**
     * Two modes, distinguished by $liveClient:
     *
     * - Live hosted site (/site/{token}): content data is injected but
     *   VCSU_STATIC_MODE is NOT set, so the survey and usage tracking stay
     *   active. The client's token is injected so those POSTs land in the
     *   right client's database. Gets a per-client PWA manifest.
     *
     * - Downloadable export (null): VCSU_STATIC_MODE=true — fully offline
     *   deliverable, survey and tracking disabled, relative manifest kept.
     */
    public function build(?Client $liveClient = null): string
    {
        $templatePath = storage_path('app/static-template/index.html');

        if (! file_exists($templatePath)) {
            abort(404, 'Template missing: upload the current index.html to storage/app/static-template/index.html');
        }

        $html = file_get_contents($templatePath);

        $payload = (new ContentController)->show()->getData(true);

        // JSON_HEX_TAG keeps any literal "</script>" inside content from
        // terminating the injected script block.
        $json = json_encode($payload, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($liveClient !== null) {
            $token = json_encode($liveClient->public_token);
            $inject = "<script>window.VCSU_STATIC_DATA={$json};window.VCSU_CLIENT_TOKEN={$token};</script>\n</head>";
        } else {
            $inject = "<script>window.VCSU_STATIC_MODE=true;window.VCSU_STATIC_DATA={$json};</script>\n</head>";
        }

        $headPos = strpos($html, '</head>');
        abort_unless($headPos !== false, 500, 'Template has no </head> tag');

        $html = substr_replace($html, $inject, $headPos, strlen('</head>'));

        if ($liveClient !== null) {
            $html = $this->replaceManifest($html, $payload['platform'] ?? [], $liveClient->publicUrl());
        }

        return $html;
    }

    /**
     * Standalone bundle for the ZIP export: the built HTML with sample-file
     * links rewritten to relative "sample-files/…" paths, plus the list of
     * referenced files on disk so the caller can zip them alongside.
     *
     * @return array{html: string, files: array<string, string>} files maps
     *   path-inside-zip => absolute path on this server's disk.
     */
    public function buildBundle(): array
    {
        $templatePath = storage_path('app/static-template/index.html');

        if (! file_exists($templatePath)) {
            abort(404, 'Template missing: upload the current index.html to storage/app/static-template/index.html');
        }

        $html = file_get_contents($templatePath);

        $payload = (new ContentController)->show()->getData(true);

        // Collect this client's sample files and rewrite their URLs to
        // relative paths, so the links keep working when the extracted
        // folder is opened locally or hosted anywhere.
        $files = [];
        $disk = Storage::disk('public');

        // Custom per-client PWA icons override the shared defaults later
        // copied in from static-template/assets/icons/ (ZipArchive::addFile
        // keeps the first entry written for a given in-zip path).
        $settings = PlatformSetting::current();
        foreach ([
            'icon_192' => 'assets/icons/icon-192.png',
            'icon_512' => 'assets/icons/icon-512.png',
            'icon_512_maskable' => 'assets/icons/icon-512-maskable.png',
        ] as $column => $inZip) {
            $path = $settings->{$column};
            if ($path && $disk->exists($path)) {
                $files[$inZip] = $disk->path($path);
            }
        }

        foreach (PracticePrompt::whereNotNull('sample_file')->pluck('sample_file') as $path) {
            if ($disk->exists($path)) {
                $files['sample-files/'.basename($path)] = $disk->path($path);
            }
        }

        foreach ($payload['modules'] ?? [] as $mid => $mod) {
            foreach ($mod['prompts'] ?? [] as $role => $prompts) {
                foreach ($prompts as $i => $p) {
                    if (! empty($p['sampleFileUrl'])) {
                        $payload['modules'][$mid]['prompts'][$role][$i]['sampleFileUrl'] =
                            'sample-files/'.basename($p['sampleFileUrl']);
                    }
                }
            }
        }

        $json = json_encode($payload, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $inject = "<script>window.VCSU_STATIC_MODE=true;window.VCSU_STATIC_DATA={$json};</script>\n</head>";

        $headPos = strpos($html, '</head>');
        abort_unless($headPos !== false, 500, 'Template has no </head> tag');
        $html = substr_replace($html, $inject, $headPos, strlen('</head>'));

        return ['html' => $html, 'files' => $files];
    }

    /**
     * The template's <link rel="manifest" href="manifest.json"> resolves to
     * /site/manifest.json on live links — shared across clients and with the
     * wrong start_url. Replace it with an inline per-client manifest so
     * "Install app" works and installs under the client's own name.
     */
    private function replaceManifest(string $html, array $platform, string $publicUrl): string
    {
        $name = $platform['title'] ?? 'AI Institute';

        $manifest = json_encode([
            'name' => $name,
            'short_name' => mb_substr($name, 0, 30),
            'description' => $platform['heroDesc'] ?? $name,
            'start_url' => $publicUrl,
            'scope' => url('/site/'),
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#f7fafc',
            'theme_color' => $platform['primaryColor'] ?? '#690720',
            'icons' => [
                ['src' => $platform['icon192Url'] ?? url('/site/assets/icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $platform['icon512Url'] ?? url('/site/assets/icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $platform['icon512MaskableUrl'] ?? url('/site/assets/icons/icon-512-maskable.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], JSON_UNESCAPED_SLASHES);

        return str_replace(
            '<link rel="manifest" href="manifest.json">',
            '<link rel="manifest" href="data:application/manifest+json;base64,'.base64_encode($manifest).'">',
            $html
        );
    }
}
