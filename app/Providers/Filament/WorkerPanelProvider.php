<?php

namespace App\Providers\Filament;

use App\Filament\Worker\Widgets\WorkerOverview;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Pages;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class WorkerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('worker')
            ->path('worker')
            ->login()
            ->brandName('OPS eGirlz Воркеры')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->renderHook(
                'panels::head.end',
                fn (): string => <<<'HTML'
<style>
    :root {
        --ops-surface: #111827;
        --ops-surface-2: #1f2937;
        --ops-border: rgba(255, 255, 255, 0.08);
    }
    body.fi-body {
        background: radial-gradient(1200px 600px at 10% -10%, rgba(245, 158, 11, 0.14), transparent), radial-gradient(1000px 500px at 90% -20%, rgba(34, 197, 94, 0.10), transparent), #030712;
    }
    .fi-topbar, .fi-sidebar {
        background: linear-gradient(180deg, rgba(17, 24, 39, 0.92), rgba(3, 7, 18, 0.94));
        backdrop-filter: blur(8px);
    }
    .fi-sidebar-item-button, .fi-btn, .fi-pagination-item-button {
        border-radius: 12px !important;
    }
    .fi-section, .fi-ta-ctn, .fi-in-entry-wrp, .fi-fo-field-wrp {
        border-radius: 16px !important;
    }
    .fi-section, .fi-ta-ctn, .fi-modal-window, .fi-in-entry-wrp {
        border: 1px solid var(--ops-border) !important;
        background: linear-gradient(180deg, rgba(17, 24, 39, 0.95), rgba(15, 23, 42, 0.95)) !important;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.32);
    }
    .fi-ta-row:hover {
        background: rgba(245, 158, 11, 0.08) !important;
    }
    .fi-input, .fi-select-input, .fi-textarea {
        border-radius: 12px !important;
    }
    @media (max-width: 768px) {
        .fi-main {
            padding-inline: 0.75rem !important;
        }
        .fi-section {
            border-radius: 14px !important;
        }
        .fi-section-header-heading {
            font-size: 1.05rem !important;
        }
        .fi-tabs-item-btn {
            padding: 0.45rem 0.7rem !important;
            font-size: 0.8rem !important;
        }
        .fi-ta-ctn {
            overflow-x: auto;
        }
        .fi-ta-table {
            min-width: 700px;
        }
        .fi-btn {
            min-height: 40px;
        }
    }
</style>
HTML
            )
            ->discoverResources(in: app_path('Filament/Worker/Resources'), for: 'App\\Filament\\Worker\\Resources')
            ->discoverWidgets(in: app_path('Filament/Worker/Widgets'), for: 'App\\Filament\\Worker\\Widgets')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->widgets([
                WorkerOverview::class,
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
