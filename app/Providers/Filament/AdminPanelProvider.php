<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Resources\ClientResource\Widgets\LatestClients;
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
            ->brandLogo(asset("logo.jpg"))
            ->brandLogoHeight('2.8rem')
            ->globalSearch(false)
            ->databaseTransactions()
            ->databaseNotifications()
            ->unsavedChangesAlerts()
            ->spa()
            ->sidebarCollapsibleOnDesktop()
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
            ->topNavigation()
            ->default()
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
//            ->discoverResources(in: app_path('Filament/Tenant/Resources'), for: 'App\\Filament\\Tenant\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
//                Widgets\AccountWidget::class,
//                Widgets\FilamentInfoWidget::class,
//                ExpenseChart::class,
                LatestClients::class,
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
