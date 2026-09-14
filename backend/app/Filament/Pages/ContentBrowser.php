<?php

namespace App\Filament\Pages;

use App\Services\ContentReviewExportService;
use Filament\Pages\Page;

/**
 * Read-only view of the currently-managed client's content, organized
 * Module > Role > Challenge/Practice Prompts/Mastery Prompts/Deliverables --
 * the same grouping as ContentReviewExportService's export, just rendered
 * as a collapsible page instead of a downloaded file. Exists specifically
 * so an admin can check that everything within one module/role reads as a
 * cohesive whole (the Challenge, prompts, and deliverables all making sense
 * together) without needing to open a file just to look.
 */
class ContentBrowser extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Content Browser';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.content-browser';

    public array $modules = [];

    public function mount(): void
    {
        $this->modules = app(ContentReviewExportService::class)->gatherData();
    }
}
