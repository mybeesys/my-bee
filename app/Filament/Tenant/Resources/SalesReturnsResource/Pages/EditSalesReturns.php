<?php

namespace App\Filament\Tenant\Resources\SalesReturnsResource\Pages;

use App\Filament\Tenant\Resources\SalesReturnsResource;
use Filament\Resources\Pages\EditRecord;

class EditSalesReturns extends EditRecord
{
    protected static string $resource = SalesReturnsResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['return_mode'] = $this->record->isCustomerReturn() ? 'customer' : 'invoice';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
