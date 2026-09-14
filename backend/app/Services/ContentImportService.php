<?php

namespace App\Services;

use App\Models\ApplyDeliverable;
use App\Models\Course;
use App\Models\GsBlock;
use App\Models\GsPage;
use App\Models\MasteryPrompt;
use App\Models\Module;
use App\Models\ModuleContent;
use App\Models\ModuleOverviewSection;
use App\Models\PlatformSetting;
use App\Models\PracticePrompt;
use App\Models\Simulation;
use App\Models\Strand;
use Illuminate\Support\Facades\DB;

/**
 * Consumes the same JSON shape ContentExportService produces. Designed for
 * safe partial updates (a "40% content refresh," not a full reseed):
 *
 *  - Every section is OPTIONAL. Omit "courses" entirely and no course is
 *    touched.
 *  - Strands/courses/modules are upserted by id: existing rows are
 *    updated in place, new ids are created. Nothing is ever deleted at
 *    this level, even if missing from the import -- an incomplete file
 *    can't accidentally wipe content.
 *  - A module's nested prompts/deliverables/simulations are the one
 *    exception: if a module's "prompts" key IS present, that module's
 *    existing prompts are replaced wholesale with the provided list
 *    (this is the only way to let an admin delete/reorder prompts via
 *    import). If the key is ABSENT, existing prompts are left alone.
 */
class ContentImportService
{
    /** @return array{summary: array<string,int>} */
    public function import(array $data): array
    {
        $summary = [
            'platform' => 0, 'strands' => 0, 'courses' => 0, 'modules' => 0,
            'overviewSections' => 0, 'prompts' => 0, 'masteryPrompts' => 0, 'deliverables' => 0,
            'simulations' => 0, 'gsPages' => 0, 'gsBlocks' => 0,
        ];

        // A bulk import can touch 1000+ rows in one run; logging every one
        // as a separate "change" would drown out real admin edits in the
        // Activity Log. The import itself isn't the kind of edit that log
        // is for. try/finally so a failed import can't leave logging
        // disabled for every later request handled by the same worker.
        activity()->disableLogging();

        try {
            DB::transaction(function () use ($data, &$summary) {
                if (isset($data['platform'])) {
                    $this->importPlatform($data['platform']);
                    $summary['platform'] = 1;
                }

                foreach ($data['strands'] ?? [] as $row) {
                    $this->importStrand($row);
                    $summary['strands']++;
                }

                foreach ($data['courses'] ?? [] as $row) {
                    $this->importCourse($row);
                    $summary['courses']++;
                }

                foreach ($data['modules'] ?? [] as $row) {
                    $counts = $this->importModule($row);
                    $summary['modules']++;
                    $summary['overviewSections'] += $counts['overviewSections'];
                    $summary['prompts'] += $counts['prompts'];
                    $summary['masteryPrompts'] += $counts['masteryPrompts'];
                    $summary['deliverables'] += $counts['deliverables'];
                    $summary['simulations'] += $counts['simulations'];
                }

                foreach ($data['gsPages'] ?? [] as $row) {
                    $summary['gsPages']++;
                    $summary['gsBlocks'] += $this->importGsPage($row);
                }
            });
        } finally {
            activity()->enableLogging();
        }

        return $summary;
    }

    private function importPlatform(array $p): void
    {
        $settings = PlatformSetting::current();
        $settings->fill([
            'title' => $p['title'] ?? $settings->title,
            'subtitle' => $p['subtitle'] ?? $settings->subtitle,
            'page_title' => $p['pageTitle'] ?? $settings->page_title,
            'hero_title' => $p['heroTitle'] ?? $settings->hero_title,
            'hero_desc' => $p['heroDesc'] ?? $settings->hero_desc,
            'hero_stats' => $p['heroStats'] ?? $settings->hero_stats,
            'primary_color' => $p['primaryColor'] ?? $settings->primary_color,
            'accent_color' => $p['accentColor'] ?? $settings->accent_color,
            'password' => $p['password'] ?? $settings->password,
            'fac_password' => $p['facPassword'] ?? $settings->fac_password,
            'footer_html' => $p['footerHtml'] ?? $settings->footer_html,
            'role_labels' => $p['roleLabels'] ?? $settings->role_labels,
            'ai_tool_name' => $p['aiToolName'] ?? $settings->ai_tool_name,
            'survey_q1_text' => $p['surveyQ1'] ?? $settings->survey_q1_text,
            'survey_q2_text' => $p['surveyQ2'] ?? $settings->survey_q2_text,
            'survey_q3_text' => $p['surveyQ3'] ?? $settings->survey_q3_text,
        ])->save();
    }

    private function importStrand(array $row): void
    {
        $strand = Strand::firstOrNew(['id' => $row['id']]);
        $strand->fill($this->onlyPresent($row, [
            'title' => 'title', 'colorClass' => 'color_class', 'sortOrder' => 'sort_order',
        ]))->save();
    }

    private function importCourse(array $row): void
    {
        $course = Course::firstOrNew(['id' => $row['id']]);
        $course->fill($this->onlyPresent($row, [
            'strandId' => 'strand_id', 'title' => 'title',
            'description' => 'description', 'sortOrder' => 'sort_order',
        ]))->save();
    }

    /**
     * Map only the JSON keys actually present in $row to their column
     * names, so omitted fields keep their existing value on update
     * instead of being overwritten with null.
     */
    private function onlyPresent(array $row, array $keyToColumn): array
    {
        $out = [];
        foreach ($keyToColumn as $jsonKey => $column) {
            if (array_key_exists($jsonKey, $row)) {
                $out[$column] = $row[$jsonKey];
            }
        }

        return $out;
    }

