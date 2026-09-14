<?php

namespace Tests\Feature;

use App\Filament\Resources\ModuleResource\Pages\ReviewModule;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleContent;
use App\Models\PracticePrompt;
use App\Models\Simulation;
use App\Models\Strand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewModulePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_page_renders_and_saves_edits(): void
    {
        $this->actingAs(User::factory()->create());

        $strand = Strand::create(['id' => 'X', 'title' => 'Test Strand', 'color_class' => '', 'sort_order' => 0]);
        $course = Course::create(['id' => 'X1', 'strand_id' => $strand->id, 'title' => 'Test Course', 'sort_order' => 0]);
        $module = Module::create(['id' => 'X1.1', 'course_id' => $course->id, 'title' => 'Test Module', 'sort_order' => 0]);
        ModuleContent::create([
            'module_id' => $module->id,
            'ilos' => ['Original ILO'],
            'challenge' => 'Original challenge',
            'concept' => 'Original concept',
            'matters' => 'Original matters',
        ]);
        $prompt = PracticePrompt::create([
            'module_id' => $module->id, 'role' => 'classroom', 'title' => 'Original prompt title',
            'ai_tool' => 'ChatGPT', 'prompt_text' => 'Original prompt text', 'sort_order' => 0,
        ]);
        $sim = Simulation::create([
            'module_id' => $module->id, 'role' => 'classroom', 'title' => 'Original sim title',
            'prompt_text' => 'Original trigger', 'keywords' => ['a', 'b'], 'response' => 'Original response',
            'verification_tips' => ['Original tip 1'],
            'followup_options' => [['label' => 'Go deeper', 'prompt' => 'Original fu prompt', 'response' => 'Original fu response']],
            'sort_order' => 0,
        ]);

        $component = Livewire::test(ReviewModule::class, ['record' => $module->id]);
        $component->assertOk();

        // Confirm initial hydration picked up real data (proves the
        // relationship-bound Group/Repeater fields loaded correctly).
        $data = $component->get('data');
        $this->assertSame('Original challenge', $data['content']['challenge']);
        $promptKey = array_key_first($data['practicePrompts']);
        $this->assertSame('Original prompt title', $data['practicePrompts'][$promptKey]['title']);
        $simKey = array_key_first($data['simulations']);
        $this->assertSame('Original sim title', $data['simulations'][$simKey]['title']);

        // Edit: change overview text, the prompt's text, the sim's
        // response, add a verification tip, add a followup option.
        $data['content']['challenge'] = 'EDITED challenge';
        $data['practicePrompts'][$promptKey]['prompt_text'] = 'EDITED prompt text';
        $data['simulations'][$simKey]['response'] = 'EDITED response';
        $newTipKey = 'new_tip_item';
        $data['simulations'][$simKey]['verification_tips'][$newTipKey] = ['tip' => 'New tip 2'];
        $newFuKey = 'new_followup_item';
        $data['simulations'][$simKey]['followup_options'][$newFuKey] = [
            'label' => 'New followup', 'prompt' => 'New fu prompt', 'response' => 'New fu response',
        ];

        $component->set('data', $data);
        $component->call('save');
        $component->assertHasNoErrors();

        $module->refresh();
        $prompt->refresh();
        $sim->refresh();

        $this->assertSame('EDITED challenge', $module->content->challenge);
        $this->assertSame('EDITED prompt text', $prompt->prompt_text);
        $this->assertSame('EDITED response', $sim->response);
        $this->assertContains('New tip 2', $sim->verification_tips);
        $this->assertCount(2, $sim->followup_options);
        $labels = array_column($sim->followup_options, 'label');
        $this->assertContains('New followup', $labels);
    }

    public function test_review_page_can_add_and_delete_a_simulation(): void
    {
        $this->actingAs(User::factory()->create());

        $strand = Strand::create(['id' => 'Y', 'title' => 'Test Strand 2', 'color_class' => '', 'sort_order' => 0]);
        $course = Course::create(['id' => 'Y1', 'strand_id' => $strand->id, 'title' => 'Test Course 2', 'sort_order' => 0]);
        $module = Module::create(['id' => 'Y1.1', 'course_id' => $course->id, 'title' => 'Test Module 2', 'sort_order' => 0]);
        ModuleContent::create(['module_id' => $module->id]);

        $component = Livewire::test(ReviewModule::class, ['record' => $module->id]);

        $data = $component->get('data');
        $newKey = 'brand_new_sim';
        $data['simulations'][$newKey] = [
            'role' => 'leader',
            'title' => 'Brand new sim',
            'prompt_text' => 'Trigger',
            'keywords' => [],
            'response' => 'Response',
            'verification_tips' => ['tip_1' => ['tip' => 'Tip']],
            'followup_options' => [],
        ];
        $component->set('data', $data);
        $component->call('save');
        $component->assertHasNoErrors();

        $this->assertDatabaseHas('simulations', ['module_id' => $module->id, 'title' => 'Brand new sim']);
        $createdId = Simulation::where('module_id', $module->id)->where('title', 'Brand new sim')->first()->id;

        // Now delete it via the form state and re-save. After the first
        // save, Filament reloads repeater state from the relationship,
        // which re-keys every item as "record-{id}" -- the client-generated
        // key used when adding a new item ("brand_new_sim") doesn't survive
        // a round trip, so look the item up by its now-known DB id instead.
        $data = $component->get('data');
        unset($data['simulations']["record-{$createdId}"]);
        $component->set('data', $data);
        $component->call('save');
        $component->assertHasNoErrors();

        $this->assertDatabaseMissing('simulations', ['id' => $createdId]);
    }
}
