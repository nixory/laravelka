<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\AdminQuickActions;
use App\Filament\Widgets\AdminOpsOverview;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
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
            ->path('admin')
            ->login()
            ->brandName('OPS eGirlz Админ')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->renderHook(
                'panels::head.end',
                fn (): string => <<<'HTML'
<style>
    :root {
        --ops-surface: #0b1020;
        --ops-surface-2: #101936;
        --ops-card: rgba(11, 16, 32, 0.86);
        --ops-card-soft: rgba(255, 255, 255, 0.02);
        --ops-border: rgba(255, 255, 255, 0.10);
        --ops-muted: #95a4be;
    }
    body.fi-body {
        background:
            radial-gradient(1200px 600px at 8% -14%, rgba(245, 158, 11, 0.10), transparent),
            radial-gradient(900px 500px at 95% -20%, rgba(56, 189, 248, 0.08), transparent),
            linear-gradient(180deg, #050913 0%, #030712 100%);
    }
    .fi-topbar, .fi-sidebar {
        background: linear-gradient(180deg, rgba(12, 18, 36, 0.90), rgba(5, 10, 22, 0.94));
        backdrop-filter: blur(10px);
        border-color: rgba(255, 255, 255, 0.06) !important;
    }
    .fi-sidebar-item-button, .fi-btn, .fi-pagination-item-button {
        border-radius: 12px !important;
    }
    .fi-section, .fi-ta-ctn, .fi-fo-field-wrp {
        border-radius: 16px !important;
    }
    .fi-section, .fi-ta-ctn, .fi-modal-window {
        border: 1px solid var(--ops-border) !important;
        background: linear-gradient(180deg, rgba(12, 18, 36, 0.90), rgba(9, 14, 30, 0.92)) !important;
        box-shadow: 0 10px 28px rgba(0, 0, 0, 0.30);
    }
    .fi-in-entry-wrp {
        border-radius: 12px !important;
        border: 1px solid rgba(255, 255, 255, 0.06) !important;
        background: var(--ops-card-soft) !important;
        box-shadow: none !important;
    }
    .fi-in-entry-wrp-label,
    .fi-in-entry-label,
    .fi-fo-field-wrp-label {
        color: var(--ops-muted) !important;
        font-weight: 600 !important;
        letter-spacing: .01em;
    }
    .fi-section-header-heading {
        letter-spacing: .01em;
    }
    .fi-section-content {
        padding-top: 1rem !important;
    }
    .fi-ta-row:hover {
        background: rgba(245, 158, 11, 0.06) !important;
    }
    .fi-input, .fi-select-input, .fi-textarea {
        border-radius: 12px !important;
        border-color: rgba(255, 255, 255, 0.12) !important;
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
        .fi-in-entry-wrp {
            border-radius: 10px !important;
        }
    }
</style>
HTML
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AdminOpsOverview::class,
                AdminQuickActions::class,
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
