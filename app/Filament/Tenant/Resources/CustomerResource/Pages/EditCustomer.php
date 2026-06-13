<?php

namespace App\Filament\Tenant\Resources\CustomerResource\Pages;

use App\Filament\Tenant\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = CustomerResource::mutateEditFormData($data, $this->record);

        return parent::mutateFormDataBeforeFill($data);
    }
}
