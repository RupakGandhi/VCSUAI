<?php

namespace App\Filament\Pages;

use App\Services\ContentReviewExportService;
use App\Services\ContentReviewImportService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Human-readable content review workflow: export the currently-managed
 * client's Challenges/Practice Prompts/Mastery Prompts/Deliverables as one
 * plain-text file (same shape proven to work for the Word-doc content
 * revisions), let an admin edit it directly or run it through an AI tool
 * and check the output, then preview the exact diff before committing --
 * so validating an AI-assisted content revision never requires reading raw
 * JSON.
 *
 * Deliberately two separate steps (preview, then apply): previewChanges()
 * only calls ContentReviewImportService::diff() (read-only) and stores the
 * result on the page; applyChanges() re-parses the same uploaded file and
 * calls apply(), so nothing is ever written without the admin having seen
 * the diff first.
 */
class ContentReview extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationLabel = 'Content Review Export/Import';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.content-review';

    public ?array $importData = [];

    public ?array $diffResult = null;

    public ?array $validationErrors = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\FileUpload::make('file')
                ->label('Reviewed content file (.txt)')
                ->helperText('Upload the file you exported below (edited as needed). Click "Preview Changes" first -- nothing is written to the database until you click "Apply Changes".')
                ->disk('local')
                ->directory('vcsu-content-review')
                ->required(),
        ];
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema($this->getFormSchema())->statePath('importData');
    }

    public function downloadExport(): StreamedResponse
    {
        $text = app(ContentReviewExportService::class)->export();

        return response()->streamDownload(
            fn () => print($text),
            'content-review-'.now()->format('Y-m-d-His').'.txt'
        );
    }

    public function previewChanges(): void
    {
        $text = $this->readUploadedFile();
        if ($text === null) {
            return;
        }

        $this->diffResult = null;
        $this->validationErrors = null;

        $service = app(ContentReviewImportService::class);
        $parseResult = $service->parse($text);
        $errors = $parseResult['warnings'];
        $errors = array_merge($errors, $service->validateAgainstDatabase($parseResult['modules']));

        if (! empty($errors)) {
            $this->validationErrors = $errors;
            Notification::make()->title(count($errors).' problem(s) found -- fix the file and re-upload')->danger()->send();

            return;
        }

        $this->diffResult = $service->diff($parseResult['modules']);

        if (empty($this->diffResult['changes']) && empty($this->diffResult['modulesSkipped'])) {
            Notification::make()->title('No changes detected')->body('The uploaded file matches the current content exactly.')->success()->send();
        }
    }

    public function applyChanges(): void
    {
        $text = $this->readUploadedFile();
        if ($text === null) {
            return;
        }

        $service = app(ContentReviewImportService::class);
        $parseResult = $service->parse($text);
        $errors = array_merge($parseResult['warnings'], $service->validateAgainstDatabase($parseResult['modules']));

        if (! empty($errors)) {
            // Re-checked here too (not just in previewChanges) in case the
            // uploaded file changed between preview and apply -- apply()
            // must never write from a file that hasn't just passed validation.
            $this->diffResult = null;
            $this->validationErrors = $errors;
            Notification::make()->title('Cannot apply -- validation problems found')->body('The file changed or was never previewed successfully. See the problems listed below.')->danger()->send();

            return;
        }

        $result = $service->apply($parseResult['modules']);

        $relativePath = $this->form->getState()['file'] ?? null;
        if ($relativePath) {
            Storage::disk('local')->delete($relativePath);
        }
        $this->form->fill();
        $this->diffResult = null;
        $this->validationErrors = null;

        $body = "Updated: {$result['updated']} · Created: {$result['created']} · Deleted: {$result['deleted']}";
        if (! empty($result['modulesSkipped'])) {
            $body .= "\nModules skipped (not in this client): ".implode(', ', $result['modulesSkipped']);
        }

        Notification::make()->title('Content review import applied')->body($body)->success()->persistent()->send();
    }

    private function readUploadedFile(): ?string
    {
        $state = $this->form->getState();
        $relativePath = $state['file'] ?? null;
        if (! $relativePath) {
            Notification::make()->title('Please choose a file first.')->danger()->send();

            return null;
        }

        return file_get_contents(Storage::disk('local')->path($relativePath));
    }
}
