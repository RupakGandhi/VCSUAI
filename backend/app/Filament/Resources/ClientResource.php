<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use App\Services\ContentSyncService;
use App\Support\ActiveClient;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 0;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('database')
                ->label('Database name')
                ->helperText('Create this database first (phpMyAdmin / hPanel), then register it here and run migrations.')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('db_host')
                ->label('Database host (optional)')
                ->placeholder('Leave empty to use the master host'),
            Forms\Components\TextInput::make('db_username')
                ->label('Database username (optional)')
                ->placeholder('Leave empty to use the master credentials'),
            Forms\Components\TextInput::make('db_password')
                ->label('Database password (optional)')
                ->password()
                ->revealable(),
            Forms\Components\Textarea::make('notes')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->weight('bold')
                    ->description(fn (Client $record) => $record->notes),
                Tables\Columns\TextColumn::make('database')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('public_token')
                    ->label('Site link')
                    ->formatStateUsing(fn (Client $record) => $record->publicUrl())
                    ->copyable()
                    ->copyableState(fn (Client $record) => $record->publicUrl())
                    ->copyMessage('Site link copied')
                    ->limit(45)
                    ->tooltip('Click to copy — share this link with the client'),
                Tables\Columns\IconColumn::make('active')
                    ->label('Managing now')
                    ->boolean()
                    ->state(fn (Client $record) => $record->id === (ActiveClient::currentOrMaster()?->id)),
            ])
            ->actions([
                Tables\Actions\Action::make('open_site')
                    ->label('Open site')
                    ->icon('heroicon-o-globe-alt')
                    ->url(fn (Client $record) => $record->publicUrl())
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('download_html')
                    ->label('Download site (ZIP)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Client $record) => url('/panel/static-export?client='.$record->id)),
                Tables\Actions\Action::make('switch')
                    ->label('Switch to')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->visible(fn (Client $record) => $record->id !== (ActiveClient::currentOrMaster()?->id))
                    ->action(function (Client $record) {
                        session(['active_client_id' => $record->id]);

                        Notification::make()
                            ->title("Now managing: {$record->name}")
                            ->body('All content pages (strands, courses, modules, simulations, settings, exports) now read and write this client\'s database.')
                            ->success()
                            ->send();

                        redirect(request()->header('Referer') ?: '/panel');
                    }),
                Tables\Actions\Action::make('migrate')
                    ->label('Run migrations')
                    ->icon('heroicon-o-circle-stack')
                    ->requiresConfirmation()
                    ->modalDescription('Creates/updates all content tables in this client\'s database. Safe to re-run.')
                    ->action(function (Client $record) {
                        try {
                            ActiveClient::apply($record);
                            Artisan::call('migrate', ['--database' => 'content', '--force' => true]);
                            $output = trim(Artisan::output());

                            Notification::make()
                                ->title("Migrations complete for {$record->name}")
                                ->body(mb_substr($output, -500))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Migration failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        } finally {
                            // Restore the session-selected client's connection
                            ActiveClient::apply(ActiveClient::current());
                        }
                    }),
                Tables\Actions\Action::make('sync_content_revision')
                    ->label('Sync Revised Content (2026-07)')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalDescription('Applies the 2026-07 content revision (per-role Challenges + revised Step-by-Step Guide prompts) to this client\'s database. Only touches modules this client actually has; existing deliverable titles/descriptions are left alone.')
                    ->action(function (Client $record) {
                        try {
                            ActiveClient::apply($record);
                            $summary = app(ContentSyncService::class)->run();

                            $body = "Challenge rows written: {$summary['challengeRowsWritten']}\n"
                                ."Deliverable prompts updated: {$summary['deliverablesUpdated']}\n"
                                ."Deliverable rows created: {$summary['deliverablesCreated']}\n"
                                .'Modules skipped (not in this client): '
                                .(count($summary['modulesSkipped']) ? implode(', ', $summary['modulesSkipped']) : 'none');

                            Notification::make()
                                ->title("Content sync complete for {$record->name}")
                                ->body($body)
                                ->success()
                                ->persistent()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Content sync failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        } finally {
                            // Restore the session-selected client's connection
                            ActiveClient::apply(ActiveClient::current());
                        }
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Client $record) => ! $record->isMaster())
                    ->modalDescription('Removes the client from this registry only — the database itself is not touched.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageClients::route('/'),
        ];
    }
}
