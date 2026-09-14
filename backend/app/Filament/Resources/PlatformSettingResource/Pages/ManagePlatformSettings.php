<?php

namespace App\Filament\Resources\PlatformSettingResource\Pages;

use App\Filament\Resources\PlatformSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePlatformSettings extends ManageRecords
{
    protected static string $resource = PlatformSettingResource::class;

    protected function getHeaderActions(): array
    {
        // Platform settings is a single-row config table (seeded by the
        // import command); there is intentionally no "create" action here.
        return [];
    }
}
