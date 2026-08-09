<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ChooseSubscription extends Page
{
    protected static ?string $slug = 'choose-plan';

    protected static string $layout = 'filament.tenant.layout.login';

    protected static string $view = 'filament.tenant.pages.choose-subscription';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        abort_unless(Filament::auth()->check(), 403);
        abort_unless(Filament::auth()->user()?->hasRole(User::ROLE_CLIENT), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return __('fields.choose_subscription_title');
    }

    public function getHeading(): string|Htmlable
    {
        return __('fields.choose_subscription_title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('fields.choose_subscription_subheading');
    }
}
