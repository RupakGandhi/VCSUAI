<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

/**
 * Enables the module-level file-upload flag for the 7 modules the client's
 * FILE_UPLOAD_MAPPING.md spec designates. Idempotent; admins can toggle
 * any module off/on afterwards in the Review page.
 */
class FileUploadModulesSeeder extends Seeder
{
    public function run(): void
    {
        $moduleIds = ['T2.1', 'T2.2', 'T3.1', 'T3.2', 'T3.4', 'L1.2', 'D1.1'];

        $updated = Module::whereIn('id', $moduleIds)->update(['requires_file_upload' => true]);

        $this->command->info("Enabled file upload on {$updated} module(s): " . implode(', ', $moduleIds));
    }
}
