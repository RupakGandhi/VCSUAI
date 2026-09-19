<?php

namespace App\Filament\Widgets;

use App\Models\Module;
use App\Models\SurveyResponse;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class SurveyTextResponsesTable extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    // See SurveyStatsOverview::refreshStats() -- a row excluded/included
    // from SurveyAllResponsesTable may have open-text feedback shown here.
    #[On('survey-data-changed')]
    public function refreshTextResponses(): void
    {
    }

    // Visible on the SurveyResults page (and its own Livewire requests)
    // but NOT auto-discovered onto the default Dashboard.
    // NB: Filament registers the Livewire endpoint as "default.livewire.update",
    // hence the leading wildcard.
    public static function canView(): bool
    {
        return request()->routeIs('filament.admin.pages.survey-results', '*livewire.update', '*livewire.upload-file');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Open-Text Responses (Q3: "What would improve this module?")')
            ->query(
                SurveyResponse::real()
                    ->whereNotNull('q3_text')
                    ->where('q3_text', '!=', '')
                    ->latest('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('module_id')->label('Module')->sortable(),
                Tables\Columns\TextColumn::make('role')->badge()->default('—'),
                Tables\Columns\TextColumn::make('q3_text')->label('Response')->limit(120)->wrap(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('module_id')
                    ->label('Module')
                    ->options(fn () => Module::pluck('title', 'id')),
            ])
            ->headerActions([
                Tables\Actions\Action::make('download_csv')
                    ->label('Download CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url('/panel/survey-export')
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([15, 25, 50]);
    }

}
