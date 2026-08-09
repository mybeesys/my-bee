<?php

namespace App\Providers\Filament;

use App\Filament\Tenant\Pages\ChooseRegistrationPlan;
use App\Filament\Tenant\Pages\CustomSettings;
use App\Filament\Tenant\Pages\Dashboard;
use App\Filament\Tenant\Pages\EditTenantProfile;
use App\Filament\Tenant\Pages\LoginTenant;
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
            ->favicon(system_logo_icon_url())
            ->brandLogo(system_brand_logo_url())
            ->brandLogoHeight('4.75rem')
            ->sidebarWidth('16rem')
            ->collapsedSidebarWidth('4rem')
            ->databaseTransactions()
            ->databaseNotifications()
            ->databaseNotificationsPolling('15s')
            ->unsavedChangesAlerts()
            ->spa()
            ->brandName(fn() => filament()->getTenant()?->name)
            ->globalSearch(false)
//            ->passwordReset()
            ->tenant(Tenant::class, slugAttribute: 'slug')
            ->routes(fn (Panel $panel) => ChooseRegistrationPlan::routes($panel))
            ->tenantRegistration(RegisterTenant::class)
            ->tenantProfile(EditTenantProfile::class)
            ->tenantMiddleware([
                ApplyTenantScopes::class,
                \App\Http\Middleware\EnsureClientSubscriptionActive::class,
            ], isPersistent: true)
            ->sidebarCollapsibleOnDesktop()
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): \Illuminate\Contracts\View\View => view('filament.tenant.components.collapsed-topbar-logo'),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): \Illuminate\Contracts\View\View|string => filament()->auth()->check() && filled(filament()->getTenant())
                    ? view('filament.tenant.components.topbar-tenant-context')
                    : '',
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<style id="tenant-notification-badge-style">'
                    .'.fi-panel-tenant .tenant-database-notifications-trigger > span[aria-hidden="true"],'
                    .'.fi-panel-tenant .fi-topbar-database-notifications-btn .fi-icon-btn-badge-ctn .fi-badge'
                    .'{background-color:#dc2626!important;color:#fff!important;border:2px solid #fff!important;'
                    .'font-weight:700!important;box-shadow:0 2px 8px rgba(220,38,38,.5)!important;}'
                    .'</style>'
                    .'<style id="tenant-settings-footer-style">'
                    .'.fi-panel-tenant .fi-sidebar-nav-groups{display:flex;flex:1 1 auto;flex-direction:column;min-height:100%;}'
                    .'.fi-panel-tenant .fi-sidebar-group[data-settings-footer="1"]{margin-top:auto;padding-top:.5rem;border-top:1px solid rgb(229 231 235 / .5);}'
                    .'.dark .fi-panel-tenant .fi-sidebar-group[data-settings-footer="1"]{border-top-color:rgb(31 41 55 / .5);}'
                    .'.fi-panel-tenant .fi-sidebar-group[data-settings-footer="1"]>.fi-sidebar-group-button{display:none!important;}'
                    .'.fi-panel-tenant .fi-sidebar-group[data-settings-footer="1"]>.fi-sidebar-group-items{display:flex!important;}'
                    .'</style>',
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
                Dashboard::class,
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
                    ->label(fn(): string => __('fields.nav_group_sales'))
                    ->collapsed(),

                NavigationGroup::make()
                    ->label(fn(): string => __('fields.nav_group_purchases'))
                    ->collapsed(),

                NavigationGroup::make()
                    ->label(fn(): string => __('fields.nav_group_online_store'))
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
                    ->label(fn (): string => __('fields.settings'))
                    ->collapsible(false)
                    ->extraSidebarAttributes([
                        'data-settings-footer' => '1',
                    ]),

            ])
            ->navigationItems([
                NavigationItem::make('tenant-settings')
                    ->label(fn (): string => __('fields.settings'))
                    ->url(fn (): string => CustomSettings::getUrl())
                    ->icon('heroicon-o-cog-6-tooth')
                    ->group(fn (): string => __('fields.settings'))
                    ->sort(1)
                    ->visible(fn (): bool => filament()->auth()->check() && filled(filament()->getTenant()))
                    ->isActiveWhen(fn (): bool => request()->routeIs(CustomSettings::getRouteName())),
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
