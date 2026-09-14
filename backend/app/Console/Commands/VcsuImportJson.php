<?php

namespace App\Console\Commands;

use App\Services\ContentImportService;
use Illuminate\Console\Command;

class VcsuImportJson extends Command
{
    protected $signature = 'vcsu:import-json {path : Path to a JSON file in the vcsu:export-json format}';

    protected $description = 'Bulk import/update curriculum content from a JSON file (safe partial updates -- see ContentImportService)';

    public function handle(ContentImportService $importer): int
    {
        $path = $this->argument('path');
        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($path), true);
        if ($data === null) {
            $this->error('Invalid JSON.');

            return self::FAILURE;
        }

        $summary = $importer->import($data);

        $this->info('Import complete:');
        $this->table(['Section', 'Rows affected'], array_map(
            fn ($k, $v) => [$k, $v],
            array_keys($summary),
            array_values($summary)
        ));

        return self::SUCCESS;
    }
}
