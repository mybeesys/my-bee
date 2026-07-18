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

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['acc3_code'] = '1227';
                    $data['code'] = Acc4::nextCodeForAcc3('1227');
                    $data['editable'] = true;
                    $data['deletable'] = true;

                    $meta = $data['meta'] ?? [];
                    $shouldBeDefault = (bool) ($meta['is_default'] ?? false)
                        || ! Acc4::query()->bankAccounts()->exists();

                    $meta['is_default'] = $shouldBeDefault;
                    $data['meta'] = $meta;

                    return $data;
                })
                ->after(function (Acc4 $record, array $data): void {
                    if ($data['meta']['is_default'] ?? false) {
                        $record->markAsDefaultBankAccount();
                    }
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
