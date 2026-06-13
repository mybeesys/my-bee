<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class Appearance extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.tenant.pages.appearance';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string | Htmlable
    {
        return __('fields.appearance');
    }

    public static function getNavigationLabel(): string
    {
        return __('fields.appearance');
    }
}
