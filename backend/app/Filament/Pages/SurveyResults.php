<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SurveyModuleTable;
use App\Filament\Widgets\SurveyStatsOverview;
use App\Filament\Widgets\SurveyTextResponsesTable;
use App\Models\SurveyResponse;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SurveyResults extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Survey Results';

    protected static ?string $title = 'Module Completion Survey Results';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.survey-results';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clear_survey_data')
                ->label('Clear Survey Data')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Clear all survey responses?')
                ->modalDescription('Permanently deletes every survey response for the client you are currently managing (see the "Managing" badge in the top bar). Other clients are not affected. This cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete all responses')
                ->action(function () {
                    // SurveyResponse is on the content connection, so this
                    // only clears the active client's database.
                    $count = SurveyResponse::query()->count();
                    SurveyResponse::query()->delete();

                    Notification::make()
                        ->title("Deleted {$count} survey response(s)")
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl());
                }),
        ];
    }

    public function getHeaderWidgets(): array
    {
        return [
            SurveyStatsOverview::class,
        ];
    }

    public function getFooterWidgets(): array
    {
        return [
            SurveyModuleTable::class,
            SurveyTextResponsesTable::class,
        ];
    }
}
