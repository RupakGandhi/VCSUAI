<?php

namespace App\Services;

use App\Models\ApplyDeliverable;
use App\Models\MasteryPrompt;
use App\Models\Module;
use App\Models\ModuleOverviewSection;
use App\Models\PracticePrompt;
use Illuminate\Support\Facades\DB;

/**
 * Parses the plain-text format produced by ContentReviewExportService back
 * into structured data, computes a diff against the currently-active
 * client's database, and can apply that diff. Three separate steps on
 * purpose -- parse() never touches the database, diff() is read-only, and
 * only apply() writes -- so the admin UI can show an accurate preview
 * before anything changes.
 *
 * Parsing is a line-by-line state machine, not a blank-line split: a
 * challenge/prompt/description field is whatever text follows its marker
 * line up to the next recognized marker, blank lines included, since
 * pasted content could legitimately contain blank lines.
 */
class ContentReviewImportService
{
    public const ROLES = ['classroom', 'leader', 'sped', 'support', 'coach', 'higher_ed'];

    private const MODULE_RE = '/^=== MODULE: (\S+): (.*) ===$/';
    private const ROLE_LINE_RE = '/^--- ROLE: (.*) ---$/';
    private const ROLE_KEY_RE = '/\[(\w+)\]\s*$/';
    private const SECTION_RE = '/^## (Challenge|Practice Prompts|Mastery Prompts|Deliverables)$/';
    private const PROMPT_ITEM_RE = '/^### Prompt (\d+): (.*)$/';
    private const DELIVERABLE_ITEM_RE = '/^### Deliverable (\d+): (.*)$/';
    private const AI_TOOL_RE = '/^AI Tool: (.*)$/';
    private const INITIAL_PROMPT_MARKER = 'Initial Draft Prompt:';
    private const REFINE_PROMPT_MARKER = 'Review & Refine Prompt:';

    /**
     * @return array{modules: array<string, array{title: string, roles: array<string, array{
     *   challenge: ?string,
     *   practicePrompts: list<array{title:string, aiTool:?string, promptText:string}>,
     *   masteryPrompts: list<array{title:string, promptText:string}>,
     *   deliverables: list<array{description:string, initialPrompt:?string, refinePrompt:?string}>,
     * }>}>, warnings: list<string>}
     */
    public function parse(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);

        $modules = [];
        $warnings = [];
        $moduleId = null;
        $role = null;
        $section = null; // 'challenge' | 'practicePrompts' | 'masteryPrompts' | 'deliverables'
        $lineNo = 0;
        $buffer = [];
        $pendingPromptTitle = null;
        $pendingAiTool = null;
        $pendingInitial = null;
        $pendingRefine = null;
        $capturingRefine = false;

        $flushChallenge = function () use (&$modules, &$moduleId, &$role, &$buffer) {
            if ($moduleId === null || $role === null) {
                return;
            }
            $text = trim(implode("\n", $buffer));
            $modules[$moduleId]['roles'][$role]['challenge'] = $text === '(none)' ? null : $text;
            $buffer = [];
        };

        $flushPrompt = function (string $kind) use (&$modules, &$moduleId, &$role, &$buffer, &$pendingPromptTitle, &$pendingAiTool) {
            if ($moduleId === null || $role === null || $pendingPromptTitle === null) {
                return;
            }
            $entry = ['title' => $pendingPromptTitle, 'promptText' => trim(implode("\n", $buffer))];
            if ($kind === 'practicePrompts') {
                $entry['aiTool'] = $pendingAiTool;
            }
            $modules[$moduleId]['roles'][$role][$kind][] = $entry;
            $buffer = [];
            $pendingPromptTitle = null;
            $pendingAiTool = null;
        };

