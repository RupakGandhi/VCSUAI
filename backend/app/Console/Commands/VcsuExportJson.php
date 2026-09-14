<?php

namespace App\Console\Commands;

use App\Services\ContentExportService;
use Illuminate\Console\Command;

class VcsuExportJson extends Command
{
    protected $signature = 'vcsu:export-json {path=storage/app/vcsu-export.json : Where to write the export file}';

    protected $description = 'Export all curriculum content to a single JSON file (backup / portability package)';

    public function handle(ContentExportService $exporter): int
    {
        $data = $exporter->export();
        $path = $this->argument('path');

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->info("Exported to {$path} (".number_format(filesize($path) / 1024, 1).' KB)');
        $this->table(['Section', 'Count'], [
            ['strands', count($data['strands'])],
            ['courses', count($data['courses'])],
            ['modules', count($data['modules'])],
            ['gsPages', count($data['gsPages'])],
        ]);

        return self::SUCCESS;
    }
}
