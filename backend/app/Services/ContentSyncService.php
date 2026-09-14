<?php

namespace App\Services;

use App\Models\ApplyDeliverable;
use App\Models\Module;
use App\Models\ModuleOverviewSection;
use Illuminate\Support\Facades\DB;

/**
 * Applies the 2026-07 content revision (per-role Challenges + bespoke
 * deliverable Step-by-Step Guide prompts) to whichever database the
 * 'content' connection currently points at -- the caller is responsible for
 * calling ActiveClient::apply($client) first, exactly like the Client
 * "Run migrations" action does.
 *
 * Every client may have a different subset of the 31 canonical modules, so
 * this is deliberately tolerant: any module id in the revision data that
 * doesn't exist for the active client is skipped and reported, never an
 * error. Deliverables are matched by module_id + role + sort_order (==
 * "Deliverable N" - 1); the revision's description is authoritative and is
 * written on both existing and newly-created rows -- some modules' stored
 * descriptions were themselves misaligned/wrong (carried over from an
 * earlier content mixup), and the revision corrects them, not just the
 * Step-by-Step Guide prompts.
 */
class ContentSyncService
{
    /** @return array{modulesSkipped: string[], challengeRowsWritten: int, deliverablesUpdated: int, deliverablesCreated: int} */
    public function run(): array
    {
        $challenges = json_decode(file_get_contents(storage_path('app/content-sync/challenges.json')), true);
        $deliverables = json_decode(file_get_contents(storage_path('app/content-sync/deliverables.json')), true);

        $summary = [
            'modulesSkipped' => [],
            'challengeRowsWritten' => 0,
            'deliverablesUpdated' => 0,
            'deliverablesCreated' => 0,
        ];

        $existingModuleIds = Module::pluck('id')->all();
        $allModuleIds = array_unique(array_merge(array_keys($challenges), array_keys($deliverables)));

        DB::connection('content')->transaction(function () use ($challenges, $deliverables, $existingModuleIds, $allModuleIds, &$summary) {
            foreach ($allModuleIds as $moduleId) {
                if (! in_array($moduleId, $existingModuleIds, true)) {
                    $summary['modulesSkipped'][] = $moduleId;

                    continue;
                }

                if (isset($challenges[$moduleId])) {
                    $summary['challengeRowsWritten'] += $this->syncChallenges($moduleId, $challenges[$moduleId]['roles']);
                }

                if (isset($deliverables[$moduleId])) {
                    [$updated, $created] = $this->syncDeliverables($moduleId, $deliverables[$moduleId]['roles']);
                    $summary['deliverablesUpdated'] += $updated;
                    $summary['deliverablesCreated'] += $created;
                }
            }
        });

        return $summary;
    }

    /** @param array<string, string> $roles role => challenge paragraph */
    private function syncChallenges(string $moduleId, array $roles): int
    {
        $written = 0;

        foreach ($roles as $role => $text) {
            ModuleOverviewSection::updateOrCreate(
                ['module_id' => $moduleId, 'section' => 'challenge', 'role' => $role, 'sort_order' => 0],
                ['content' => $text]
            );
            $written++;
        }

        return $written;
    }

    /** @param array<string, array<int, array{num:int,description:string,initialPrompt:string,refinePrompt:string}>> $roles
     *  @return array{0: int, 1: int} [updated count, created count]
     */
    private function syncDeliverables(string $moduleId, array $roles): array
    {
        $updated = 0;
        $created = 0;

        foreach ($roles as $role => $items) {
            foreach ($items as $item) {
                $sortOrder = $item['num'] - 1;
                $existing = ApplyDeliverable::where('module_id', $moduleId)
                    ->where('role', $role)
                    ->where('sort_order', $sortOrder)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'description' => $item['description'],
                        'initial_prompt' => $item['initialPrompt'],
                        'refine_prompt' => $item['refinePrompt'],
                    ]);
                    $updated++;
                } else {
                    ApplyDeliverable::create([
                        'module_id' => $moduleId,
                        'role' => $role,
                        'title' => 'Deliverable '.$item['num'],
                        'description' => $item['description'],
                        'initial_prompt' => $item['initialPrompt'],
                        'refine_prompt' => $item['refinePrompt'],
                        'sort_order' => $sortOrder,
                    ]);
                    $created++;
                }
            }
        }

        return [$updated, $created];
    }
}
