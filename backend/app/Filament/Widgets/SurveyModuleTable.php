<?php

namespace App\Filament\Widgets;

use App\Models\Module;
use App\Models\SurveyResponse;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class SurveyModuleTable extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    // Visible on the SurveyResults page (and its own Livewire requests for
    // sorting/actions) but NOT auto-discovered onto the default Dashboard.
    // NB: Filament registers the Livewire endpoint as "default.livewire.update",
    // hence the leading wildcard.
    public static function canView(): bool
    {
        return request()->routeIs('filament.admin.pages.survey-results', '*livewire.update', '*livewire.upload-file');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Survey Responses by Module')
            ->query($this->moduleQuery())
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Module ID'),
                Tables\Columns\TextColumn::make('title')->limit(45),
                Tables\Columns\TextColumn::make('response_count')->label('Responses')->sortable(),
                Tables\Columns\TextColumn::make('avg_q1')->label('Avg Confidence (Q1)')->sortable(),
                Tables\Columns\TextColumn::make('avg_q2')->label('Avg Relevance (Q2)')->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('clear_module_responses')
                    ->label('Clear responses')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Module $record) => "Clear survey responses for {$record->id}?")
                    ->modalDescription('Deletes this module\'s responses for the client you are currently managing. This cannot be undone.')
                    ->action(function (Module $record) {
                        $count = SurveyResponse::where('module_id', $record->id)->delete();

                        Notification::make()
                            ->title("Deleted {$count} response(s) for {$record->id}")
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('response_count', 'desc')
            ->paginated([10, 25, 50]);
    }

    private function moduleQuery(): Builder
    {
        $responseCount = fn () => SurveyResponse::real()->selectRaw('COUNT(*)')
            ->whereColumn('module_id', 'modules.id');

        $avgQ1 = fn () => SurveyResponse::real()->selectRaw('ROUND(AVG(q1_score),1)')
            ->whereColumn('module_id', 'modules.id');

        $avgQ2 = fn () => SurveyResponse::real()->selectRaw('ROUND(AVG(q2_score),1)')
            ->whereColumn('module_id', 'modules.id');

        return Module::query()
            ->select('modules.*')
            ->selectSub($responseCount(), 'response_count')
            ->selectSub($avgQ1(), 'avg_q1')
            ->selectSub($avgQ2(), 'avg_q2');
    }
}
