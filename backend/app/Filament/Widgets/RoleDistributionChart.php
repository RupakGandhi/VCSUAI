<?php

namespace App\Filament\Widgets;

use App\Models\PracticePrompt;
use App\Models\UsageEvent;
use Filament\Widgets\ChartWidget;

class RoleDistributionChart extends ChartWidget
{
    protected static ?string $heading = 'Role Distribution (role_change events)';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $roleLabels = [
            'classroom' => 'Classroom Teacher',
            'leader' => 'School/District Leader',
            'sped' => 'Special Education',
            'support' => 'Support Staff',
            'coach' => 'Instructional Coach',
            'higher_ed' => 'Higher Ed Faculty',
        ];

        $counts = UsageEvent::where('event_type', 'role_change')
            ->whereNotNull('role')
            ->selectRaw('role, COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $labels = [];
        $data = [];
        foreach (PracticePrompt::ROLES as $role) {
            $labels[] = $roleLabels[$role] ?? $role;
            $data[] = $counts[$role] ?? 0;
        }

        return [
            'datasets' => [[
                'label' => 'Role changes',
                'data' => $data,
                'backgroundColor' => ['#2b6cb0', '#6b46c1', '#2f855a', '#c05621', '#c53030', '#718096'],
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
