<?php

namespace App\Filament\Resources\ModuleResource\RelationManagers;

use App\Models\PracticePrompt;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ApplyDeliverablesRelationManager extends RelationManager
{
    protected static string $relationship = 'applyDeliverables';

    protected static ?string $title = 'Apply Deliverables';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('role')
                    ->options(array_combine(PracticePrompt::ROLES, PracticePrompt::ROLES))
                    ->required(),
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('initial_prompt')
                    ->label('Initial Draft Prompt (optional)')
                    ->helperText('Bespoke text for step 2 of the Step-by-Step Guide. Leave blank to auto-generate from role + description as before.')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('refine_prompt')
                    ->label('Review & Refine Prompt (optional)')
                    ->helperText('Bespoke text for step 3 of the Step-by-Step Guide. Leave blank to auto-generate from role + description as before.')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('role')
            ->columns([
                Tables\Columns\TextColumn::make('role')->badge(),
                Tables\Columns\TextColumn::make('title')->limit(40),
                Tables\Columns\TextColumn::make('description')->limit(60),
            ])
            ->filters([
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
