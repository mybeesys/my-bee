<?php

namespace App\Filament\Admin\Resources\AppVersionResource\Pages;

use App\Filament\Admin\Resources\AppVersionResource;
use Filament\Actions\CreateAction;
use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Cache;

class ListAppVersions extends ListRecords
{
    protected static string $resource = AppVersionResource::class;

    protected function getTableBulkActions(): array
    {
        return [];
    }


    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            \Filament\Actions\Action::make('clear_latest_version_cache')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->action(function () {
                    Cache::forget('app-latest-version');
                    fns()->sendSuccess('Cached latest version cleared');
                })
        ];
    }

    protected function getActions(): array
    {
        return array_merge(
            parent::getActions(),
            [
                Action::make('clear_latest_version_cache')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        Cache::forget('app-latest-version');
                        $this->notify('success', 'Cached latest version cleared');
                    })
            ]);
    }
}
