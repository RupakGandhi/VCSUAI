<?php

namespace App\Filament\Widgets;

use App\Models\SurveyResponse;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class SurveyStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = null;

    // SurveyAllResponsesTable's exclude/include toggle lives in a separate
    // Livewire component and dispatches this after saving -- without a
    // listener here these numbers kept showing the pre-toggle count/averages
    // until the whole page was reloaded.
    #[On('survey-data-changed')]
    public function refreshStats(): void
    {
        // Empty on purpose: any listener firing causes Livewire to
        // re-render this component, which re-runs getStats() fresh.
    }

    // Visible on the SurveyResults page (and its own Livewire requests)
    // but NOT auto-discovered onto the default Dashboard.
    // NB: Filament registers the Livewire endpoint as "default.livewire.update",
    // hence the leading wildcard.
    public static function canView(): bool
    {
        return request()->routeIs('filament.admin.pages.survey-results', '*livewire.update', '*livewire.upload-file');
    }

    protected function getStats(): array
    {
        $total = SurveyResponse::real()->count();
        $avgQ1 = $total > 0 ? number_format(SurveyResponse::real()->avg('q1_score'), 1) : '—';
        $avgQ2 = $total > 0 ? number_format(SurveyResponse::real()->avg('q2_score'), 1) : '—';

        return [
            Stat::make('Total Responses', $total)
                ->description('Anonymous survey submissions')
                ->icon('heroicon-o-chat-bubble-left-right'),
            Stat::make('Avg Confidence Score (Q1)', $avgQ1)
                ->description('How confident applying module strategies? (1–5)')
                ->icon('heroicon-o-star'),
            Stat::make('Avg Relevance Score (Q2)', $avgQ2)
                ->description('How relevant was the content to their role? (1–5)')
                ->icon('heroicon-o-light-bulb'),
        ];
    }
}
