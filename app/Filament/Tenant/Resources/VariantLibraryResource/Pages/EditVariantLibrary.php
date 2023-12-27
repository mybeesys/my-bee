<?php

namespace App\Filament\Tenant\Resources\VariantLibraryResource\Pages;

use App\Filament\Tenant\Resources\VariantLibraryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVariantLibrary extends EditRecord
{
    protected static string $resource = VariantLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
