<?php

namespace App\Filament\Widgets;

use App\Models\UsageEvent;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopSearchTermsTable extends BaseWidget
{
    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Top Search Terms')
            ->query(
                UsageEvent::query()
                    // search_term doubles as the table's record key, since this
                    // is a GROUP BY aggregate with no natural unique id column.
                    ->selectRaw('search_term as id, search_term, COUNT(*) as total')
                    ->where('event_type', 'search')
                    ->whereNotNull('search_term')
                    ->groupBy('search_term')
                    ->orderByDesc('total')
            )
            ->columns([
                Tables\Columns\TextColumn::make('search_term')->label('Term'),
                Tables\Columns\TextColumn::make('total')->label('Count'),
            ])
            ->paginated([5, 10, 25]);
    }
}
