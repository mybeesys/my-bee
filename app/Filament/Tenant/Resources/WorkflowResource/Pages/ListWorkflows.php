<?php

namespace App\Filament\Tenant\Resources\WorkflowResource\Pages;

use App\Filament\Tenant\Resources\WorkflowResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWorkflows extends ListRecords
{
    protected static string $resource = WorkflowResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