        $flushDeliverable = function () use (&$modules, &$moduleId, &$role, &$pendingPromptTitle, &$pendingInitial, &$pendingRefine) {
            if ($moduleId === null || $role === null || $pendingPromptTitle === null) {
                return;
            }
            $modules[$moduleId]['roles'][$role]['deliverables'][] = [
                'description' => $pendingPromptTitle,
                'initialPrompt' => $this->normalizeAutoGenPlaceholder($pendingInitial),
                'refinePrompt' => $this->normalizeAutoGenPlaceholder($pendingRefine),
            ];
            $pendingPromptTitle = null;
            $pendingInitial = null;
            $pendingRefine = null;
        };

        foreach ($lines as $line) {
            $lineNo++;

            if (preg_match(self::MODULE_RE, $line, $m)) {
                if ($section === 'challenge') {
                    $flushChallenge();
                } elseif ($section === 'practicePrompts' || $section === 'masteryPrompts') {
                    $flushPrompt($section);
                } elseif ($section === 'deliverables') {
                    $flushDeliverable();
                }
                $moduleId = $m[1];
                $modules[$moduleId] = ['title' => $m[2], 'roles' => []];
                $role = null;
                $section = null;

                continue;
            }

            if (preg_match(self::ROLE_LINE_RE, $line, $m)) {
                if ($section === 'challenge') {
                    $flushChallenge();
                } elseif ($section === 'practicePrompts' || $section === 'masteryPrompts') {
                    $flushPrompt($section);
                } elseif ($section === 'deliverables') {
                    $flushDeliverable();
                }

                if ($moduleId === null) {
                    $warnings[] = "Line {$lineNo}: a ROLE marker appears before any MODULE marker -- \"{$line}\".";
                    $role = null;
                    $section = null;

                    continue;
                }

                if (! preg_match(self::ROLE_KEY_RE, $m[1], $km) || ! in_array($km[1], self::ROLES, true)) {
                    $warnings[] = "Line {$lineNo}, module {$moduleId}: role line is missing a valid \"[role_key]\" tag (e.g. \"[classroom]\") -- \"{$line}\". This role's content will be ignored.";
                    $role = null;
                    $section = null;

                    continue;
                }

                $role = $km[1];
                $modules[$moduleId]['roles'][$role] = [
                    'challenge' => null, 'practicePrompts' => [], 'masteryPrompts' => [], 'deliverables' => [],
                ];
                $section = null;

                continue;
            }

            if (preg_match(self::SECTION_RE, $line, $m)) {
                if ($section === 'challenge') {
                    $flushChallenge();
                } elseif ($section === 'practicePrompts' || $section === 'masteryPrompts') {
                    $flushPrompt($section);
                } elseif ($section === 'deliverables') {
                    $flushDeliverable();
                }
                $section = match ($m[1]) {
                    'Challenge' => 'challenge',
                    'Practice Prompts' => 'practicePrompts',
                    'Mastery Prompts' => 'masteryPrompts',
                    'Deliverables' => 'deliverables',
                };
                $buffer = [];

                continue;
            }

            if (($section === 'practicePrompts' || $section === 'masteryPrompts') && preg_match(self::PROMPT_ITEM_RE, $line, $m)) {
                $flushPrompt($section);
                $pendingPromptTitle = $m[2];

                continue;
            }

            if ($section === 'deliverables' && preg_match(self::DELIVERABLE_ITEM_RE, $line, $m)) {
                $flushDeliverable();
                $pendingPromptTitle = $m[2];
                $capturingRefine = false;

                continue;
            }

            if ($section === 'practicePrompts' && preg_match(self::AI_TOOL_RE, $line, $m) && empty($buffer)) {
                $pendingAiTool = $m[1];

                continue;
            }

            if ($section === 'deliverables' && trim($line) === self::INITIAL_PROMPT_MARKER) {
                $capturingRefine = false;

                continue;
            }

            if ($section === 'deliverables' && trim($line) === self::REFINE_PROMPT_MARKER) {
                $capturingRefine = true;

                continue;
            }

            if ($section === 'deliverables' && $pendingPromptTitle !== null) {
                $content = preg_replace('/^ {4}/', '', $line);
                if ($capturingRefine) {
                    $pendingRefine = ltrim(($pendingRefine ?? '').$content."\n");
                } else {
                    $pendingInitial = ltrim(($pendingInitial ?? '').$content."\n");
                }

                continue;
            }

            if ($section !== null) {
                $buffer[] = $line;

                continue;
            }

            // A non-blank line with no active section, once we're past the
            // file's own preamble (before the first MODULE marker, where the
            // title/instructions legitimately live with no section at all):
            // this is content the parser can't place anywhere, e.g. text
            // between a MODULE/ROLE header and the next "## " marker.
            if ($moduleId !== null && trim($line) !== '') {
                $warnings[] = "Line {$lineNo}: unrecognized text outside any section, near module {$moduleId}".($role ? "/{$role}" : '').": \"{$line}\".";
            }
        }

