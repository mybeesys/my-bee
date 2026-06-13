<?php

namespace App\Filament\Tenant\Resources\CategoryResource\Pages;

use App\Filament\Tenant\Resources\CategoryResource;
use App\Services\CategoryService;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;

class EditCategory extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = CategoryResource::class;

    protected function afterSave()
    {

        $this->record->load('children', 'allChildren', 'parent');

        $service = new CategoryService();

        if ($this->record->parent) {
//            $data = $service->subOf($this->record->parent->slug);
//            $data = $data->add($this->record)->sortBy('order');
//            Cache::put("sub-of-".$this->record->parent->slug, $data);//            Cache::put("sub-of-".$this->record->parent->slug, $data);
            Cache::clear("sub-of-" . $this->record->parent->slug);
        } else { //main
            $service->clearMainCategories();
        }
    }

    protected function afterDelete()
    {
        $this->record->load('children', 'allChildren', 'parent');

        $service = new CategoryService();

        if ($this->record->parent) {
            Cache::clear("sub-of-" . $this->record->parent->slug);
//                Filament::notify('info', "Cache of ({$this->record->parent->name}) cleared.");
        } else { //main
            $service->clearMainCategories();
        }
    }


    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }
}
