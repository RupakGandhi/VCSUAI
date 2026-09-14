<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlatformSettingResource\Pages;
use App\Filament\Resources\PlatformSettingResource\RelationManagers;
use App\Models\PlatformSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlatformSettingResource extends Resource
{
    protected static ?string $model = PlatformSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Branding')
                    ->schema([
                        Forms\Components\TextInput::make('title')->required()->columnSpanFull(),
                        Forms\Components\TextInput::make('subtitle'),
                        Forms\Components\TextInput::make('page_title')->label('Browser tab title'),
                        Forms\Components\ColorPicker::make('primary_color'),
                        Forms\Components\ColorPicker::make('accent_color'),
                    ])->columns(2),
                Forms\Components\Section::make('Home Screen Hero')
                    ->schema([
                        Forms\Components\TextInput::make('hero_title')->columnSpanFull(),
                        Forms\Components\Textarea::make('hero_desc')->rows(2)->columnSpanFull(),
                        Forms\Components\TagsInput::make('hero_stats')
                            ->label('Hero stat labels (in display order)')
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Deliverable Guides')
                    ->description('The AI tool participants are told to open in every deliverable\'s step-by-step guide ("Open … and use this prompt"). Leave empty for the default.')
                    ->schema([
                        Forms\Components\TextInput::make('ai_tool_name')
                            ->label('AI tool name')
                            ->placeholder('Microsoft Copilot')
                            ->maxLength(100),
                    ]),
                Forms\Components\Section::make('Role Display Names')
                    ->description('What each role lens is called in the frontend dropdown. Internal keys never change — only these labels. Leave a field empty to use the default shown in its placeholder.')
                    ->schema([
                        Forms\Components\TextInput::make('role_labels.classroom')
                            ->label('classroom')->placeholder('Teacher'),
                        Forms\Components\TextInput::make('role_labels.leader')
                            ->label('leader')->placeholder('School/District Leader'),
                        Forms\Components\TextInput::make('role_labels.sped')
                            ->label('sped')->placeholder('Special Education'),
                        Forms\Components\TextInput::make('role_labels.support')
                            ->label('support')->placeholder('Support Staff'),
                        Forms\Components\TextInput::make('role_labels.coach')
                            ->label('coach')->placeholder('Instructional Coach'),
                        Forms\Components\TextInput::make('role_labels.higher_ed')
                            ->label('higher_ed')->placeholder('Higher Ed Faculty'),
                    ])->columns(3),
                Forms\Components\Section::make('Footer')
                    ->schema([
                        Forms\Components\Textarea::make('footer_html')
                            ->label('Footer content (HTML allowed)')
                            ->helperText('Each <p>…</p> renders as one footer line. Links are allowed, e.g. <a href="…">OptimizED</a>.')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Module Survey Questions')
                    ->description('Shown when a participant marks a module complete (hosted mode only). Q1 and Q2 are 1–5 scales; Q3 is open text. Only the wording is editable — the scale structure is fixed so analytics stay comparable.')
                    ->schema([
                        Forms\Components\TextInput::make('survey_q1_text')
                            ->label('Question 1 (1–5 scale)')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('survey_q2_text')
                            ->label('Question 2 (1–5 scale)')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('survey_q3_text')
                            ->label('Question 3 (open text, optional for participants)')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('PWA App Icon')
                    ->description('The icon shown on mobile home screens when a participant installs this client\'s site as an app. Leave any size empty to use the platform default icon.')
                    ->schema([
                        Forms\Components\FileUpload::make('icon_192')
                            ->label('Icon (192×192 PNG)')
                            ->image()
                            ->disk('public')
                            ->directory('platform-icons')
                            ->maxSize(1024),
                        Forms\Components\FileUpload::make('icon_512')
                            ->label('Icon (512×512 PNG)')
                            ->image()
                            ->disk('public')
                            ->directory('platform-icons')
                            ->maxSize(2048),
                        Forms\Components\FileUpload::make('icon_512_maskable')
                            ->label('Maskable icon (512×512 PNG, safe-zone padded)')
                            ->image()
                            ->disk('public')
                            ->directory('platform-icons')
                            ->maxSize(2048),
                    ])->columns(3),
                Forms\Components\Section::make('Access Codes')
                    ->description('Plaintext codes used by the existing front-end access gate (not a new auth system).')
                    ->schema([
                        Forms\Components\TextInput::make('password')->label('Platform access code'),
                        Forms\Components\TextInput::make('fac_password')->label('Facilitator password'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePlatformSettings::route('/'),
        ];
    }
}
