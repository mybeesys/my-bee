<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;

class InvConfig extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.tenant.pages.inv-config';

    protected static bool $shouldRegisterNavigation = false;

}
