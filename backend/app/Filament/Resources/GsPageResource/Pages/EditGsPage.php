<?php

namespace App\Filament\Resources\GsPageResource\Pages;

use App\Filament\Resources\GsPageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGsPage extends EditRecord
{
    protected static string $resource = GsPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
