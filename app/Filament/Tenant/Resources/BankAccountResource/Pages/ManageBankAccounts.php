<?php

namespace App\Filament\Tenant\Resources\BankAccountResource\Pages;

use App\Filament\Tenant\Pages\CustomSettings;
use App\Filament\Tenant\Resources\BankAccountResource;
use App\Models\Acc4;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\ActionSize;

class ManageBankAccounts extends ManageRecords
{
    protected static string $resource = BankAccountResource::class;

    protected function getActions(): array
    {
        return [
            CreateAction::make(),
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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['acc3_code'] = '1227';
        $data['code'] = Acc4::nextCodeForAcc3('1227');
        $data['editable'] = true;
        $data['deletable'] = true;

        return $data;
    }
}
