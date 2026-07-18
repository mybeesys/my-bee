<?php

namespace App\Filament\Tenant\Resources\SupplierResource\Pages;

use App\Filament\Tenant\Resources\SupplierResource;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = SupplierResource::mutateEditFormData($data, $this->record);

        return parent::mutateFormDataBeforeFill($data);
    }
}
