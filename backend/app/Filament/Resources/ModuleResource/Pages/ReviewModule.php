<?php

namespace App\Filament\Resources\ModuleResource\Pages;

use App\Filament\Resources\ModuleResource;
use App\Models\PracticePrompt;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

/**
 * One-screen view of everything for a module: Overview, Practice Prompts,
 * and Simulations (including each simulation's verification tips and
 * follow-up conversation options) are editable here together, so an admin
 * can judge whether a prompt/response/verification/follow-up set is
 * internally aligned and fix it on the spot -- instead of hopping between
 * three separate RelationManager modals. Learn/Apply/Facilitator stay
 * read-only here (unchanged from the original review page) since editing
 * those rich-HTML fields safely is a separate, not-yet-built piece of work.
 */
class ReviewModule extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = ModuleResource::class;

    protected static string $view = 'filament.resources.module-resource.pages.review-module';

    // Deliberately NOT named $record: Livewire's ImplicitRouteBinding
    // auto-binds any public property whose name matches a route parameter
    // and has a class type-hint, which collided with the {record} route
    // parameter and broke mount() resolution (silently surfaced as a 404).
    public ?\App\Models\Module $module = null;

    public ?array $data = [];

    public function mount(string|int $record): void
    {
        $this->module = ModuleResource::getEloquentQuery()
            ->with(['content', 'practicePrompts', 'applyDeliverables', 'simulations', 'course.strand'])
            ->findOrFail($record);

        // Filament's relationship-bound fields (the `content` Group, the
        // `practicePrompts`/`simulations` Repeaters) only hydrate from
        // their relationships when fill() is called WITH a non-null state
        // array (mirrors Filament's own EditRecord::fillFormWithDataAndCallHooks()).
        // A bare fill() makes every field fall back to its empty default
        // instead, silently showing a blank form over real existing data.
        $this->form->fill($this->module->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('ModuleReview')
                    ->columnSpanFull()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Overview')
                            ->schema([
                                Forms\Components\Group::make([
                                    Forms\Components\TagsInput::make('ilos')
                                        ->label('Intended Learning Outcomes')
                                        ->placeholder('Add an ILO and press Enter')
                                        ->columnSpanFull(),
                                    Forms\Components\Textarea::make('challenge')
                                        ->label('The Challenge')
                                        ->rows(4)
                                        ->columnSpanFull(),
                                    Forms\Components\Textarea::make('concept')
                                        ->label('Key Concept')
                                        ->rows(4)
                                        ->columnSpanFull(),
                                    Forms\Components\Textarea::make('matters')
                                        ->label('Why It Matters')
                                        ->rows(4)
                                        ->columnSpanFull(),
                                ])->relationship('content'),
                            ]),

                        Forms\Components\Tabs\Tab::make('Practice Prompts')
                            ->schema([
                                Forms\Components\Repeater::make('practicePrompts')
                                    ->relationship()
                                    ->label('')
                                    ->schema([
                                        Forms\Components\Select::make('role')
                                            ->options(array_combine(PracticePrompt::ROLES, PracticePrompt::ROLES))
                                            ->required(),
                                        Forms\Components\TextInput::make('title')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('prompt_text')
                                            ->required()
                                            ->rows(4)
                                            ->columnSpanFull(),
                                        Forms\Components\FileUpload::make('sample_file')
                                            ->label('Sample file (optional — shown as a download link with this prompt)')
                                            ->disk('public')
                                            ->directory('prompt-samples')
                                            ->downloadable()
                                            ->maxSize(10240)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2)
                                    ->itemLabel(fn (array $state): ?string => ($state['role'] ?? '?').' — '.($state['title'] ?? 'New prompt'))
                                    ->collapsed()
                                    ->reorderableWithButtons()
                                    ->orderColumn('sort_order')
                                    ->defaultItems(0)
                                    ->addActionLabel('Add practice prompt'),
                            ]),

                        Forms\Components\Tabs\Tab::make('Simulations')
                            ->schema([
                                Forms\Components\Toggle::make('requires_file_upload')
                                    ->label('This module requires a file upload (shows 📎 button + sample download in the simulator)')
                                    ->helperText('Module-level setting — applies to the whole simulator, no need to set it per simulation.')
                                    ->columnSpanFull(),

                                Forms\Components\Repeater::make('simulations')
                                    ->relationship()
                                    ->label('')
                                    ->schema([
                                        Forms\Components\Select::make('role')
                                            ->options(array_combine(PracticePrompt::ROLES, PracticePrompt::ROLES))
                                            ->required(),
                                        Forms\Components\TextInput::make('title')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('prompt_text')
                                            ->label('Trigger prompt / keywords context')
                                            ->required()
                                            ->rows(2)
                                            ->columnSpanFull(),
                                        Forms\Components\TagsInput::make('keywords')
                                            ->columnSpanFull(),
                                        Forms\Components\Textarea::make('response')
                                            ->label('Simulated AI response')
                                            ->required()
                                            ->rows(6)
                                            ->columnSpanFull(),

                                        Forms\Components\Repeater::make('verification_tips')
                                            ->label('Verification Guide tips')
                                            ->simple(
                                                Forms\Components\TextInput::make('tip')
                                                    ->required()
                                                    ->maxLength(500)
                                            )
                                            ->addActionLabel('Add tip')
                                            ->columnSpanFull(),

                                        Forms\Components\Repeater::make('followup_options')
                                            ->label('Follow-up conversation options')
                                            ->schema([
                                                Forms\Components\TextInput::make('label')
                                                    ->required()
                                                    ->maxLength(100),
                                                Forms\Components\Textarea::make('prompt')
                                                    ->label('Follow-up prompt (sent on click)')
                                                    ->required()
                                                    ->rows(2)
                                                    ->columnSpanFull(),
                                                Forms\Components\Textarea::make('response')
                                                    ->label('Follow-up response')
                                                    ->required()
                                                    ->rows(4)
                                                    ->columnSpanFull(),
                                            ])
                                            ->addActionLabel('Add follow-up option')
                                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'New follow-up')
                                            ->collapsed()
                                            ->columnSpanFull(),

                                        Forms\Components\Repeater::make('bias_check_tips')
                                            ->label('Bias Check tips (appear after follow-up response)')
                                            ->simple(
                                                Forms\Components\TextInput::make('tip')
                                                    ->required()
                                                    ->maxLength(500)
                                            )
                                            ->addActionLabel('Add bias check tip')
                                            ->columnSpanFull(),

                                    ])
                                    ->columns(2)
                                    ->itemLabel(fn (array $state): ?string => ($state['role'] ?? '?').' — '.($state['title'] ?? 'New simulation'))
                                    ->collapsed()
                                    ->reorderableWithButtons()
                                    ->orderColumn('sort_order')
                                    ->defaultItems(0)
                                    ->addActionLabel('Add simulation'),
                            ]),
                    ]),
            ])
            ->statePath('data')
            ->model($this->module);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        // Module-level attributes aren't relationship-bound like the
        // repeaters, so persist them explicitly.
        $this->module->update([
            'requires_file_upload' => (bool) ($state['requires_file_upload'] ?? false),
        ]);

        Notification::make()
            ->title('Module content saved')
            ->success()
            ->send();
    }

    public function getTitle(): string
    {
        return "Review: {$this->module->id} — {$this->module->title}";
    }

    /** @return array<string> */
    public function getRoles(): array
    {
        return PracticePrompt::ROLES;
    }
}
