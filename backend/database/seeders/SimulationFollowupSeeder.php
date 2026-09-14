<?php

namespace Database\Seeders;

use App\Models\Simulation;
use Illuminate\Database\Seeder;

class SimulationFollowupSeeder extends Seeder
{
    public function run(): void
    {
        $defaultVerification = [
            'Verify that the suggested strategies align with your specific curriculum standards and school policies.',
            'Cross-check any AI-generated examples against trusted educational resources before using them with students.',
            'Consider whether the approach is appropriate for your students\' age, skill level, and prior knowledge.',
            'Identify at least one way you would adapt or refine this output before implementing it in your classroom.',
        ];

        $defaultFollowups = [
            [
                'label'    => 'Expand or Clarify',
                'prompt'   => 'Can you expand on one of these strategies with more specific examples?',
                'response' => "Absolutely — let me go deeper on the most immediately applicable strategy.\n\nThe key is to start small and be intentional. Choose one specific lesson or unit where you'll pilot this approach. Before you begin, write down what you expect to happen. After you've tried it, compare your expectations to what actually occurred — that reflection is where the real learning happens.\n\nPractical next step: pick ONE element from the response above and implement it this week. Don't try to adopt everything at once. Once that feels natural, layer in the next piece.",
            ],
            [
                'label'    => 'Adapt to My Context',
                'prompt'   => 'How would I adapt this for my specific grade level or subject area?',
                'response' => "Great question — context matters enormously when implementing AI strategies. Here's how to think about adapting this for your situation:\n\n• Consider your students' prior experience with AI tools and adjust your scaffolding accordingly\n• Align the approach with your specific curriculum standards so AI use reinforces — not replaces — core learning goals\n• Start with content you know deeply so you can evaluate AI accuracy confidently\n• Build in structured time for students to question and verify AI outputs\n\nThe core principles remain the same across contexts, but your implementation will naturally reflect your students' needs, your subject's unique demands, and your school's culture around technology.",
            ],
            [
                'label'    => 'Alternative Approach',
                'prompt'   => "What's a different way to approach this if the suggested method doesn't fit my situation?",
                'response' => "There's rarely one right way to integrate AI into teaching — here are alternative approaches worth considering:\n\n• Low-tech entry point: Use AI-generated outputs as discussion starters or comparison pieces rather than direct tools students interact with\n• Collaborative model: Have students work in pairs to critically evaluate and improve AI-generated content together\n• Assessment-first design: Define what authentic mastery looks like before using AI, so you have a clear standard to measure against\n• Gradual release: Demonstrate AI use first (I do), then guide students through it together (we do), then let them practice independently (you do)\n\nChoose the path that matches your current comfort level. You can always evolve your approach as you gain confidence and see what resonates with your students.",
            ],
            [
                'label'    => 'Common Pitfalls',
                'prompt'   => 'What mistakes should I avoid when implementing this?',
                'response' => "Great instinct to ask — knowing what to avoid is just as important as knowing what to do. Here are the most common pitfalls educators encounter:\n\n• Over-relying on AI output without verification: AI can sound confident while being wrong. Build in a step where you or your students cross-check key claims.\n• Skipping the modeling phase: Students (and teachers) need to see AI used well before they can use it well themselves. Don't hand it off without a demonstration.\n• Treating AI as a shortcut rather than a thinking partner: The goal isn't to do less work — it's to do better work. Use AI to push your thinking further, not to replace it.\n• Ignoring equity implications: Not all students have equal access to or comfort with AI tools. Design activities that don't inadvertently disadvantage students with less exposure.\n• Forgetting to revisit and revise: AI-assisted work is a first draft, not a final product. Build revision cycles into your workflow.\n\nThe educators who get the most value from AI are those who stay critically engaged with the output rather than passively accepting it.",
            ],
        ];

        $count = 0;
        foreach (Simulation::all() as $sim) {
            $updates = [];

            if (empty($sim->verification_tips)) {
                $updates['verification_tips'] = $defaultVerification;
            }

            $opts = $sim->followup_options ?? [];
            if (empty($opts)) {
                $updates['followup_options'] = $defaultFollowups;
            } elseif (count($opts) < 4 && !in_array('Common Pitfalls', array_column($opts, 'label'))) {
                // Existing sims have 3 options; the platform spec calls for 4.
                $opts[] = $defaultFollowups[3];
                $updates['followup_options'] = $opts;
            }

            if (!empty($updates)) {
                $sim->update($updates);
                $count++;
            }
        }

        $this->command->info("Updated {$count} simulation(s) with missing verification_tips and/or followup_options.");
    }
}
