<?php

namespace App\Filament\Tenant\Resources\TaxProfileResource\Pages;

use App\Filament\Tenant\Pages\CustomSettings;
use App\Filament\Tenant\Resources\TaxProfileResource;
use App\Models\TaxProfile;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Form;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\CreateAction as TableCreateAction;
use Filament\Tables\Actions\EditAction as TableEditAction;

class ListTaxProfiles extends ListRecords
{
    protected static string $resource = TaxProfileResource::class;

    public function mount(): void
    {
        parent::mount();

        if (request()->query('create')) {
            $this->mountAction('create');
        }

        if ($editId = request()->query('edit')) {
            $taxProfile = TaxProfile::query()->find($editId);

            if ($taxProfile && TaxProfileResource::canEdit($taxProfile)) {
                $this->mountTableAction('edit', $taxProfile);
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Action::make('back')
                ->icon('heroicon-m-arrow-uturn-left')
                ->size(ActionSize::Large)
                ->url(CustomSettings::getUrl())
                ->iconButton(),
        ];
    }

    protected function configureCreateAction(CreateAction | TableCreateAction $action): void
    {
        parent::configureCreateAction($action);

        if (! $action instanceof CreateAction) {
            return;
        }

        $action
            ->form(fn (Form $form): Form => $this->form($form->columns(1)))
            ->slideOver()
            ->modalWidth(MaxWidth::FourExtraLarge)
            ->createAnother(false);
    }

    protected function configureEditAction(TableEditAction $action): void
    {
        parent::configureEditAction($action);

        $action
            ->form(fn (Form $form): Form => $this->form($form->columns(1)))
            ->slideOver()
            ->modalWidth(MaxWidth::FourExtraLarge);
    }

    public function getBreadcrumbs(): array
    {
        return array_merge([
            CustomSettings::getUrl() => __('fields.settings'),
        ], parent::getBreadcrumbs());
    }
}
