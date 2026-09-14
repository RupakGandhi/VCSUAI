<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetActiveClient;
use App\Support\ActiveClient;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Support\HtmlString;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('panel')
            ->login()
            ->brandName('OptimizED AI Institute Client Dashboard')
            ->colors([
                'primary' => '#690720',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetActiveClient::class,
            ])
            // Panel middleware only runs on full page loads; Livewire AJAX
            // requests (form saves, table actions) bypass it. Persistent
            // middleware runs on those too — without this, edits made while
            // switched to another client would silently write to the
            // default (hosted) client's database.
            ->persistentMiddleware([
                SetActiveClient::class,
            ])
            ->renderHook('panels::topbar.start', function () {
                $client = ActiveClient::currentOrMaster();
                if (! $client) {
                    return '';
                }

                return new HtmlString(
                    '<span style="display:inline-flex;align-items:center;gap:0.4rem;background:#690720;color:#fff;'
                    .'border-radius:9999px;padding:0.3rem 0.9rem;font-size:0.8rem;font-weight:600;">'
                    .'Managing: '.e($client->name)
                    .'</span>'
                );
            })
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
