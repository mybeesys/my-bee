<?php

namespace App\Filament\Tenant\Resources\WarehouseResource\Pages;

use App\Filament\Tenant\Pages\CustomSettings;
use App\Filament\Tenant\Resources\ProductResource\Widgets\PricingOverview;
use App\Filament\Tenant\Resources\WarehouseResource;
use App\Models\Warehouse;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\CreateAction as TableCreateAction;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ListWarehouses extends ListRecords
{
    protected static string $resource = WarehouseResource::class;

    public function mount(): void
    {
        parent::mount();

        if (! Warehouse::firstWhere('main', true)) {
            fns()->persist()->sendWarning(__('fields.no_default_inventory_found_alert'));
        }

        if (request()->query('create')) {
            $this->mountAction('create');
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PricingOverview::class,
        ];
    }

    protected function getActions(): array
    {
        return [
            Action::make('pulling_stock_priority')
                ->label(__('fields.pulling_stock_priority'))
                ->color('gray')
                ->fillForm(function () {
                    $data = [];

                    foreach (Warehouse::orderBy('pulling_stock_priority')->get() as $warehouse) {
                        $item[Str::uuid()->toString()] = [
                            'warehouse_id' => $warehouse->id,
                            'warehouse' => $warehouse->name,
                        ];
                        $data = array_merge($data, $item);
                    }

                    return [
                        'items' => $data,
                    ];
                })
                ->form(function () {
                    return [
                        Section::make()
                            ->schema([
                                Placeholder::make('order_hint')
                                    ->label('')
                                    ->content(function () {
                                        $msg = __('fields.drag_and_drop_to_reorder');

                                        return new HtmlString("<strong style='color: #ff5028'>$msg</strong>");
                                    }),
                                TableRepeater::make('items')
                                    ->label(__('fields.warehouses'))
                                    ->headers([
                                        Header::make('warehouse_id'),
                                    ])
                                    ->renderHeader(false)
                                    ->deletable(false)
                                    ->addable(false)
                                    ->orderColumn()
                                    ->schema([
                                        Hidden::make('warehouse_id'),
                                        TextInput::make('warehouse')
                                            ->readOnly()
                                            ->label(__('fields.warehouse')),
                                    ])
                                    ->columns(2),
                            ]),
                    ];
                })
                ->modalSubmitActionLabel(__('fields.save'))
                ->action(function (array $data) {
                    foreach ($data['items'] ?? [] as $index => $item) {
                        Warehouse::find($item['warehouse_id'])->update(['pulling_stock_priority' => $index + 1]);
                    }
                    fns()->saved();
                }),

            CreateAction::make(),

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
            ->label(__('fields.add_warehouse'))
            ->form(fn (Form $form): Form => $this->form($form->columns(1)))
            ->slideOver()
            ->modalWidth(MaxWidth::TwoExtraLarge)
            ->createAnother(false);
    }

    public function getBreadcrumbs(): array
    {
        return array_merge([
            CustomSettings::getUrl() => __('fields.settings'),
        ], parent::getBreadcrumbs());
    }
}
