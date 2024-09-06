<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;

class Subscription extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.tenant.pages.subscription';

    protected static bool $shouldRegisterNavigation = false;
}
