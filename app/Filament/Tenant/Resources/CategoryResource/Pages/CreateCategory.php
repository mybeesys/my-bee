<?php

namespace App\Filament\Tenant\Resources\CategoryResource\Pages;

use App\Filament\Tenant\Resources\CategoryResource;
use App\Services\CategoryService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Cache;

class CreateCategory extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;

    protected static string $resource = CategoryResource::class;

    protected function afterCreate(): void
    {
        $this->record->load('children', 'allChildren', 'parent');

        $service = new CategoryService();

        if($this->record->parent)
        {
//            $data = $service->subOf($this->record->parent->slug);
//            $data = $data->add($this->record)->sortBy('order');
//            Cache::put("sub-of-".$this->record->parent->slug, $data);
            Filament::notify('info', "Cache of ({$this->record->parent->name}) cleared.");
            Cache::clear("sub-of-".$this->record->parent->slug);
        }
    }
}
