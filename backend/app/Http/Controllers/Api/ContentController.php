<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\GsPage;
use App\Models\Module;
use App\Models\PlatformSetting;
use App\Models\Simulation;
use App\Models\Strand;

/**
 * Public, read-only content bundle for the front end's backend integration
 * (see index.html's BACKEND INTEGRATION script). Covers every content type
 * editable in the admin -- structure, module content, prompts, deliverables,
 * simulations, and platform settings -- so an edit in /admin is reflected
 * on the live front end on next page load, per the contract requirement
 * that edits "automatically reflected on the live frontend without manual
 * file editing or redeployment."
 *
 * Deliberately uncached, at every layer: every request recomputes this
 * straight from the database and is sent with Cache-Control: no-store.
 * This app's traffic is modest enough that the cost of recomputing this
 * payload per-request is negligible, and skipping caching entirely
 * removes a whole class of "why isn't my edit showing up" bugs that
 * repeatedly came from stale data at the Laravel-cache, browser, or CDN
 * layer.
 */
class ContentController extends Controller
{
    public function show()
    {
        $payload = [
            'version' => $this->computeVersion(),
            'platform' => $this->platformPayload(),
            'strands' => $this->strandsPayload(),
            'courses' => $this->coursesPayload(),
            'modules' => $this->modulesPayload(),
            'simData' => $this->simDataPayload(),
            'gsPages' => $this->gsPagesPayload(),
        ];

        return response()->json($payload)
            ->header('Cache-Control', 'no-store');
    }

    private function computeVersion(): int
    {
        $latest = collect([
            PlatformSetting::max('updated_at'),
            Module::max('updated_at'),
            Course::max('updated_at'),
            Strand::max('updated_at'),
        ])->filter()->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->timestamp)->max();

