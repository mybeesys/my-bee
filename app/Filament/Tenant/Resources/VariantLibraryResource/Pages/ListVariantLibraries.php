<?php

namespace App\Filament\Tenant\Resources\VariantLibraryResource\Pages;

use App\Filament\Tenant\Resources\VariantLibraryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListVariantLibraries extends ListRecords
{
    protected static string $resource = VariantLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        return "تساعدك مكتبة الخيارات في انشاء الخيارات واعادة استخدامها بين المنتجات";
    }
}
