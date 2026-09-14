<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ExtractsJsDataObjects;
use App\Models\ApplyDeliverable;
use App\Models\Course;
use App\Models\GsBlock;
use App\Models\GsPage;
use App\Models\MasteryPrompt;
use App\Models\Module;
use App\Models\ModuleContent;
use App\Models\PlatformSetting;
use App\Models\PracticePrompt;
use App\Models\Simulation;
use App\Models\Strand;
use DOMDocument;
use DOMElement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Imports all content from the existing single-file index.html into the
 * database, so the admin dashboard starts pre-populated with exactly what's
 * already live. Safe to re-run: truncates the content tables it owns before
 * re-inserting (platform_settings/usage_events/users are left untouched).
 */
class VcsuImportHtml extends Command
{
    use ExtractsJsDataObjects;

    protected $signature = 'vcsu:import-html {path : Path to the source index.html file}';

    protected $description = 'Import strands/courses/modules/content/prompts/deliverables/simulations/getting-started pages from the existing index.html into the database';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $html = file_get_contents($path);

        $this->info('Extracting CMS_DATA_DEFAULT and SIM_DATA...');
        $cmsData = $this->extractJsObject($html, 'CMS_DATA_DEFAULT');
        $simData = $this->extractJsObject($html, 'SIM_DATA');

        $this->info('Parsing HTML document...');
        // Native DOMDocument, not masterminds/html5: the latter binds elements
        // to the XHTML namespace, which silently breaks unprefixed tag-name
        // CSS/XPath selectors (h5, p, h3) in Symfony's Crawler::filter()
        // while class/attribute selectors keep working — a confusing partial
        // failure. Plain libxml parsing avoids the namespace entirely and is
        // sufficient for this document (no exotic HTML5-only syntax in it).
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $crawler = new Crawler($dom);

        // This one-time migration creates thousands of rows; logging each
        // as an "edit" would drown out real admin activity later. try/
        // finally so a failure here can't leave logging off for whatever
        // request a long-lived worker handles next.
        activity()->disableLogging();
        try {
            DB::transaction(function () use ($cmsData, $simData, $crawler) {
                $this->importPlatformSettings($cmsData['platform'] ?? []);
                $this->importStructure($cmsData);
                $this->importModuleContent($crawler);
                $this->importPracticePrompts($crawler);
                $this->importMasteryPrompts($crawler);
                $this->importApplyDeliverables($crawler);
                $this->importSimulations($simData);
                $this->importGettingStarted($crawler);
            });
        } finally {
            activity()->enableLogging();
        }

        $this->newLine();
        $this->info('Import complete:');
        $this->table(['Table', 'Rows'], [
            ['strands', Strand::count()],
            ['courses', Course::count()],
            ['modules', Module::count()],
            ['module_contents', ModuleContent::count()],
            ['practice_prompts', PracticePrompt::count()],
            ['mastery_prompts', MasteryPrompt::count()],
            ['apply_deliverables', ApplyDeliverable::count()],
            ['simulations', Simulation::count()],
            ['gs_pages', GsPage::count()],
            ['gs_blocks', GsBlock::count()],
        ]);

