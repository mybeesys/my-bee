<?php

namespace App\Filament\Tenant\Resources\JournalEntryResource\Pages;

use App\Filament\Tenant\Resources\JournalEntryResource;
use Filament\Actions\CreateAction;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListJournalEntries extends ListRecords
{
    protected static string $resource = JournalEntryResource::class;

    protected function getActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

//    protected function getTableContentFooter(): ?View
//    {
//        return view('custom-footer.list-journal-footer'); // path to custom view in resources/views
//    }
}