        return $latest ?? 0;
    }

    private function platformPayload(): array
    {
        $settings = PlatformSetting::current();

        return [
            'title' => $settings->title,
            'subtitle' => $settings->subtitle,
            'pageTitle' => $settings->page_title,
            'heroTitle' => $settings->hero_title,
            'heroDesc' => $settings->hero_desc,
            'heroStats' => $settings->hero_stats,
            'primaryColor' => $settings->primary_color,
            'accentColor' => $settings->accent_color,
            'survey' => [
                'q1' => $settings->survey_q1_text,
                'q2' => $settings->survey_q2_text,
                'q3' => $settings->survey_q3_text,
            ],
            'footerHtml' => $settings->footer_html,
            'roleLabels' => $settings->role_labels,
            'aiToolName' => $settings->ai_tool_name,
            // Null when the client hasn't uploaded a custom icon — the
            // frontend/StaticSiteBuilder fall back to the shared default
            // icons in that case. Served by /files/platform-icons/ — see
            // routes/web.php for why this isn't a /storage/ symlink URL.
            'icon192Url' => $settings->icon_192 ? url('/files/platform-icons/'.basename($settings->icon_192)) : null,
            'icon512Url' => $settings->icon_512 ? url('/files/platform-icons/'.basename($settings->icon_512)) : null,
            'icon512MaskableUrl' => $settings->icon_512_maskable ? url('/files/platform-icons/'.basename($settings->icon_512_maskable)) : null,
            // The access gate is a purely client-side splash (the code is
            // embedded in every deployed page's source already), so sending
            // the per-client codes here adds no exposure — and without them
            // the gate can only ever check the code baked into the template.
            'password' => $settings->password,
            'facPassword' => $settings->fac_password,
        ];
    }

    private function strandsPayload(): array
    {
        return Strand::orderBy('sort_order')->get()
            ->map(fn (Strand $s) => ['id' => $s->id, 'title' => $s->title, 'colorClass' => $s->color_class])
            ->values()->all();
    }

    private function coursesPayload(): array
    {
        return Course::orderBy('id')->get()
            ->mapWithKeys(fn (Course $c) => [$c->id => [
                'strandId' => $c->strand_id,
                'title' => $c->title,
                'description' => $c->description,
            ]])
            ->all();
    }

    private function modulesPayload(): array
    {
        return Module::with(['content', 'practicePrompts', 'masteryPrompts', 'applyDeliverables', 'overviewSections'])
            ->orderBy('id')->get()
            ->mapWithKeys(function (Module $m) {
                $content = $m->content;

                return [$m->id => [
                    'title' => $m->title,
                    'courseId' => $m->course_id,
                    'requiresFileUpload' => (bool) $m->requires_file_upload,
                    'content' => $content ? [
                        'ilos' => $content->ilos,
                        'challenge' => $content->challenge,
                        'concept' => $content->concept,
                        'matters' => $content->matters,
                        'learnHtml' => $content->learn_html,
                        'facilitatorHtml' => $content->facilitator_html,
                    ] : null,
                    'overviewSections' => $m->overviewSections->isNotEmpty()
                        ? $m->overviewSections
                            ->groupBy('section')
                            ->map(fn ($bySec) => $bySec
                                ->groupBy('role')
                                ->map(fn ($items) => $items->map(fn ($i) => $i->content)->values()))
                        : null,
                    'prompts' => $m->practicePrompts->groupBy('role')->map(
                        fn ($prompts) => $prompts->map(fn ($p) => [
                            'title' => $p->title,
                            'promptText' => $p->prompt_text,
                            // Served by the /files/ route — see routes/web.php
                            // for why this is not a /storage/ symlink URL.
                            'sampleFileUrl' => $p->sample_file
                                ? url('/files/'.$p->sample_file)
                                : null,
                        ])->values()
                    ),
                    'masteryPrompts' => $m->masteryPrompts->groupBy('role')->map(
                        fn ($prompts) => $prompts->map(fn ($p) => [
                            'title' => $p->title,
                            'promptText' => $p->prompt_text,
                        ])->values()
                    ),
                    'deliverables' => $m->applyDeliverables->groupBy('role')->map(
                        fn ($deliverables) => $deliverables->map(fn ($d) => [
                            'title' => $d->title,
                            'description' => $d->description,
                            'initialPrompt' => $d->initial_prompt,
                            'refinePrompt' => $d->refine_prompt,
                        ])->values()
                    ),
                ]];
            })
            ->all();
    }

    private function gsPagesPayload(): array
    {
        // Ordered list; the frontend maps index 0..4 onto ml-page-1..5.
        return GsPage::with('blocks')->orderBy('sort_order')->get()
            ->map(fn (GsPage $p) => [
                'title' => $p->title,
                'descriptionHtml' => $p->description_html,
                'blocks' => $p->blocks->sortBy('sort_order')->values()->map(fn ($b) => [
                    'title' => $b->title,
                    'contentHtml' => $b->content_html,
                ]),
            ])->values()->all();
    }

    private function simDataPayload(): array
    {
        $simData = [];
        Simulation::orderBy('module_id')->orderBy('role')->orderBy('sort_order')
            ->get()
            ->groupBy('module_id')
            ->each(function ($bySimModule, $moduleId) use (&$simData) {
                $simData[$moduleId] = $bySimModule
                    ->groupBy('role')
                    ->map(fn ($sims) => $sims->map(fn (Simulation $sim) => [
                        'title' => $sim->title,
                        'prompt_text' => $sim->prompt_text,
                        'keywords' => $sim->keywords,
                        'response' => $sim->response,
                        'verification_tips' => $sim->verification_tips,
                        'followup_options' => $sim->followup_options,
                        'bias_check_tips' => $sim->bias_check_tips,
                        'requires_file_upload' => (bool) $sim->requires_file_upload,
                        // Lets the front end verify an uploaded file actually
                        // matches the sample this prepared walkthrough is
                        // keyed to (D05), rather than showing that response
                        // for any file selected. Column already existed but
                        // was never sent to the front end.
                        'expected_filename' => $sim->expected_filename,
                    ])->values())
                    ->toArray();
            });

        return $simData;
    }
}
