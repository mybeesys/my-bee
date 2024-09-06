<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;

class Store extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.tenant.pages.store';

    protected static bool $shouldRegisterNavigation = false;

}
