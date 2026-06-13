<?php

namespace App\Filament\Tenant\Resources\TaxProfileResource\Pages;

use App\Filament\Tenant\Resources\TaxProfileResource;
use Filament\Resources\Pages\Page;

class RedirectTaxProfileCreate extends Page
{
    protected static string $resource = TaxProfileResource::class;

    protected static string $view = 'filament.tenant.pages.tax-profile-create-redirect';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        abort_unless(TaxProfileResource::canCreate(), 403);

        $this->redirect(TaxProfileResource::getUrl('index') . '?create=1');
    }
}
