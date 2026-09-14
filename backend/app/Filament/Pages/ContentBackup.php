<?php

namespace App\Filament\Pages;

use App\Services\ContentExportService;
use App\Services\ContentImportService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export everything to a single JSON file (backup / the portability
 * package owed to VCSU at contract end) and bulk-import the same shape
 * back in for content refreshes. See ContentImportService for the safety
 * rules a refresh file needs to follow (partial updates are safe; omitted
 * fields are never overwritten with null).
 */
class ContentBackup extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationLabel = 'Export & Import';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.content-backup';

    public ?array $importData = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\FileUpload::make('file')
                ->label('JSON file to import')
                ->helperText('Only genuinely valid JSON will be imported -- the file is parsed and rejected below if it isn\'t.')
                // Deliberately no acceptedFileTypes()/mimetypes validation
                // here. That rule sniffs the file's actual bytes via finfo,
                // which has no magic-byte signature for JSON and guesses
                // wildly (text/plain, or text/html when the export's
                // learnHtml/footerHtml fields put a "<" near the start of
                // the file) -- rejecting genuinely valid exports before
                // they're even read. runImport() below already does the
                // real check: json_decode() the contents and bail with
                // "not valid JSON" if that fails. That's the only
                // trustworthy validation for this field.
                ->disk('local')
                ->directory('vcsu-imports')
                ->required(),
        ];
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema($this->getFormSchema())->statePath('importData');
    }

    public function downloadExport(): StreamedResponse
    {
        $exporter = app(ContentExportService::class);
        $data = $exporter->export();
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return response()->streamDownload(
            fn () => print($json),
            'vcsu-export-'.now()->format('Y-m-d-His').'.json'
        );
    }

    public function runImport(): void
    {
        $state = $this->form->getState();
        $relativePath = $state['file'] ?? null;

        if (! $relativePath) {
            Notification::make()->title('Please choose a file first.')->danger()->send();

            return;
        }

        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($relativePath);
        $contents = file_get_contents($fullPath);
        $data = json_decode($contents, true);

        if ($data === null) {
            Notification::make()->title('That file is not valid JSON.')->danger()->send();

            return;
        }

        $summary = app(ContentImportService::class)->import($data);

        \Illuminate\Support\Facades\Storage::disk('local')->delete($relativePath);
        $this->form->fill();

        Notification::make()
            ->title('Import complete')
            ->body(collect($summary)->map(fn ($v, $k) => "{$k}: {$v}")->implode(' · '))
            ->success()
            ->send();
    }
}
