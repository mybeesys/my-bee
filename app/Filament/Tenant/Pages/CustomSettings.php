<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;

class CustomSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.tenant.pages.custom-settings';

    protected static ?string $slug = "/settings/v2";

    protected static ?int $navigationSort = 999;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return __('fields.settings');
    }

    public function getTitle(): string | Htmlable
    {
        return __('fields.settings');
    }

    public function getHeading(): string|Htmlable
    {
        return __('fields.settings');
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }
}
