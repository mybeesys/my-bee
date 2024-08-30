<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;

class CustomSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog';

    protected static string $view = 'filament.tenant.pages.custom-settings';
}
