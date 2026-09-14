<?php

namespace Database\Seeders;

use App\Models\ModuleContent;
use Illuminate\Database\Seeder;

/**
 * The "Close the Loop: Facilitator Coaching Debrief" card exists in the
 * static frontend HTML, but module_contents.facilitator_html was backfilled
 * into the CMS before that card was added — and the frontend replaces the
 * whole Facilitator tab with the CMS value when present. This appends the
 * card to any facilitator_html that doesn't already have it.
 */
class FacilitatorDebriefSeeder extends Seeder
{
    public function run(): void
    {
        $block = '<div class="content-card" style="margin-top:2rem;border-top:3px solid var(--primary);padding-top:1.5rem;">'
            .'<h4 style="margin:0 0 1rem;color:var(--primary);">&#128260; Close the Loop: Facilitator Coaching Debrief</h4>'
            .'<p style="margin:0 0 1rem;font-size:0.9rem;line-height:1.6;">After participants complete this module, use these reflection prompts to guide a coaching conversation:</p>'
            .'<ul style="margin:0;padding-left:1.2rem;font-size:0.9rem;line-height:2;">'
            .'<li><strong>Connection:</strong> What connections did participants make between this module\'s strategies and their current practice?</li>'
            .'<li><strong>Challenge:</strong> What concepts or skills did participants find most challenging? How can you scaffold their next attempt?</li>'
            .'<li><strong>Commitment:</strong> What specific action will each participant commit to trying before the next session?</li>'
            .'<li><strong>Follow-up:</strong> How will you check in on their progress and provide ongoing support?</li>'
            .'</ul></div>';

        $count = 0;
        foreach (ModuleContent::whereNotNull('facilitator_html')->get() as $content) {
            if (str_contains($content->facilitator_html, 'Close the Loop')) {
                continue;
            }
            $content->update(['facilitator_html' => $content->facilitator_html.$block]);
            $count++;
        }

        $this->command->info("Appended facilitator debrief to {$count} module(s).");
    }
}
