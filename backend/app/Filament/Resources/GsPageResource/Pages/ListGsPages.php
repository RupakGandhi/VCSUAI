<?php

namespace App\Filament\Resources\GsPageResource\Pages;

use App\Filament\Resources\GsPageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGsPages extends ListRecords
{
    protected static string $resource = GsPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
