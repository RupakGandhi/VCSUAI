<?php

namespace App\Services;

use App\Models\Course;
use App\Models\GsPage;
use App\Models\Module;
use App\Models\PlatformSetting;
use App\Models\Strand;
use Illuminate\Support\Carbon;

/**
 * Builds and (in a future pass) consumes the single canonical JSON shape
 * used for both full-content export (backup / portability package at
 * contract end) and bulk import (content refreshes). Keeping both
 * directions reading/writing the SAME shape is what makes a round-trip
 * (export, edit the file, re-import) safe.
 */
class ContentExportService
{
    public function export(): array
    {
        return [
            'exported_at' => Carbon::now()->toIso8601String(),
            'platform' => $this->platform(),
            'strands' => $this->strands(),
            'courses' => $this->courses(),
            'modules' => $this->modules(),
            'gsPages' => $this->gsPages(),
        ];
    }

    private function platform(): array
    {
        $s = PlatformSetting::current();

        return [
            'title' => $s->title,
            'subtitle' => $s->subtitle,
            'pageTitle' => $s->page_title,
            'heroTitle' => $s->hero_title,
            'heroDesc' => $s->hero_desc,
            'heroStats' => $s->hero_stats,
            'primaryColor' => $s->primary_color,
            'accentColor' => $s->accent_color,
            'password' => $s->password,
            'facPassword' => $s->fac_password,
            'footerHtml' => $s->footer_html,
            'roleLabels' => $s->role_labels,
            'aiToolName' => $s->ai_tool_name,
            'surveyQ1' => $s->survey_q1_text,
            'surveyQ2' => $s->survey_q2_text,
            'surveyQ3' => $s->survey_q3_text,
        ];
    }

    private function strands(): array
    {
        return Strand::orderBy('sort_order')->get()
            ->map(fn (Strand $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'colorClass' => $s->color_class,
                'sortOrder' => $s->sort_order,
            ])->values()->all();
    }

    private function courses(): array
    {
        return Course::orderBy('id')->get()
            ->map(fn (Course $c) => [
                'id' => $c->id,
                'strandId' => $c->strand_id,
                'title' => $c->title,
                'description' => $c->description,
                'sortOrder' => $c->sort_order,
            ])->values()->all();
    }

    private function modules(): array
    {
        return Module::with(['content', 'practicePrompts', 'masteryPrompts', 'applyDeliverables', 'simulations', 'overviewSections'])
            ->orderBy('id')->get()
            ->map(function (Module $m) {
                $content = $m->content;

                return [
                    'id' => $m->id,
                    'courseId' => $m->course_id,
                    'title' => $m->title,
                    'sortOrder' => $m->sort_order,
                    'requiresFileUpload' => (bool) $m->requires_file_upload,
                    'content' => $content ? [
                        'ilos' => $content->ilos,
                        'challenge' => $content->challenge,
                        'concept' => $content->concept,
                        'matters' => $content->matters,
                        'learnHtml' => $content->learn_html,
                        'facilitatorHtml' => $content->facilitator_html,
                    ] : null,
                    'overviewSections' => $m->overviewSections->map(fn ($s) => [
                        'section' => $s->section,
                        'role' => $s->role,
                        'content' => $s->content,
                        'sortOrder' => $s->sort_order,
                    ])->values()->all(),
                    'prompts' => $m->practicePrompts->map(fn ($p) => [
                        'role' => $p->role,
                        'title' => $p->title,
                        'promptText' => $p->prompt_text,
                        'sampleFile' => $p->sample_file,
                        'sortOrder' => $p->sort_order,
                    ])->values()->all(),
                    'masteryPrompts' => $m->masteryPrompts->map(fn ($p) => [
                        'role' => $p->role,
                        'title' => $p->title,
                        'promptText' => $p->prompt_text,
                        'sortOrder' => $p->sort_order,
                    ])->values()->all(),
                    'deliverables' => $m->applyDeliverables->map(fn ($d) => [
                        'role' => $d->role,
                        'title' => $d->title,
                        'description' => $d->description,
                        'initialPrompt' => $d->initial_prompt,
                        'refinePrompt' => $d->refine_prompt,
                        'sortOrder' => $d->sort_order,
                    ])->values()->all(),
                    'simulations' => $m->simulations->map(fn ($s) => [
                        'role' => $s->role,
                        'title' => $s->title,
                        'promptText' => $s->prompt_text,
                        'keywords' => $s->keywords,
                        'response' => $s->response,
                        'verificationTips' => $s->verification_tips,
                        'followupOptions' => $s->followup_options,
                        'biasCheckTips' => $s->bias_check_tips,
                        'expectedFilename' => $s->expected_filename,
                        'sortOrder' => $s->sort_order,
                    ])->values()->all(),
                ];
            })->values()->all();
    }

    private function gsPages(): array
    {
        return GsPage::with('blocks')->orderBy('sort_order')->get()
            ->map(fn (GsPage $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'descriptionHtml' => $p->description_html,
                'sortOrder' => $p->sort_order,
                'blocks' => $p->blocks->map(fn ($b) => [
                    'id' => $b->id,
                    'title' => $b->title,
                    'contentHtml' => $b->content_html,
                    'sortOrder' => $b->sort_order,
                ])->values()->all(),
            ])->values()->all();
    }
}
