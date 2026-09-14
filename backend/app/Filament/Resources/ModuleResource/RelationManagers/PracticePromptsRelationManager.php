<?php

namespace App\Filament\Resources\ModuleResource\RelationManagers;

use App\Models\PracticePrompt;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PracticePromptsRelationManager extends RelationManager
{
    protected static string $relationship = 'practicePrompts';

    protected static ?string $title = 'Practice Prompts';

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
                Forms\Components\Textarea::make('prompt_text')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('sample_file')
                    ->label('Sample file (optional — shown as a download link with this prompt)')
                    ->disk('public')
                    ->directory('prompt-samples')
                    ->downloadable()
                    ->maxSize(10240)
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
                Tables\Columns\TextColumn::make('prompt_text')->limit(60),
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
