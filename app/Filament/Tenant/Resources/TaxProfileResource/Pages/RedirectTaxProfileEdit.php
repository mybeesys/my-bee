<?php

namespace App\Filament\Tenant\Resources\TaxProfileResource\Pages;

use App\Filament\Tenant\Resources\TaxProfileResource;
use App\Models\TaxProfile;
use Filament\Resources\Pages\Page;

class RedirectTaxProfileEdit extends Page
{
    protected static string $resource = TaxProfileResource::class;

    protected static string $view = 'filament.tenant.pages.tax-profile-create-redirect';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(int | string $record): void
    {
        $taxProfile = TaxProfile::query()->find($record);

        abort_unless($taxProfile && TaxProfileResource::canEdit($taxProfile), 403);

        $this->redirect(TaxProfileResource::getTaxProfileEditUrl($taxProfile));
    }
}
