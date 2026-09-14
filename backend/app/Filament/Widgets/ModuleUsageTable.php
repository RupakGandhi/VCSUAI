<?php

namespace App\Filament\Widgets;

use App\Models\Module;
use App\Models\UsageEvent;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ModuleUsageTable extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Module Usage')
            ->query($this->usageQuery())
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Module'),
                Tables\Columns\TextColumn::make('title')->limit(40),
                Tables\Columns\TextColumn::make('starts')->label('Starts')->sortable(),
                Tables\Columns\TextColumn::make('completions')->label('Completions')->sortable(),
                Tables\Columns\TextColumn::make('sims')->label('Sim Uses')->sortable(),
            ])
            ->defaultSort('id')
            ->paginated([10, 25, 50]);
    }

    private function usageQuery(): Builder
    {
        $eventCount = fn (string $type) => UsageEvent::selectRaw('COUNT(*)')
            ->whereColumn('module_id', 'modules.id')
            ->where('event_type', $type);

        return Module::query()
            ->select('modules.*')
            ->selectSub($eventCount('module_start'), 'starts')
            ->selectSub($eventCount('module_complete'), 'completions')
            ->selectSub($eventCount('sim_used'), 'sims');
    }
}
