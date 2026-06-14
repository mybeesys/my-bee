<?php

namespace App\Providers\Filament;

use App\Filament\Tenant\Pages\Dashboard;
use App\Filament\Tenant\Pages\EditTenantProfile;
use App\Filament\Tenant\Pages\LoginTenant;
use App\Filament\Tenant\Pages\RegisterClient;
use App\Filament\Tenant\Pages\RegisterTenant;
use App\Filament\Tenant\Pages\TenantUserProfile;
use App\Http\Middleware\ApplyTenantScopes;
use App\Http\Middleware\FilamentPanelsUserSettings;
use App\Models\Tenant;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
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
use Rupadana\FilamentAnnounce\FilamentAnnouncePlugin;

class TenantPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $domain = "client.mybeesystem.com";
        if (config('app.env') === "local")
            $domain = "client.my-bee.test";


        return $panel
            ->id('tenant')
//            ->tenantBillingProvider(new SparkBillingProvider())
//            ->requiresTenantSubscription()
            ->path('')
            ->domain($domain)
            ->viteTheme('resources/css/filament/tenant/theme.css')
            ->favicon(public_asset_if_exists('logo.jpg'))
            ->brandLogo(public_asset_if_exists('logo.jpg'))
            ->brandLogoHeight('2rem')
            ->sidebarWidth('16rem')
            ->collapsedSidebarWidth('4rem')
            ->databaseTransactions()
            ->databaseNotifications()
            ->unsavedChangesAlerts()
            ->spa()
            ->brandName(fn() => filament()->getTenant()?->name)
            ->globalSearch(false)
//            ->registration(RegisterClient::class)
//            ->passwordReset()
            ->tenant(Tenant::class, slugAttribute: 'slug')
            ->tenantRegistration(RegisterTenant::class)
            ->tenantProfile(EditTenantProfile::class)
            ->tenantMiddleware([
                ApplyTenantScopes::class,
            ], isPersistent: true)
            ->sidebarCollapsibleOnDesktop()
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): \Illuminate\Contracts\View\View => view('filament.tenant.components.collapsed-topbar-logo'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): \Illuminate\Contracts\View\View => view('filament.tenant.components.sidebar-accordion'),
            )
//            ->tenantMenuItems([
//                'test' => MenuItem::make()->label('Register new team')->url(null)
//                // ...
//            ])
            ->font('Cairo', provider: GoogleFontProvider::class)
            ->login(LoginTenant::class)
            ->colors([
                'primary' => Color::Yellow,
            ])
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label(fn(): string => filament()->auth()->user()->full_name)
                    ->url(function () {
                        if (filament()->getTenant()) {
                            return TenantUserProfile::getUrl();
                        }
                    })
                    ->icon('heroicon-o-user-circle'),
            ])
            ->discoverResources(in: app_path('Filament/Tenant/Resources'), for: 'App\\Filament\\Tenant\\Resources')
            ->discoverPages(in: app_path('Filament/Tenant/Pages'), for: 'App\\Filament\\Tenant\\Pages')
            ->pages([
                Dashboard::class
            ])
            ->discoverWidgets(in: app_path('Filament/Tenant/Widgets'), for: 'App\\Filament\\Tenant\\Widgets')
            ->widgets([
//                Widgets\AccountWidget::class,
//                Widgets\FilamentInfoWidget::class,
            ])
            ->navigationGroups([

                NavigationGroup::make()
                    ->label(fn(): string => __('fields.navigation_group_favourites'))
                    ->collapsed(),

                NavigationGroup::make()
                    ->label(fn(): string => __('fields.warehouses'))
                    ->collapsed(),

                NavigationGroup::make()
                    ->label(fn(): string => __('fields.invoices'))
                    ->collapsed(),

                NavigationGroup::make()
                    ->label(fn(): string => __('fields.nav_group_purchases'))
                    ->collapsed(),

                NavigationGroup::make()
                    ->label(fn(): string => __('fields.nav_group_clients_and_suppliers'))
                    ->collapsed(),

                NavigationGroup::make()
                    ->label(fn(): string => __('fields.nav_group_transactions'))
                    ->collapsed(),

                NavigationGroup::make()
                    ->label(fn(): string => __('fields.nav_group_reports'))
                    ->collapsed(),

                NavigationGroup::make()
                    ->label(fn(): string => __('fields.nav_group_store'))
                    ->collapsed(),

                NavigationGroup::make()
                    ->label(fn(): string => __('fields.settings'))
                    ->collapsed(),

            ])
            ->navigationItems([

//                NavigationItem::make()
//                    ->label(fn(): string => __('fields.clients')),

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
                FilamentPanelsUserSettings::class
            ])
            ->authMiddleware([
                Authenticate::class,
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

                FilamentAnnouncePlugin::make()
                    ->pollingInterval('30s') // optional, by default it is set to null
                    ->defaultColor(Color::Blue)
            ]);
    }
}
