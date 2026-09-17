<?php

namespace App\Filament\Widgets;

use App\Models\Module;
use App\Models\SurveyResponse;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Individual survey rows (unlike SurveyModuleTable's per-module averages,
 * or SurveyTextResponsesTable which only lists rows with open-text
 * feedback) so a specific row -- e.g. a QA/retest submission -- can be
 * found and flagged out of evaluation without deleting it, which
 * previously required "Clear responses"/"Clear Survey Data" and took
 * every other real response down with it.
 */
class SurveyAllResponsesTable extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return request()->routeIs('filament.admin.pages.survey-results', '*livewire.update', '*livewire.upload-file');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('All Survey Responses')
            ->query(SurveyResponse::query()->latest('created_at'))
            ->columns([
                Tables\Columns\TextColumn::make('module_id')->label('Module')->sortable(),
                Tables\Columns\TextColumn::make('role')->badge()->default('—'),
                Tables\Columns\TextColumn::make('q1_score')->label('Q1'),
                Tables\Columns\TextColumn::make('q2_score')->label('Q2'),
                Tables\Columns\TextColumn::make('q3_text')->label('Q3')->limit(60)->default('—'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\IconColumn::make('is_test')
                    ->label('Excluded (test data)')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('module_id')
                    ->label('Module')
                    ->options(fn () => Module::pluck('title', 'id')),
                Tables\Filters\TernaryFilter::make('is_test')
                    ->label('Test data'),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_test')
                    ->label('Exclude from evaluation')
                    ->icon('heroicon-o-eye-slash')
                    ->visible(fn (SurveyResponse $record) => ! $record->is_test)
                    ->requiresConfirmation()
                    ->modalDescription('Flags this one response as test/non-participant data so it\'s excluded from the stats and averages above. It is not deleted and can be un-flagged later.')
                    ->action(function (SurveyResponse $record) {
                        $record->update(['is_test' => true]);
                        Notification::make()->title('Response excluded from evaluation')->success()->send();
                    }),
                Tables\Actions\Action::make('unmark_test')
                    ->label('Include in evaluation')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (SurveyResponse $record) => $record->is_test)
                    ->action(function (SurveyResponse $record) {
                        $record->update(['is_test' => false]);
                        Notification::make()->title('Response restored to evaluation')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([15, 25, 50]);
    }
}
