<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\ModuleOverviewSection;
use App\Models\PracticePrompt;
use DOMDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Parses per-role Challenge, Key Concept, Why It Matters, and Strategy text
 * from the source index.html and populates module_overview_sections rows.
 *
 * Uses firstOrCreate: rows that already exist (admin-edited) are never
 * overwritten. Safe to re-run.
 */
class VcsuBackfillOverviewSections extends Command
{
    protected $signature = 'vcsu:backfill-overview-sections
        {path : Path to the source index.html file}
        {--dry-run : Report what would be created without writing}';

    protected $description = 'Backfill per-role challenge/concept/matters/strategy rows from index.html into module_overview_sections';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $html = file_get_contents($path);

        $this->info('Parsing HTML document...');
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $crawler = new Crawler($dom);

        $dryRun = (bool) $this->option('dry-run');
        $moduleIds = Module::pluck('id')->all();
        $roles = PracticePrompt::ROLES;

        $counts = [
            'challenge' => 0,
            'concept'   => 0,
            'matters'   => 0,
            'strategy'  => 0,
        ];
        $skipped = 0;

        $apply = function () use ($crawler, $moduleIds, $roles, &$counts, &$skipped) {
            foreach ($moduleIds as $mid) {
                $escaped = $this->cssEscape($mid);
                $overview = $crawler->filter('#phase-overview-' . $escaped);
                $learn    = $crawler->filter('#phase-learn-' . $escaped);

                // --- Challenge (per-role) ---
                if ($overview->count()) {
                    $challengeBox = $overview->filter('.challenge-box');
                    if ($challengeBox->count()) {
                        $challengeBox->filter('.role-content')->each(function (Crawler $node) use ($mid, &$counts, &$skipped) {
                            $role = $node->attr('data-role');
                            if (! $role) {
                                return;
                            }
                            $p = $node->filter('p');
                            $text = $p->count() ? trim($p->first()->text()) : '';
                            if ($text === '') {
                                return;
                            }
                            $existing = ModuleOverviewSection::where([
                                'module_id' => $mid,
                                'section'   => 'challenge',
                                'role'      => $role,
                            ])->exists();
                            if ($existing) {
                                $skipped++;

                                return;
                            }
                            ModuleOverviewSection::create([
                                'module_id'  => $mid,
                                'section'    => 'challenge',
                                'role'       => $role,
                                'content'    => $text,
                                'sort_order' => 0,
                            ]);
                            $counts['challenge']++;
                        });
                    }
                }

                // --- Key Concept (per-role) ---
                if ($overview->count()) {
                    $conceptBox = $overview->filter('.concept-box');
                    if ($conceptBox->count()) {
                        $conceptBox->filter('.role-content')->each(function (Crawler $node) use ($mid, &$counts, &$skipped) {
                            $role = $node->attr('data-role');
                            if (! $role) {
                                return;
                            }
                            $p = $node->filter('p');
                            $text = $p->count() ? trim($p->first()->text()) : '';
                            if ($text === '') {
                                return;
                            }
                            $existing = ModuleOverviewSection::where([
                                'module_id' => $mid,
                                'section'   => 'concept',
                                'role'      => $role,
                            ])->exists();
                            if ($existing) {
                                $skipped++;

                                return;
                            }
                            ModuleOverviewSection::create([
                                'module_id'  => $mid,
                                'section'    => 'concept',
                                'role'       => $role,
                                'content'    => $text,
                                'sort_order' => 0,
                            ]);
                            $counts['concept']++;
                        });
                    }
                }

                // --- Why It Matters (shared text → one row per role) ---
                if ($overview->count()) {
                    $mattersP = $overview->filter('.matters-box > p');
                    if ($mattersP->count()) {
                        $text = trim($mattersP->first()->text());
                        if ($text !== '') {
                            foreach (PracticePrompt::ROLES as $role) {
                                $existing = ModuleOverviewSection::where([
                                    'module_id' => $mid,
                                    'section'   => 'matters',
                                    'role'      => $role,
                                ])->exists();
                                if ($existing) {
                                    $skipped++;

                                    continue;
                                }
                                ModuleOverviewSection::create([
                                    'module_id'  => $mid,
                                    'section'    => 'matters',
                                    'role'       => $role,
                                    'content'    => $text,
                                    'sort_order' => 0,
                                ]);
                                $counts['matters']++;
                            }
                        }
                    }
                }

                // --- Strategies (per-role, multiple items) ---
                if ($learn->count()) {
                    $learn->filter('.role-content')->each(function (Crawler $node) use ($mid, &$counts, &$skipped) {
                        $role = $node->attr('data-role');
                        if (! $role) {
                            return;
                        }
                        $items = $node->filter('.strategy-list li')->each(fn (Crawler $li) => trim($li->text()));
                        $items = array_values(array_filter($items, fn ($t) => $t !== ''));
                        if (empty($items)) {
                            return;
                        }
                        foreach ($items as $sortOrder => $text) {
                            $existing = ModuleOverviewSection::where([
                                'module_id'  => $mid,
                                'section'    => 'strategy',
                                'role'       => $role,
                                'sort_order' => $sortOrder,
                            ])->exists();
                            if ($existing) {
                                $skipped++;

                                continue;
                            }
                            ModuleOverviewSection::create([
                                'module_id'  => $mid,
                                'section'    => 'strategy',
                                'role'       => $role,
                                'content'    => $text,
                                'sort_order' => $sortOrder,
                            ]);
                            $counts['strategy']++;
                        }
                    });
                }
            }
        };

        if ($dryRun) {
            DB::beginTransaction();
            activity()->disableLogging();
            try {
                $apply();
            } finally {
                activity()->enableLogging();
                DB::rollBack();
            }
        } else {
            activity()->disableLogging();
            try {
                DB::transaction($apply);
            } finally {
                activity()->enableLogging();
            }
        }

        $total = array_sum($counts);
        $this->newLine();
        $this->info($dryRun ? 'Dry run — nothing was written:' : 'Backfill complete:');
        $this->table(['Section', $dryRun ? 'Would create' : 'Created'], [
            ['challenge', $counts['challenge']],
            ['concept',   $counts['concept']],
            ['matters',   $counts['matters']],
            ['strategy',  $counts['strategy']],
            ['TOTAL',     $total],
        ]);
        $this->line("Skipped (already existed): {$skipped}");

        return self::SUCCESS;
    }

    private function cssEscape(string $id): string
    {
        return str_replace('.', '\\.', $id);
    }
}
