<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\TicketResource\Pages\EditTicket;
use App\Http\Controllers\Admin\NavigationController;
use App\Support\PanelNavigation;
use Filament\Navigation\NavigationBuilder;
use Illuminate\Support\Facades\Route;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Widgets\NeedsAttentionTicketsWidget;
use App\Filament\Widgets\TicketsByStatusChart;
use App\Filament\Widgets\TicketStatsOverview;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->brandName('FunkIT HelpDesk')
            ->brandLogo(asset('images/funkit-logo.png'))
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => Blade::render('<x-turnstile model="turnstileToken" />'),
            )
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn (): string => <<<'HTML'
                <style>
                    .ticket-attr__group { min-height: 2.25rem; }
                    .ticket-attr {
                        display: inline-flex;
                        flex-direction: column;
                        gap: 0.125rem;
                        margin: -0.25rem -0.5rem;
                        padding: 0.25rem 0.5rem;
                        border-radius: 0.5rem;
                        cursor: pointer;
                        transition: background-color 75ms ease;
                    }
                    .ticket-attr:hover,
                    .ticket-attr:focus-visible {
                        background-color: rgba(var(--gray-950), 0.05);
                        outline: none;
                    }
                    .dark .ticket-attr:hover,
                    .dark .ticket-attr:focus-visible {
                        background-color: rgba(var(--white), 0.05);
                    }
                    .ticket-attr__label {
                        font-size: 0.6875rem;
                        font-weight: 500;
                        line-height: 1;
                        text-transform: uppercase;
                        letter-spacing: 0.04em;
                        color: rgb(var(--gray-500));
                    }
                    .ticket-attr__value {
                        font-size: 0.875rem;
                        font-weight: 600;
                        color: rgb(var(--primary-600));
                        text-decoration: underline;
                        text-decoration-style: dotted;
                        text-underline-offset: 2px;
                    }
                    .dark .ticket-attr__value { color: rgb(var(--primary-400)); }

                    .ticket-message {
                        border: 1px solid rgb(var(--gray-200));
                        border-radius: 0.75rem;
                        background-color: rgb(var(--gray-50));
                        padding: 1.25rem 1.5rem;
                        box-shadow: 0 1px 2px 0 rgba(var(--gray-950), 0.04);
                    }
                    .dark .ticket-message {
                        border-color: rgb(var(--gray-800));
                        background-color: rgb(var(--gray-900));
                    }
                    .ticket-message__header {
                        display: flex;
                        align-items: center;
                        gap: 0.375rem;
                        margin-bottom: 0.875rem;
                        font-size: 0.6875rem;
                        font-weight: 600;
                        letter-spacing: 0.05em;
                        text-transform: uppercase;
                        color: rgb(var(--gray-500));
                    }
                    .ticket-message__header-icon { width: 0.9375rem; height: 0.9375rem; }
                    .ticket-message__body {
                        white-space: pre-wrap;
                        overflow-wrap: anywhere;
                        max-height: 30rem;
                        overflow-y: auto;
                        font-size: 0.975rem;
                        line-height: 1.65;
                        color: rgb(var(--gray-800));
                    }
                    .dark .ticket-message__body { color: rgb(var(--gray-200)); }
                    .ticket-message__signoff {
                        display: flex;
                        justify-content: flex-end;
                        margin-top: 1.25rem;
                    }
                    .ticket-message__signoff-card {
                        display: inline-flex;
                        flex-direction: column;
                        align-items: flex-end;
                        gap: 0.0625rem;
                        max-width: 100%;
                        padding: 0.5rem 0.75rem;
                        border: 1px solid rgb(var(--gray-300));
                        border-radius: 0.625rem;
                        background-color: rgb(var(--gray-100));
                        text-align: right;
                    }
                    .dark .ticket-message__signoff-card {
                        border-color: rgb(var(--gray-700));
                        background-color: rgb(var(--gray-800));
                    }
                    .ticket-message__signoff-name {
                        font-size: 0.8125rem;
                        font-weight: 600;
                        color: rgb(var(--gray-800));
                    }
                    .dark .ticket-message__signoff-name { color: rgb(var(--gray-100)); }
                    .ticket-message__signoff-email {
                        font-size: 0.75rem;
                        color: rgb(var(--gray-500));
                    }

                    /* Conversation entries reuse the message-card look. */
                    .ticket-comment__time {
                        margin-left: auto;
                        font-weight: 500;
                        letter-spacing: normal;
                        text-transform: none;
                        color: rgb(var(--gray-400));
                    }
                    .ticket-comment__notified {
                        margin-top: 0.75rem;
                        font-size: 0.75rem;
                        color: rgb(var(--gray-500));
                    }
                    .ticket-comment--internal {
                        background-color: rgb(var(--warning-50));
                        border-color: rgb(var(--warning-200));
                    }
                    .dark .ticket-comment--internal {
                        background-color: rgba(var(--warning-400), 0.08);
                        border-color: rgba(var(--warning-400), 0.25);
                    }
                    .ticket-comment--original {
                        border-left: 3px solid rgb(var(--gray-300));
                    }
                    .dark .ticket-comment--original {
                        border-left-color: rgb(var(--gray-600));
                    }

                    .ticket-att {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 0.5rem;
                        margin-top: 0.875rem;
                    }
                    .ticket-att__thumb {
                        display: block;
                        width: 5rem;
                        height: 5rem;
                        overflow: hidden;
                        border-radius: 0.5rem;
                        border: 1px solid rgb(var(--gray-200));
                        background-color: rgb(var(--gray-100));
                    }
                    .ticket-att__thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
                    .ticket-att__file {
                        display: inline-flex;
                        align-items: center;
                        gap: 0.5rem;
                        max-width: 16rem;
                        padding: 0.375rem 0.625rem;
                        border: 1px solid rgb(var(--gray-200));
                        border-radius: 0.5rem;
                        background-color: rgb(var(--gray-50));
                        font-size: 0.8125rem;
                        color: rgb(var(--gray-700));
                        text-decoration: none;
                    }
                    .ticket-att__file-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                    .ticket-att__file-size { flex-shrink: 0; font-size: 0.6875rem; color: rgb(var(--gray-400)); }
                    .dark .ticket-att__thumb { border-color: rgb(var(--gray-700)); background-color: rgb(var(--gray-800)); }
                    .dark .ticket-att__file { border-color: rgb(var(--gray-700)); background-color: rgb(var(--gray-800)); color: rgb(var(--gray-200)); }

                    /* Drop Filament's per-row card chrome + its nested cell
                       padding, but keep a comfortable gutter around the stack so
                       the cards don't run into the panel edge. */
                    .fi-ta-content-grid { padding: 1rem !important; gap: 0.75rem !important; }
                    .fi-ta-content-grid .fi-ta-record {
                        background-color: transparent !important;
                        box-shadow: none !important;
                    }
                    .fi-ta-content-grid .fi-ta-record .flex-col.py-4 { padding-block: 0 !important; row-gap: 0.5rem !important; }
                    /* Strip Filament's cell inset, but never the card's own padding
                       (the .col-* wrapper also carries a .flex-1 class). */
                    .fi-ta-content-grid .fi-ta-record .flex-1 > *:not(.ticket-message) { padding-inline: 0 !important; }
                </style>
                HTML,
                scopes: EditTicket::class,
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.navigation-customizer', PanelNavigation::customizerViewData())->render(),
            )
            ->topNavigation()
            ->navigation(fn (NavigationBuilder $builder): NavigationBuilder => $builder->items(PanelNavigation::visibleItems()))
            ->authenticatedRoutes(function (): void {
                Route::post('/navigation/toggle', NavigationController::class)->name('navigation.toggle');
            })
            ->profile()
            ->colors([
                'primary' => '#052a44',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                TicketStatsOverview::class,
                TicketsByStatusChart::class,
                NeedsAttentionTicketsWidget::class,
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
                SecurityHeaders::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureUserIsAdmin::class,
            ]);
    }
}
