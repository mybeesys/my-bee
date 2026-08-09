<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Backups;
use App\Filament\Pages\Profile;
use App\Http\Middleware\FilamentPanelsUserSettings;
use App\Livewire\ExpenseChart;
use BezhanSalleh\FilamentLanguageSwitch\FilamentLanguageSwitchPlugin;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\SpatieLaravelTranslatablePlugin;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{

    public function panel(Panel $panel): Panel
    {
        $domain = "admin.mybeesystem.com";
        if (config('app.env') === "local")
            $domain = "admin.my-bee.test";

        return $panel
            ->id('admin')
            ->path('')
            ->domain($domain)
            ->brandLogo(system_brand_logo_url())
            ->favicon(system_logo_icon_url())
            ->brandLogoHeight('4.75rem')
            ->sidebarWidth('16rem')
            ->collapsedSidebarWidth('4rem')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->globalSearch(false)
            ->databaseTransactions()
            ->databaseNotifications()
            ->unsavedChangesAlerts()
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): \Illuminate\Contracts\View\View => view('filament.tenant.components.collapsed-topbar-logo'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<style id="admin-notification-badge-style">'
                    .'.fi-panel-admin .fi-topbar-database-notifications-btn .fi-icon-btn-badge-ctn .fi-badge'
                    .'{background-color:#dc2626!important;color:#fff!important;border:2px solid #fff!important;'
                    .'font-weight:700!important;box-shadow:0 2px 8px rgba(220,38,38,.5)!important;}'
                    .'</style>',
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): \Illuminate\Contracts\View\View => view('filament.admin.components.sidebar-accordion'),
            )
            ->font('Cairo', provider: GoogleFontProvider::class)
//            ->font('Noto Kufi Arabic', provider: GoogleFontProvider::class)
//            ->navigationGroups([
//                NavigationGroup::make()
//                    ->label(fn(): string => __('fields.clients'))
//                    ->icon('heroicon-o-user-group')
//                    ->collapsed(),
//                NavigationGroup::make()
//                    ->label(fn(): string => __('fields.subscription_plans'))
//                    ->icon('heroicon-o-briefcase')
//                    ->collapsed(),
//
//                NavigationGroup::make()
//                    ->label(fn(): string => __('fields.roles_permissions'))
//                    ->icon('heroicon-o-shield-check')
//                    ->collapsed(),
//
//                NavigationGroup::make()
//                    ->label(fn(): string => __('fields.settings'))
//                    ->icon('heroicon-o-cog-8-tooth')
//                    ->collapsed(),
//
//            ])
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label(fn(): string => filament()->auth()->user()->full_name)
                    ->url(fn(): string => \App\Filament\Admin\Pages\Profile::getUrl())
                    ->icon('heroicon-o-user-circle'),
            ])
            ->default()
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
//            ->discoverResources(in: app_path('Filament/Tenant/Resources'), for: 'App\\Filament\\Tenant\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
            ])
            ->middleware([
//                \Stancl\Tenant\Middleware\InitializeTenancyBySubdomain::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                FilamentPanelsUserSettings::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])->tenantMiddleware([
            ])->plugins([
                SpatieLaravelTranslatablePlugin::make(),
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make()
                    ->gridColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 3
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 4,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                    ]),

                FilamentSpatieLaravelBackupPlugin::make()
                    ->usingQueue('default')
                    ->usingPage(Backups::class),

            ]);
    }
}
