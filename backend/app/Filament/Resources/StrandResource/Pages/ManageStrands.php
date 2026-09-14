<?php

namespace App\Filament\Resources\StrandResource\Pages;

use App\Filament\Resources\StrandResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageStrands extends ManageRecords
{
    protected static string $resource = StrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
