<?php

namespace App\Filament\Resources\ModuleResource\RelationManagers;

use App\Models\ModuleOverviewSection;
use App\Models\PracticePrompt;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OverviewSectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'overviewSections';

    protected static ?string $title = 'Overview Sections (Challenge / Concept / Matters / Strategy)';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('section')
                    ->options(array_combine(ModuleOverviewSection::SECTIONS, ModuleOverviewSection::SECTIONS))
                    ->required(),
                Forms\Components\Select::make('role')
                    ->options(array_combine(PracticePrompt::ROLES, PracticePrompt::ROLES))
                    ->required(),
                Forms\Components\Textarea::make('content')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('section')
            ->defaultSort('section')
            ->columns([
                Tables\Columns\TextColumn::make('section')->badge(),
                Tables\Columns\TextColumn::make('role')->badge(),
                Tables\Columns\TextColumn::make('content')->limit(80),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('section')
                    ->options(array_combine(ModuleOverviewSection::SECTIONS, ModuleOverviewSection::SECTIONS)),
                Tables\Filters\SelectFilter::make('role')
                    ->options(array_combine(PracticePrompt::ROLES, PracticePrompt::ROLES)),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