        // Flush whatever was still open at end of file
        if ($section === 'challenge') {
            $flushChallenge();
        } elseif ($section === 'practicePrompts' || $section === 'masteryPrompts') {
            $flushPrompt($section);
        } elseif ($section === 'deliverables') {
            $flushDeliverable();
        }

        return ['modules' => $modules, 'warnings' => $warnings];
    }

    private function normalizeAutoGenPlaceholder(?string $text): ?string
    {
        $trimmed = $text === null ? null : trim($text);

        return ($trimmed === '' || $trimmed === '(auto-generated from description when blank)') ? null : $trimmed;
    }

    /**
     * Cross-checks parsed modules against the currently-active client's
     * database for the single most likely accidental-mass-deletion shape an
     * AI-assisted "mass content" edit could produce: a role whose section
     * headers are still present (so it parses fine) but whose
     * prompts/deliverables all got cleared out, when that role actually had
     * content before. A plain diff would show this as a pile of ordinary
     * "deleted" entries mixed in with everything else -- easy to miss in a
     * large file. This is called out as a blocking error instead, separate
     * from parse()'s own structural warnings, since it requires comparing
     * against the database rather than just the file's own structure.
     *
     * Deliberately does not flag Challenge (a single field going blank is
     * already an obvious, single-line diff entry, not a silent bulk loss)
     * or modules/roles entirely absent from the file (those are left
     * untouched by apply(), not deleted -- see the class-level notes on
     * diff()/apply() below).
     *
     * @return list<string> human-readable blocking errors; empty means clear to proceed
     */
    public function validateAgainstDatabase(array $modules): array
    {
        $errors = [];
        $existingModuleIds = Module::pluck('id')->all();

        $dbModules = Module::whereIn('id', array_keys($modules))
            ->with(['practicePrompts', 'masteryPrompts', 'applyDeliverables'])
            ->get()->keyBy('id');

        foreach ($modules as $moduleId => $moduleData) {
            if (! in_array($moduleId, $existingModuleIds, true)) {
                continue; // reported separately as "skipped", not a validation error
            }
            $module = $dbModules[$moduleId];

            foreach ($moduleData['roles'] as $role => $roleData) {
                $dbCount = $module->practicePrompts->where('role', $role)->count()
                    + $module->masteryPrompts->where('role', $role)->count()
                    + $module->applyDeliverables->where('role', $role)->count();
                $newCount = count($roleData['practicePrompts']) + count($roleData['masteryPrompts']) + count($roleData['deliverables']);

                if ($dbCount > 0 && $newCount === 0) {
                    $errors[] = "Module \"{$moduleId}\", role \"{$role}\": had {$dbCount} prompt(s)/deliverable(s) before, but the uploaded file has 0 for this role. If this is intentional, this check can't tell the difference from an accidental drop -- please confirm by leaving at least a placeholder entry, or ask for this check to be bypassed for this specific case.";
                }
            }
        }

        return $errors;
    }

    /**
     * Compares parsed data against the currently-active client's database.
     * Read-only. Returns every module/role/item difference (never just a
     * count) so the admin UI can show exactly what changed before anyone
     * commits to it -- 'unchanged' entries are included too, so the total
     * item count is always visible, but the UI is expected to collapse
     * those by default.
     *
     * @return array{modulesSkipped: string[], changes: list<array>, summary: array<string,int>}
     */
    public function diff(array $parsed): array
    {
        $existingModuleIds = Module::pluck('id')->all();
        $changes = [];
        $modulesSkipped = [];
        $summary = ['unchanged' => 0, 'updated' => 0, 'created' => 0, 'deleted' => 0];

        $modules = Module::whereIn('id', array_keys($parsed))
            ->with(['practicePrompts', 'masteryPrompts', 'applyDeliverables', 'overviewSections'])
            ->get()->keyBy('id');

        foreach ($parsed as $moduleId => $moduleData) {
            if (! in_array($moduleId, $existingModuleIds, true)) {
                $modulesSkipped[] = $moduleId;

                continue;
            }
            $module = $modules[$moduleId];

            foreach ($moduleData['roles'] as $role => $roleData) {
                $dbChallengeRow = $module->overviewSections->where('section', 'challenge')->where('role', $role)->first();
                $dbChallenge = $dbChallengeRow?->content;
                if ($dbChallenge !== $roleData['challenge']) {
                    $changes[] = $this->change($moduleId, $role, 'challenge', null, 'updated', $dbChallenge, $roleData['challenge']);
                    $summary['updated']++;
                } else {
                    $summary['unchanged']++;
                }

                $this->diffItems(
                    $module->practicePrompts->where('role', $role)->sortBy('sort_order')->values(),
                    $roleData['practicePrompts'],
                    fn ($p) => ['title' => $p->title, 'promptText' => $p->prompt_text, 'aiTool' => $p->ai_tool ?: null],
                    $moduleId, $role, 'practicePrompt', $changes, $summary
                );

                $this->diffItems(
                    $module->masteryPrompts->where('role', $role)->sortBy('sort_order')->values(),
                    $roleData['masteryPrompts'],
                    fn ($p) => ['title' => $p->title, 'promptText' => $p->prompt_text],
                    $moduleId, $role, 'masteryPrompt', $changes, $summary
                );

                $this->diffItems(
                    $module->applyDeliverables->where('role', $role)->sortBy('sort_order')->values(),
                    $roleData['deliverables'],
                    fn ($d) => ['description' => $d->description, 'initialPrompt' => $d->initial_prompt ?: null, 'refinePrompt' => $d->refine_prompt ?: null],
                    $moduleId, $role, 'deliverable', $changes, $summary
                );
            }
        }

        return ['modulesSkipped' => $modulesSkipped, 'changes' => $changes, 'summary' => $summary];
    }

    private function diffItems($dbItems, array $parsedItems, callable $normalize, string $moduleId, string $role, string $type, array &$changes, array &$summary): void
    {
        $max = max($dbItems->count(), count($parsedItems));
        for ($i = 0; $i < $max; $i++) {
            $old = $dbItems->get($i) ? $normalize($dbItems->get($i)) : null;
            $new = $parsedItems[$i] ?? null;

            if ($old !== null && $new !== null) {
                if ($old !== $new) {
                    $changes[] = $this->change($moduleId, $role, $type, $i, 'updated', $old, $new);
                    $summary['updated']++;
                } else {
                    $summary['unchanged']++;
                }
            } elseif ($old === null && $new !== null) {
                $changes[] = $this->change($moduleId, $role, $type, $i, 'created', null, $new);
                $summary['created']++;
            } elseif ($old !== null && $new === null) {
                $changes[] = $this->change($moduleId, $role, $type, $i, 'deleted', $old, null);
                $summary['deleted']++;
            }
        }
    }

    private function change(string $moduleId, string $role, string $type, ?int $index, string $action, mixed $old, mixed $new): array
    {
        return compact('moduleId', 'role', 'type', 'index', 'action', 'old', 'new');
    }

    /**
     * Applies parsed data to the currently-active client's database. The
     * caller (Filament action) is responsible for ActiveClient::apply()
     * beforehand, same convention as ContentSyncService. Deletions found by
     * diff() ARE applied -- the admin UI must show the diff and get
     * confirmation first, since removing a role's last remaining item this
     * way is a real, intentional edit, not a safety net.
     *
     * @return array{modulesSkipped: string[], updated: int, created: int, deleted: int}
     */
    public function apply(array $parsed): array
    {
        $existingModuleIds = Module::pluck('id')->all();
        $modulesSkipped = [];
        $counts = ['updated' => 0, 'created' => 0, 'deleted' => 0];

        DB::connection('content')->transaction(function () use ($parsed, $existingModuleIds, &$modulesSkipped, &$counts) {
            foreach ($parsed as $moduleId => $moduleData) {
                if (! in_array($moduleId, $existingModuleIds, true)) {
                    $modulesSkipped[] = $moduleId;

                    continue;
                }

                foreach ($moduleData['roles'] as $role => $roleData) {
                    $existingChallenge = ModuleOverviewSection::where(['module_id' => $moduleId, 'section' => 'challenge', 'role' => $role])->first();
                    if ($roleData['challenge'] !== null) {
                        if (! $existingChallenge) {
                            ModuleOverviewSection::create(['module_id' => $moduleId, 'section' => 'challenge', 'role' => $role, 'sort_order' => 0, 'content' => $roleData['challenge']]);
                        } elseif ($existingChallenge->content !== $roleData['challenge']) {
                            $existingChallenge->update(['content' => $roleData['challenge']]);
                        }
                    } elseif ($existingChallenge) {
                        $existingChallenge->delete();
                    }

                    $counts = $this->applyItems(
                        PracticePrompt::where('module_id', $moduleId)->where('role', $role)->orderBy('sort_order')->get(),
                        $roleData['practicePrompts'],
                        fn (array $item, int $sortOrder) => [
                            'module_id' => $moduleId, 'role' => $role, 'sort_order' => $sortOrder,
                            'title' => $item['title'], 'prompt_text' => $item['promptText'], 'ai_tool' => $item['aiTool'],
                        ],
                        PracticePrompt::class, $counts
                    );

                    $counts = $this->applyItems(
                        MasteryPrompt::where('module_id', $moduleId)->where('role', $role)->orderBy('sort_order')->get(),
                        $roleData['masteryPrompts'],
                        fn (array $item, int $sortOrder) => [
                            'module_id' => $moduleId, 'role' => $role, 'sort_order' => $sortOrder,
                            'title' => $item['title'], 'prompt_text' => $item['promptText'],
                        ],
                        MasteryPrompt::class, $counts
                    );

                    $counts = $this->applyItems(
                        ApplyDeliverable::where('module_id', $moduleId)->where('role', $role)->orderBy('sort_order')->get(),
                        $roleData['deliverables'],
                        fn (array $item, int $sortOrder) => [
                            'module_id' => $moduleId, 'role' => $role, 'sort_order' => $sortOrder,
                            'title' => 'Deliverable '.($sortOrder + 1), 'description' => $item['description'],
                            'initial_prompt' => $item['initialPrompt'], 'refine_prompt' => $item['refinePrompt'],
                        ],
                        ApplyDeliverable::class, $counts
                    );
                }
            }
        });

        return array_merge(['modulesSkipped' => $modulesSkipped], $counts);
    }

    private function applyItems($dbItems, array $parsedItems, callable $attributesFor, string $modelClass, array $counts): array
    {
        $max = max($dbItems->count(), count($parsedItems));
        for ($i = 0; $i < $max; $i++) {
            $existing = $dbItems->get($i);
            $new = $parsedItems[$i] ?? null;

            if ($existing && $new) {
                $attrs = $attributesFor($new, $i);
                $existing->fill($attrs);
                if ($existing->isDirty()) {
                    $existing->save();
                    $counts['updated']++;
                }
            } elseif (! $existing && $new) {
                $modelClass::create($attributesFor($new, $i));
                $counts['created']++;
            } elseif ($existing && ! $new) {
                $existing->delete();
                $counts['deleted']++;
            }
        }

        return $counts;
    }
}