        return self::SUCCESS;
    }

    private function importPlatformSettings(array $platform): void
    {
        PlatformSetting::query()->delete();
        PlatformSetting::create([
            'title' => $platform['title'] ?? 'VCSU AI Institute for Teaching and Learning',
            'subtitle' => $platform['subtitle'] ?? null,
            'page_title' => $platform['pageTitle'] ?? null,
            'hero_title' => $platform['heroTitle'] ?? null,
            'hero_desc' => $platform['heroDesc'] ?? null,
            'hero_stats' => $platform['heroStats'] ?? null,
            'primary_color' => $platform['primaryColor'] ?? null,
            'accent_color' => $platform['accentColor'] ?? null,
            'password' => $platform['password'] ?? null,
            'fac_password' => $platform['facPassword'] ?? null,
        ]);
        $this->line('  Platform settings imported.');
    }

    private function importStructure(array $cmsData): void
    {
        // Children tables cascade-delete from strands, so this clears
        // courses/modules too.
        Strand::query()->delete();

        foreach (($cmsData['strands'] ?? []) as $i => $strand) {
            Strand::create([
                'id' => $strand['id'],
                'title' => $strand['title'],
                'color_class' => $strand['colorClass'] ?? '',
                'sort_order' => $i,
            ]);
        }

        $moduleTitles = $cmsData['modules'] ?? [];

        foreach (($cmsData['courses'] ?? []) as $courseId => $course) {
            Course::create([
                'id' => $courseId,
                'strand_id' => $course['strandId'],
                'title' => $course['title'],
                'description' => $course['desc'] ?? null,
                'sort_order' => 0,
            ]);

            foreach (($course['modules'] ?? []) as $i => $moduleId) {
                Module::create([
                    'id' => $moduleId,
                    'course_id' => $courseId,
                    'title' => $moduleTitles[$moduleId]['title'] ?? $moduleId,
                    'sort_order' => $i,
                ]);
            }
        }

        $this->line('  Strands/courses/modules imported: '.Strand::count().'/'.Course::count().'/'.Module::count());
    }

    private function innerHtml(DOMElement $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }

        return trim($html);
    }

    private function importModuleContent(Crawler $crawler): void
    {
        ModuleContent::query()->delete();
        $count = 0;

        foreach (Module::pluck('id') as $moduleId) {
            $overview = $crawler->filter('#phase-overview-'.$this->cssEscape($moduleId));
            $learn = $crawler->filter('#phase-learn-'.$this->cssEscape($moduleId));
            $facilitator = $crawler->filter('#phase-facilitator-'.$this->cssEscape($moduleId));

            if ($overview->count() === 0) {
                $this->warn("  No overview phase found for module {$moduleId}, skipping content.");

                continue;
            }

            $ilos = $overview->filter('.ilo-section .ilo-tag')->each(fn (Crawler $n) => trim($n->text()));
            $challenge = $overview->filter('.challenge-box p')->count() ? trim($overview->filter('.challenge-box p')->text()) : null;
            $concept = $overview->filter('.concept-box p')->count() ? trim($overview->filter('.concept-box p')->text()) : null;
            $matters = $overview->filter('.matters-box p')->count() ? trim($overview->filter('.matters-box p')->text()) : null;

            ModuleContent::create([
                'module_id' => $moduleId,
                'ilos' => $ilos,
                'challenge' => $challenge,
                'concept' => $concept,
                'matters' => $matters,
                'learn_html' => $learn->count() ? $this->innerHtml($learn->getNode(0)) : null,
                'facilitator_html' => $facilitator->count() ? $this->innerHtml($facilitator->getNode(0)) : null,
            ]);
            $count++;
        }

        $this->line("  Module content imported: {$count} modules.");
    }

    private function importPracticePrompts(Crawler $crawler): void
    {
        PracticePrompt::query()->delete();
        $count = 0;

        foreach (Module::pluck('id') as $moduleId) {
            $practice = $crawler->filter('#phase-practice-'.$this->cssEscape($moduleId));
            if ($practice->count() === 0) {
                continue;
            }

            $practice->filter('.role-content')->each(function (Crawler $roleContent) use ($moduleId, &$count) {
                $role = $roleContent->attr('data-role');
                if (! $role) {
                    return;
                }

                $roleContent->filter('.prompt-card')->each(function (Crawler $card, $i) use ($moduleId, $role, &$count) {
                    $h5 = $card->filter('h5');
                    if ($h5->count() === 0) {
                        return;
                    }

                    $aiTool = $h5->filter('.model-rec')->count() ? trim($h5->filter('.model-rec')->text()) : null;
                    // Title is the h5 text with the model-rec span's text removed.
                    $title = trim(str_replace($aiTool ?? '', '', $h5->text()));
                    $promptText = $card->filter('.prompt-text')->count() ? trim($card->filter('.prompt-text')->text()) : '';

                    PracticePrompt::create([
                        'module_id' => $moduleId,
                        'role' => $role,
                        'title' => $title,
                        'ai_tool' => $aiTool,
                        'prompt_text' => $promptText,
                        'sort_order' => $i,
                    ]);
                    $count++;
                });
            });
        }

        $this->line("  Practice prompts imported: {$count}.");
    }

    private function importMasteryPrompts(Crawler $crawler): void
    {
        MasteryPrompt::query()->delete();
        $count = 0;

        foreach (Module::pluck('id') as $moduleId) {
            $apply = $crawler->filter('#phase-apply-'.$this->cssEscape($moduleId));
            if ($apply->count() === 0) {
                continue;
            }

            // Mastery Prompts live in their own content-card within the Apply
            // phase, identified by its "Mastery Prompts" heading -- not to be
            // confused with the deliverables section, which reuses the same
            // .role-content[data-role] markup pattern within the same phase.
            $masteryCard = null;
            $apply->filter('.content-card')->each(function (Crawler $card) use (&$masteryCard) {
                if ($masteryCard) {
                    return;
                }
                $h3 = $card->filter('h3');
                if ($h3->count() && trim($h3->text()) === 'Mastery Prompts') {
                    $masteryCard = $card;
                }
            });
            if (! $masteryCard) {
                continue;
            }

            $masteryCard->filter('.role-content')->each(function (Crawler $roleContent) use ($moduleId, &$count) {
                $role = $roleContent->attr('data-role');
                if (! $role) {
                    return;
                }

                $roleContent->filter('.prompt-card.mastery')->each(function (Crawler $card, $i) use ($moduleId, $role, &$count) {
                    $h5 = $card->filter('h5');
                    if ($h5->count() === 0) {
                        return;
                    }

                    $promptText = $card->filter('.prompt-text')->count() ? trim($card->filter('.prompt-text')->text()) : '';

                    MasteryPrompt::create([
                        'module_id' => $moduleId,
                        'role' => $role,
                        'title' => trim($h5->text()),
                        'prompt_text' => $promptText,
                        'sort_order' => $i,
                    ]);
                    $count++;
                });
            });
        }

        $this->line("  Mastery prompts imported: {$count}.");
    }

    private function importApplyDeliverables(Crawler $crawler): void
    {
        ApplyDeliverable::query()->delete();
        $count = 0;

        foreach (Module::pluck('id') as $moduleId) {
            $apply = $crawler->filter('#phase-apply-'.$this->cssEscape($moduleId));
            if ($apply->count() === 0) {
                continue;
            }

            $apply->filter('.role-content')->each(function (Crawler $roleContent) use ($moduleId, &$count) {
                $role = $roleContent->attr('data-role');
                if (! $role) {
                    return;
                }

                $roleContent->filter('.deliverable-card')->each(function (Crawler $card, $i) use ($moduleId, $role, &$count) {
                    $h5 = $card->filter('h5');
                    $p = $card->filter('p');
                    if ($h5->count() === 0) {
                        return;
                    }

                    ApplyDeliverable::create([
                        'module_id' => $moduleId,
                        'role' => $role,
                        'title' => trim($h5->text()),
                        'description' => $p->count() ? trim($p->first()->text()) : '',
                        'sort_order' => $i,
                    ]);
                    $count++;
                });
            });
        }

        $this->line("  Apply deliverables imported: {$count}.");
    }

    private function importSimulations(array $simData): void
    {
        Simulation::query()->delete();
        $count = 0;

        foreach ($simData as $moduleId => $roles) {
            if (! Module::whereKey($moduleId)->exists()) {
                continue;
            }

            foreach ($roles as $role => $sims) {
                foreach ($sims as $i => $sim) {
                    Simulation::create([
                        'module_id' => $moduleId,
                        'role' => $role,
                        'title' => $sim['title'] ?? '',
                        'prompt_text' => $sim['prompt_text'] ?? '',
                        'keywords' => $sim['keywords'] ?? [],
                        'response' => $sim['response'] ?? '',
                        'verification_tips' => $sim['verification_tips'] ?? [],
                        'followup_options' => $sim['followup_options'] ?? [],
                        'sort_order' => $i,
                    ]);
                    $count++;
                }
            }
        }

        $this->line("  Simulations imported: {$count}.");
    }

    private function importGettingStarted(Crawler $crawler): void
    {
        GsPage::query()->delete(); // cascades to gs_blocks
        $pageCount = 0;
        $blockCount = 0;

        for ($i = 1; $i <= 5; $i++) {
            $page = $crawler->filter('#ml-page-'.$i);
            if ($page->count() === 0) {
                continue;
            }

            $title = $page->filter('.page-header h1')->count() ? trim($page->filter('.page-header h1')->text()) : "Page {$i}";
            $descNode = $page->filter('.page-header p');
            $descHtml = $descNode->count() ? $this->innerHtml($descNode->getNode(0)) : null;

            $gsPage = GsPage::create([
                'title' => $title,
                'description_html' => $descHtml,
                'sort_order' => $i,
            ]);
            $pageCount++;

            $page->filter('.content-card')->each(function (Crawler $card, $j) use ($gsPage, &$blockCount) {
                $h3 = $card->filter('h3');
                if ($h3->count() === 0) {
                    return;
                }

                // Content is everything in the card after the h3 heading.
                $node = $card->getNode(0);
                $contentHtml = '';
                $afterH3 = false;
                foreach ($node->childNodes as $child) {
                    if ($child instanceof DOMElement && strtolower($child->tagName) === 'h3') {
                        $afterH3 = true;

                        continue;
                    }
                    if ($afterH3) {
                        $contentHtml .= $node->ownerDocument->saveHTML($child);
                    }
                }

                GsBlock::create([
                    'gs_page_id' => $gsPage->id,
                    'title' => trim($h3->text()),
                    'content_html' => trim($contentHtml),
                    'sort_order' => $j,
                ]);
                $blockCount++;
            });
        }

        $this->line("  Getting Started pages/blocks imported: {$pageCount}/{$blockCount}.");
    }

    /** Escape a module id like "T1.1" for use in a CSS id selector (the "." needs escaping). */
    private function cssEscape(string $id): string
    {
        return str_replace('.', '\\.', $id);
    }
}