    /** @return array{overviewSections: int, prompts: int, masteryPrompts: int, deliverables: int, simulations: int} */
    private function importModule(array $row): array
    {
        $module = Module::firstOrNew(['id' => $row['id']]);
        $module->fill($this->onlyPresent($row, [
            'courseId' => 'course_id', 'title' => 'title', 'sortOrder' => 'sort_order',
            'requiresFileUpload' => 'requires_file_upload',
        ]))->save();

        if (isset($row['content'])) {
            // Field-level partial update: only overwrite keys actually
            // present in the import. A file that only sets "challenge"
            // must not null out ilos/concept/matters/learnHtml/
            // facilitatorHtml on the existing row.
            $c = $row['content'];
            $fieldMap = [
                'ilos' => 'ilos', 'challenge' => 'challenge', 'concept' => 'concept',
                'matters' => 'matters', 'learnHtml' => 'learn_html', 'facilitatorHtml' => 'facilitator_html',
            ];
            $updates = [];
            foreach ($fieldMap as $jsonKey => $column) {
                if (array_key_exists($jsonKey, $c)) {
                    $updates[$column] = $c[$jsonKey];
                }
            }
            $content = ModuleContent::firstOrNew(['module_id' => $module->id]);
            $content->fill($updates)->save();
        }

        $counts = ['overviewSections' => 0, 'prompts' => 0, 'masteryPrompts' => 0, 'deliverables' => 0, 'simulations' => 0];

        if (isset($row['prompts'])) {
            PracticePrompt::where('module_id', $module->id)->delete();
            foreach ($row['prompts'] as $p) {
                PracticePrompt::create([
                    'module_id' => $module->id,
                    'role' => $p['role'],
                    'title' => $p['title'],
                    'ai_tool' => $p['aiTool'] ?? null,
                    'prompt_text' => $p['promptText'],
                    'sample_file' => $p['sampleFile'] ?? null,
                    'sort_order' => $p['sortOrder'] ?? 0,
                ]);
                $counts['prompts']++;
            }
        }

        if (isset($row['masteryPrompts'])) {
            MasteryPrompt::where('module_id', $module->id)->delete();
            foreach ($row['masteryPrompts'] as $p) {
                MasteryPrompt::create([
                    'module_id' => $module->id,
                    'role' => $p['role'],
                    'title' => $p['title'],
                    'prompt_text' => $p['promptText'],
                    'sort_order' => $p['sortOrder'] ?? 0,
                ]);
                $counts['masteryPrompts']++;
            }
        }

        if (isset($row['deliverables'])) {
            ApplyDeliverable::where('module_id', $module->id)->delete();
            foreach ($row['deliverables'] as $d) {
                ApplyDeliverable::create([
                    'module_id' => $module->id,
                    'role' => $d['role'],
                    'title' => $d['title'],
                    'description' => $d['description'],
                    'initial_prompt' => $d['initialPrompt'] ?? null,
                    'refine_prompt' => $d['refinePrompt'] ?? null,
                    'sort_order' => $d['sortOrder'] ?? 0,
                ]);
                $counts['deliverables']++;
            }
        }

        if (isset($row['simulations'])) {
            Simulation::where('module_id', $module->id)->delete();
            foreach ($row['simulations'] as $s) {
                Simulation::create([
                    'module_id' => $module->id,
                    'role' => $s['role'],
                    'title' => $s['title'],
                    'prompt_text' => $s['promptText'],
                    'keywords' => $s['keywords'] ?? [],
                    'response' => $s['response'],
                    'verification_tips' => $s['verificationTips'] ?? [],
                    'followup_options' => $s['followupOptions'] ?? [],
                    'bias_check_tips' => $s['biasCheckTips'] ?? [],
                    'expected_filename' => $s['expectedFilename'] ?? null,
                    'sort_order' => $s['sortOrder'] ?? 0,
                ]);
                $counts['simulations']++;
            }
        }

        if (isset($row['overviewSections'])) {
            ModuleOverviewSection::where('module_id', $module->id)->delete();
            foreach ($row['overviewSections'] as $s) {
                ModuleOverviewSection::create([
                    'module_id'  => $module->id,
                    'section'    => $s['section'],
                    'role'       => $s['role'],
                    'content'    => $s['content'],
                    'sort_order' => $s['sortOrder'] ?? 0,
                ]);
                $counts['overviewSections']++;
            }
        }

        return $counts;
    }

    private function importGsPage(array $row): int
    {
        $attrs = [
            'title' => $row['title'],
            'description_html' => $row['descriptionHtml'] ?? null,
            'sort_order' => $row['sortOrder'] ?? 0,
        ];

        $page = isset($row['id']) && GsPage::whereKey($row['id'])->exists()
            ? tap(GsPage::find($row['id']))->update($attrs)
            : GsPage::create($attrs);

        $blockCount = 0;
        if (isset($row['blocks'])) {
            GsBlock::where('gs_page_id', $page->id)->delete();
            foreach ($row['blocks'] as $b) {
                GsBlock::create([
                    'gs_page_id' => $page->id,
                    'title' => $b['title'],
                    'content_html' => $b['contentHtml'],
                    'sort_order' => $b['sortOrder'] ?? 0,
                ]);
                $blockCount++;
            }
        }

        return $blockCount;
    }
}
