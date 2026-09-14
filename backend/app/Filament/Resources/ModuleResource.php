<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModuleResource\Pages;
use App\Filament\Resources\ModuleResource\RelationManagers;
use App\Models\Course;
use App\Models\Module;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ModuleResource extends Resource
{
    protected static ?string $model = Module::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Curriculum Structure';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Module')
                    ->columnSpanFull()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Details')
                            ->schema([
                                Forms\Components\TextInput::make('id')
                                    ->label('ID (e.g. T1.1, D2.3)')
                                    ->required()
                                    ->maxLength(20),
                                Forms\Components\Select::make('course_id')
                                    ->label('Course')
                                    ->options(fn () => Course::pluck('title', 'id'))
                                    ->required(),
                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Overview')
                            ->schema([
                                Forms\Components\Group::make([
                                    Forms\Components\TagsInput::make('ilos')
                                        ->label('Intended Learning Outcomes')
                                        ->placeholder('Add an ILO and press Enter')
                                        ->columnSpanFull(),
                                    Forms\Components\Textarea::make('challenge')
                                        ->label('The Challenge')
                                        ->rows(4)
                                        ->columnSpanFull(),
                                    Forms\Components\Textarea::make('concept')
                                        ->label('Key Concept')
                                        ->rows(4)
                                        ->columnSpanFull(),
                                    Forms\Components\Textarea::make('matters')
                                        ->label('Why It Matters')
                                        ->rows(4)
                                        ->columnSpanFull(),
                                ])->relationship('content'),
                            ]),

                        Forms\Components\Tabs\Tab::make('Learn')
                            ->schema([
                                Forms\Components\Group::make([
                                    Forms\Components\RichEditor::make('learn_html')
                                        ->label('Learn phase content')
                                        ->columnSpanFull()
                                        ->extraInputAttributes(['style' => 'min-height: 400px']),
                                ])->relationship('content'),
                            ]),

                        Forms\Components\Tabs\Tab::make('Facilitator Guide')
                            ->schema([
                                Forms\Components\Group::make([
                                    Forms\Components\RichEditor::make('facilitator_html')
                                        ->label('Facilitator guide content')
                                        ->columnSpanFull()
                                        ->extraInputAttributes(['style' => 'min-height: 400px']),
                                ])->relationship('content'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->searchable(),
                Tables\Columns\TextColumn::make('course.strand.title')->label('Strand')->badge(),
                Tables\Columns\TextColumn::make('course.title')->label('Course')->limit(30),
                Tables\Columns\TextColumn::make('title')->searchable()->limit(50),
                Tables\Columns\TextColumn::make('practice_prompts_count')->counts('practicePrompts')->label('Prompts'),
                Tables\Columns\TextColumn::make('simulations_count')->counts('simulations')->label('Sims'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('course_id')
                    ->label('Course')
                    ->options(fn () => Course::pluck('title', 'id')),
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Module $record) => ModuleResource\Pages\ReviewModule::getUrl(['record' => $record])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OverviewSectionsRelationManager::class,
            RelationManagers\PracticePromptsRelationManager::class,
            RelationManagers\MasteryPromptsRelationManager::class,
            RelationManagers\ApplyDeliverablesRelationManager::class,
            RelationManagers\SimulationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModules::route('/'),
            'create' => Pages\CreateModule::route('/create'),
            'edit' => Pages\EditModule::route('/{record}/edit'),
            'review' => Pages\ReviewModule::route('/{record}/review'),
        ];
    }
}
