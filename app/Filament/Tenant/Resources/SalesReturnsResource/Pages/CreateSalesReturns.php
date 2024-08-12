<?php

namespace App\Filament\Tenant\Resources\SalesReturnsResource\Pages;

use App\Filament\Tenant\Resources\SalesReturnsResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesReturns extends CreateRecord
{
    protected static string $resource = SalesReturnsResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
