<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Module;
use App\Models\Strand;
use Illuminate\Database\Seeder;

/**
 * Creates the Foundations strand skeleton (strand F, course F1, modules
 * F1.1–F1.7) so the CMS can hold the Foundations content when the client
 * delivers it as a JSON import. Placeholder titles only — real content
 * arrives via the import. firstOrCreate keeps this safe to re-run and
 * never overwrites titles the admin has already edited.
 */
class FoundationsStructureSeeder extends Seeder
{
    public function run(): void
    {
        Strand::firstOrCreate(
            ['id' => 'F'],
            ['title' => 'Foundations of AI Literacy', 'color_class' => 'foundations', 'sort_order' => 0]
        );

        Course::firstOrCreate(
            ['id' => 'F1'],
            [
                'strand_id' => 'F',
                'title' => 'Foundations of AI Literacy',
                'description' => 'Prerequisite onboarding pathway covering core AI concepts, responsible use, and foundational skills.',
                'sort_order' => 0,
            ]
        );

        $placeholders = [
            'F1.1' => 'Foundations Module 1',
            'F1.2' => 'Foundations Module 2',
            'F1.3' => 'Foundations Module 3',
            'F1.4' => 'Foundations Module 4',
            'F1.5' => 'Foundations Module 5',
            'F1.6' => 'Foundations Module 6',
            'F1.7' => 'Foundations Module 7',
        ];

        $sort = 0;
        foreach ($placeholders as $id => $title) {
            Module::firstOrCreate(
                ['id' => $id],
                ['course_id' => 'F1', 'title' => $title, 'sort_order' => $sort++]
            );
        }

        $this->command->info('Foundations structure ready: strand F, course F1, modules F1.1-F1.7.');
    }
}
