<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Browse the change history captured automatically by the LogsActivity
 * trait on every content model. Answers "what was edited, when, by whom"
 * and lets an admin revert a single field-level change back to its
 * previous value.
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Activity Log';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['causer', 'subject']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y g:ia')
                    ->sortable(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Changed by')
                    ->placeholder('System / import')
                    ->default('System / import'),
                Tables\Columns\TextColumn::make('event')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Content type')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),
                Tables\Columns\TextColumn::make('subject_id')
                    ->label('Record'),
                Tables\Columns\TextColumn::make('changed_fields')
                    ->label('Fields changed')
                    ->state(function (Activity $record) {
                        $changed = $record->changes()->get('attributes', []);

                        return $changed ? implode(', ', array_keys($changed)) : '—';
                    })
                    ->limit(60),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Content type')
                    ->options([
                        'App\\Models\\Strand' => 'Strand',
                        'App\\Models\\Course' => 'Course',
                        'App\\Models\\Module' => 'Module',
                        'App\\Models\\ModuleContent' => 'Module Content',
                        'App\\Models\\PracticePrompt' => 'Practice Prompt',
                        'App\\Models\\ApplyDeliverable' => 'Apply Deliverable',
                        'App\\Models\\Simulation' => 'Simulation',
                        'App\\Models\\PlatformSetting' => 'Platform Settings',
                    ]),
                Tables\Filters\SelectFilter::make('event')
                    ->options(['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted']),
            ])
            ->actions([
                Tables\Actions\Action::make('viewChanges')
                    ->label('View changes')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Change details')
                    ->modalContent(fn (Activity $record) => view('filament.resources.activity-resource.changes', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\Action::make('revert')
                    ->label('Revert')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Activity $record) => $record->event === 'updated'
                        && $record->changes()->get('old')
                        && $record->subject()->exists())
                    ->requiresConfirmation()
                    ->modalDescription('This restores the values shown as "before" for this change. It does not undo any LATER edits made to the same fields.')
                    ->action(function (Activity $record) {
                        $old = $record->changes()->get('old', []);
                        $subject = $record->subject;
                        if ($subject && $old) {
                            $subject->update($old);
                            \Filament\Notifications\Notification::make()
                                ->title('Reverted')
                                ->body('Restored '.implode(', ', array_keys($old)))
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
