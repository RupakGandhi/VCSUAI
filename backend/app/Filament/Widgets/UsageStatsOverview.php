<?php

namespace App\Filament\Widgets;

use App\Models\Module;
use App\Models\UsageEvent;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UsageStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalModules = Module::count();
        $moduleStarts = UsageEvent::where('event_type', 'module_start')->count();
        $moduleCompletions = UsageEvent::where('event_type', 'module_complete')->count();
        $completionRate = $moduleStarts > 0 ? round(($moduleCompletions / $moduleStarts) * 100) : 0;
        $sessions = UsageEvent::whereNotNull('session_id')->distinct('session_id')->count('session_id');
        $searches = UsageEvent::where('event_type', 'search')->count();
        $certificates = UsageEvent::where('event_type', 'certificate_generated')->count();

        return [
            Stat::make('Unique Sessions', $sessions)
                ->description('Anonymous browser sessions seen')
                ->icon('heroicon-o-users'),
            Stat::make('Module Completion Rate', "{$completionRate}%")
                ->description("{$moduleCompletions} completions / {$moduleStarts} starts")
                ->icon('heroicon-o-check-circle'),
            Stat::make('Searches Performed', $searches)
                ->icon('heroicon-o-magnifying-glass'),
            Stat::make('Certificates Generated', $certificates)
                ->icon('heroicon-o-academic-cap'),
            Stat::make('Modules in Curriculum', $totalModules)
                ->icon('heroicon-o-document-text'),
        ];
    }
}
