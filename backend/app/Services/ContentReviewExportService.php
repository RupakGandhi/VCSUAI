<?php

namespace App\Services;

use App\Models\Module;
use App\Models\PlatformSetting;

/**
 * Gathers the currently-active client's editable content (Challenges,
 * Practice Prompts, Mastery Prompts, Deliverables), organized Module > Role
 * > content-type. Two consumers of the same underlying data:
 *
 *  - export() formats it as the plain-text document (same shape as the
 *    "Module Challenges by Role" / "Revised Deliverable Prompts" Word docs
 *    already proven to work well for human review and AI-assisted editing --
 *    ContentReviewImportService::parse() consumes the exact same format
 *    back), so admins never have to read or hand-edit raw JSON to validate
 *    an AI-assisted content revision.
 *  - gatherData() exposes the same structure directly for the read-only
 *    Content Browser admin page, so browsing for cohesion doesn't require
 *    downloading a file first.
 */
class ContentReviewExportService
{
    public const ROLES = ['classroom', 'leader', 'sped', 'support', 'coach', 'higher_ed'];

    /**
     * @return list<array{id: string, title: string, roles: array<string, array{
     *   label: string,
     *   challenge: ?string,
     *   practicePrompts: list<array{title:string, aiTool:?string, promptText:string}>,
     *   masteryPrompts: list<array{title:string, promptText:string}>,
     *   deliverables: list<array{description:string, initialPrompt:?string, refinePrompt:?string}>,
     * }>}>
     */
    public function gatherData(): array
    {
        $roleLabels = $this->roleLabels();

        $modules = Module::with(['content', 'practicePrompts', 'masteryPrompts', 'applyDeliverables', 'overviewSections'])
            ->orderBy('id')->get();

        return $modules->map(function (Module $module) use ($roleLabels) {
            $challenges = $module->overviewSections->where('section', 'challenge')->keyBy('role');
            $practicePromptsByRole = $module->practicePrompts->groupBy('role');
            $masteryPromptsByRole = $module->masteryPrompts->groupBy('role');
            $deliverablesByRole = $module->applyDeliverables->groupBy('role');

            $roles = [];
            foreach (self::ROLES as $role) {
                $roles[$role] = [
                    'label' => $roleLabels[$role],
                    'challenge' => $challenges[$role]->content ?? null,
                    'practicePrompts' => ($practicePromptsByRole[$role] ?? collect())->sortBy('sort_order')->values()
                        ->map(fn ($p) => ['title' => $p->title, 'aiTool' => $p->ai_tool ?: null, 'promptText' => $p->prompt_text])
                        ->all(),
                    'masteryPrompts' => ($masteryPromptsByRole[$role] ?? collect())->sortBy('sort_order')->values()
                        ->map(fn ($p) => ['title' => $p->title, 'promptText' => $p->prompt_text])
                        ->all(),
                    'deliverables' => ($deliverablesByRole[$role] ?? collect())->sortBy('sort_order')->values()
                        ->map(fn ($d) => ['description' => $d->description, 'initialPrompt' => $d->initial_prompt ?: null, 'refinePrompt' => $d->refine_prompt ?: null])
                        ->all(),
                ];
            }

            return ['id' => $module->id, 'title' => $module->title, 'roles' => $roles];
        })->all();
    }

    public function export(): string
    {
        $data = $this->gatherData();

        $out = "VCSU AI Institute -- Content Review Export\n";
        $out .= 'Generated: '.now()->toDateTimeString()."\n\n";
        $out .= "Do not change the labeled markers (lines starting with '=== MODULE', '--- ROLE', '## ', '### '). Edit the text underneath them freely.\n";

        foreach ($data as $module) {
            $out .= "\n=== MODULE: {$module['id']}: {$module['title']} ===\n";

            foreach ($module['roles'] as $role => $roleData) {
                // The [role-key] suffix is what the importer actually reads --
                // it's kept stable even if a client customizes the display
                // label, so don't remove it when editing the label text.
                $out .= "\n--- ROLE: {$roleData['label']} [{$role}] ---\n";

                $out .= "\n## Challenge\n";
                $out .= ($roleData['challenge'] ?? '(none)')."\n";

                $out .= "\n## Practice Prompts\n";
                foreach ($roleData['practicePrompts'] as $i => $p) {
                    $out .= "\n### Prompt ".($i + 1).": {$p['title']}\n";
                    if ($p['aiTool']) {
                        $out .= "AI Tool: {$p['aiTool']}\n";
                    }
                    $out .= "{$p['promptText']}\n";
                }

                $out .= "\n## Mastery Prompts\n";
                foreach ($roleData['masteryPrompts'] as $i => $p) {
                    $out .= "\n### Prompt ".($i + 1).": {$p['title']}\n";
                    $out .= "{$p['promptText']}\n";
                }

                $out .= "\n## Deliverables\n";
                foreach ($roleData['deliverables'] as $i => $d) {
                    $out .= "\n### Deliverable ".($i + 1).": {$d['description']}\n";
                    $out .= "Initial Draft Prompt:\n";
                    $out .= '    '.($d['initialPrompt'] ?: '(auto-generated from description when blank)')."\n";
                    $out .= "Review & Refine Prompt:\n";
                    $out .= '    '.($d['refinePrompt'] ?: '(auto-generated from description when blank)')."\n";
                }
            }
        }

        return $out;
    }

    /** @return array<string,string> role key => display label, using this client's custom labels where set */
    private function roleLabels(): array
    {
        $defaults = [
            'classroom' => 'Teacher (Classroom)',
            'leader' => 'Leader (School/District)',
            'sped' => 'Special Education Professional',
            'support' => 'Student Support Professional',
            'coach' => 'Instructional Coach',
            'higher_ed' => 'Higher Education Faculty',
        ];

        $custom = PlatformSetting::current()->role_labels ?? [];

        return array_merge($defaults, array_filter($custom));
    }
}
