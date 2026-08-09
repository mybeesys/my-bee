<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class ChooseRegistrationPlan extends Page
{
    protected static ?string $slug = 'join';

    protected static string $layout = 'filament.tenant.layout.login';

    protected static string $view = 'filament.tenant.pages.choose-registration-plan';

    protected static bool $shouldRegisterNavigation = false;

    protected static string | array $withoutRouteMiddleware = [
        Authenticate::class,
    ];

    public static function registerRoutes(Panel $panel): void
    {
        // Route is registered at the panel root via TenantPanelProvider::routes().
    }

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null): string
    {
        $panel = $panel ? Filament::getPanel($panel) : Filament::getCurrentPanel();

        return $panel->route(static::getRelativeRouteName(), $parameters, $isAbsolute);
    }

    public function getTitle(): string|Htmlable
    {
        return __('fields.registration_choose_plan_title');
    }

    public function getHeading(): string|Htmlable
    {
        return __('fields.registration_choose_plan_title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('fields.registration_choose_plan_subheading');
    }
}
