<?php

namespace App\Filament\Tenant\Resources\Acc4Resource\Pages;

use App\Filament\Tenant\Pages\CustomSettings;
use App\Filament\Tenant\Resources\Acc4Resource;
use App\Models\Acc4;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\ActionSize;

class ManageAcc4s extends ManageRecords
{
    protected static string $resource = Acc4Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['acc3_code'] = '1217';
                    $data['code'] = Acc4::nextCodeForAcc3('1217');
                    $data['editable'] = true;
                    $data['deletable'] = true;

                    return $data;
                }),
            Action::make('back')
                ->icon('heroicon-m-arrow-uturn-left')
                ->size(ActionSize::Large)
                ->url(CustomSettings::getUrl())
                ->iconButton(),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return array_merge([
            CustomSettings::getUrl() => __('fields.settings'),
        ], parent::getBreadcrumbs());
    }

    protected function getTableBulkActions(): array
    {
        return [];
    }
}
