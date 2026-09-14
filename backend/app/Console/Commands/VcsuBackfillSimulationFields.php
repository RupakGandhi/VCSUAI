<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ExtractsJsDataObjects;
use App\Models\Simulation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-parses SIM_DATA from the source index.html and backfills
 * verification_tips/followup_options onto EXISTING simulations rows --
 * the two fields VcsuImportHtml originally failed to capture, which the
 * frontend actively needs (see sendSim() in public/index.html).
 *
 * Deliberately NOT a full re-import: only UPDATEs the two new columns on
 * rows matched by module_id+role+title, and only when they're currently
 * empty. A row that already has data (an admin edited it through the new
 * Review page) is left untouched -- this command must never overwrite a
 * real edit with stale source-HTML data. Safe to re-run.
 */
class VcsuBackfillSimulationFields extends Command
{
    use ExtractsJsDataObjects;

    protected $signature = 'vcsu:backfill-sim-fields {path : Path to the source index.html file} {--dry-run : Report what would change without writing}';

    protected $description = 'Backfill verification_tips/followup_options onto existing simulations rows from the source HTML, without touching any other column or row';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $html = file_get_contents($path);

        $this->info('Extracting SIM_DATA...');
        $simData = $this->extractJsObject($html, 'SIM_DATA');

        $dryRun = (bool) $this->option('dry-run');

        $updated = 0;
        $alreadyHadData = 0;
        $unmatched = [];

        $apply = function () use ($simData, &$updated, &$alreadyHadData, &$unmatched) {
            foreach ($simData as $moduleId => $roles) {
                foreach ($roles as $role => $sims) {
                    foreach ($sims as $sim) {
                        $title = $sim['title'] ?? '';
                        $verificationTips = $sim['verification_tips'] ?? [];
                        $followupOptions = $sim['followup_options'] ?? [];

                        $row = Simulation::where('module_id', $moduleId)
                            ->where('role', $role)
                            ->where('title', $title)
                            ->first();

                        if (! $row) {
                            $unmatched[] = "{$moduleId} / {$role} / {$title}";

                            continue;
                        }

                        if (! empty($row->verification_tips) || ! empty($row->followup_options)) {
                            $alreadyHadData++;

                            continue;
                        }

                        $row->update([
                            'verification_tips' => $verificationTips,
                            'followup_options' => $followupOptions,
                        ]);
                        $updated++;
                    }
                }
            }
        };

        if ($dryRun) {
            DB::beginTransaction();
            $apply();
            DB::rollBack();
        } else {
            activity()->disableLogging();
            try {
                DB::transaction($apply);
            } finally {
                activity()->enableLogging();
            }
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run -- nothing was written:' : 'Backfill complete:');
        $this->table(['Outcome', 'Count'], [
            [$dryRun ? 'Would update' : 'Updated', $updated],
            ['Already had data (skipped)', $alreadyHadData],
            ['Unmatched (no DB row found)', count($unmatched)],
        ]);

        if (! empty($unmatched)) {
            $this->warn('Unmatched entries:');
            foreach ($unmatched as $u) {
                $this->line("  - {$u}");
            }
        }

        return self::SUCCESS;
    }
}
