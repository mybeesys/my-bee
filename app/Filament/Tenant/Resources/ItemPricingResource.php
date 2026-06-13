<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ItemPricingResource\Pages;
use App\Filament\Tenant\Resources\ItemPricingResource\RelationManagers;
use App\Filament\Tenant\Resources\ProductResource\Widgets\MissingPricingOverview;
use App\Filament\Tenant\Resources\ProductResource\Widgets\PricingOverview;
use App\Models\Acc4;
use App\Models\ItemPrice;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Services\PricingService;
use Filament\Forms;
use Filament\Forms\Components\View;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Facades\Filament;

class ItemPricingResource extends Resource
{
    protected static ?string $model = ItemPrice::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $slug = "products/pricing";

    protected static ?int $navigationSort = 4;

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationGroup(): ?string
    {
        return __('fields.products');
    }

    public static function getLabel(): ?string
    {
        return __('fields.item_price');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.items_prices');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()->schema([

                    hidden_tenant_id_field(),

                    Forms\Components\Hidden::make('currency_iso_code')
                        ->default(setting('main_currency', 'SAR')),

                    Forms\Components\Select::make('acc4_code')
                        ->label(__('fields.product'))
                        ->required()
                        ->live()
                        ->searchable()
                        ->options(Acc4::asOptions(Product::class))
                        ->afterStateUpdated(function (Forms\Set $set) {
                            $set('unit_id', null);

                            $set('last_pricing_date', "-");
                            $set('last_pricing', "-");

                            $set('last_stock_unit_cost', "-");
                        }),

                    Forms\Components\Select::make('unit_id')
                        ->label(__('fields.unit'))
                        ->required()
                        ->live()
                        ->options(function (Forms\Get $get) {
                            $product = Acc4::with(['item.prices', 'item.availableStocks', 'item.units.unit'])
                                ->find($get('acc4_code'))?->item;

                            if ($product) {
                                return $product->unitsAsOptions();
                            }

                            return [];
                        })
                        ->searchable()
//                        ->afterStateHydrated(function (Forms\Set $set, $record) {
//                            $set('last_pricing_date', "-");
//                            $set('last_pricing', "-");
//
//                            $set('last_stock_unit_cost', "-");
//
//                            if ($record) {
//                                $product = $record->acc4->item;
//
//                                $lastPriceDate = $product->lastPrice == null ? "-" : $product->lastPrice->date->format('d-m-Y');
//                                $lastPrice = $product->lastPrice == null ? "-" : number_format($product->lastPrice->price, 2);
//
//                                $lasStockUnitCost = $product->lastStock == null ? "-" : number_format($product->lastStock->unit_cost, 2);
//
//                                $set('last_pricing_date', $lastPriceDate);
//                                $set('last_pricing', $lastPrice);
//
//                                $set('last_stock_unit_cost', $lasStockUnitCost);
//
//                                if ($product->lastStock)
//                                    $set('profit', format_amount(profit_percent($product->lastPrice->price, $product->lastStock->unit_cost)));
//
//                            }
//                        })
                        ->afterStateUpdated(function (Forms\Set $set, $state, Forms\Get $get) {

                            $set('last_pricing_date', "-");
                            $set('last_pricing', "-");

//                            $set('last_stock_unit_cost', "-");

                            $set('unit_cost', null);
                            $set('price', null);

                            if ($state) {

                                $acc4_code = $get('acc4_code');

//
//                                $product = Product::with(['prices', 'units.unit', 'availableStocks', 'stocks'])
//                                    ->find($productUnit->product_id);

                                $product = Acc4::with(['item.prices', 'item.stocks', 'item.availableStocks', 'item.units.unit'])->find($acc4_code)->item;

                                $lastPrice = $product->prices->where('unit_id', $state)->last();
                                $lastStock = $product->stocks->where('unit_id', $state)->last();
                                $lastUnitCost = $lastPrice == null ? null : number_format($lastPrice->unit_cost, 2, '.', '');

                                $lastPriceDate = $lastPrice == null ? "-" : $lastPrice->date->format('d-m-Y');
                                $lastPriceValue = $lastPrice == null ? "-" : format_amount($lastPrice->price);

                                $lasStockUnitCost = $lastStock == null ? "-" : format_amount($lastStock->unit_cost);

                                $set('last_pricing_date', $lastPriceDate);
                                $set('last_pricing', $lastPriceValue);

                                $set('unit_cost', $lastUnitCost);

//                                $set('last_stock_unit_cost', $lasStockUnitCost);
                            }
                        }),

//                    Forms\Components\TextInput::make('last_stock_unit_cost')
//                        ->dehydrated(false)
//                        ->label(__('fields.last_stock_unit_cost'))
//                        ->helperText(function ($state) {
//                            return numbers_to_words($state);
//                        })
//                        ->mainCurrencySuffix()
//                        ->disabled(1),

                    Forms\Components\TextInput::make('last_pricing_date')
                        ->hiddenOn(['edit', 'view'])
                        ->dehydrated(false)
                        ->label(__('fields.last_pricing_date'))
                        ->disabled(1),

                    Forms\Components\TextInput::make('last_pricing')
                        ->hiddenOn(['edit', 'view'])
                        ->dehydrated(false)
                        ->label(__('fields.last_pricing'))
                        ->helperText(function ($state) {
                            return numbers_to_words($state);
                        })
                        ->mainCurrencySuffix()
                        ->disabled(1),

                    Forms\Components\TextInput::make('unit_cost')
                        ->label(__('fields.purchase_price'))
                        ->live(true)
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(PHP_INT_MAX)
                        ->helperText(function ($state) {
                            return numbers_to_words($state);
                        })->mainCurrencySuffix(),

                    Forms\Components\TextInput::make('price')
                        ->label(__('fields.sale_price'))
                        ->live(true)
                        ->extraInputAttributes(['name' => 'itemPricingNewPrice'])
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(PHP_INT_MAX)
                        ->gt('unit_cost')
                        ->different('last_pricing')
                        ->helperText(function ($state) {
                            return numbers_to_words($state);
                        })
                        ->afterStateUpdated(function (Forms\Components\TextInput $component, $state, Forms\Get $get, Forms\Set $set) {
                            $set('profit', '-');

                            if ($state and is_number($state) and $state > 0) {

//                                $component->helperText(numbers_to_words($state));

                                $unit_cost = $get('unit_cost');
                                if ($unit_cost) {
                                    $unit_cost = \Str::replace(',', '', $unit_cost);

                                    $profit = profit_percent($state, $unit_cost);

                                    if (is_number($profit)) {
                                        $set('profit', number_format($profit, 2, '.', ''));
                                    } else {
                                        $set('profit', "-");
                                    }
                                }
                            }
                        })
                        ->mainCurrencySuffix(),

                    Forms\Components\TextInput::make('profit')
                        ->dehydrated(false)
                        ->live()
                        ->maxValue(10000)
                        ->suffix('%')
                        ->prefixIcon('heroicon-o-calculator')
                        ->disabled(function (Forms\Get $get) {
                            return $get('last_stock_unit_cost') == null or $get('last_stock_unit_cost') == "-";
                        })
                        ->label(__('fields.profit_percent')),

                    Forms\Components\Hidden::make('date')->default(now())
                ])->columns(3),

                View::make('components.loading'),

            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('acc4.item.barcode')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('acc4', function (Builder $q2) use ($search) {
                            return $q2->whereHasMorph('item', [Product::class], function (Builder $q3) use ($search) {
                                return $q3->where('barcode', 'like', "%{$search}%")
                                    ->where('tenant_id', \filament()->getTenant()->id);
                            });
                        });
                    })
                    ->label(__('fields.barcode')),

                Tables\Columns\TextColumn::make('acc4.item.name')
                    ->searchable()
                    ->label(__('fields.name')),

                Tables\Columns\TextColumn::make('price')
                    ->label(__('fields.price'))
                    ->searchable()
                    ->description(fn($record) => $record->unit->name)
                    ->tooltip(function ($record) {
                        return numbers_to_words($record->price);
                    })
                    ->getStateUsing(function ($record) {
                        return $record->currency_iso_code . " " . format_amount($record->price);
                    }),


//                Tables\Columns\TextColumn::make('exchange_rate')
//                    ->searchable()
//                    ->label(__('fields.exchange_rate')),

                Tables\Columns\TextColumn::make('profit')
                    ->label(__('fields.profit_percent'))
                    ->extraAttributes(function ($record) {
                        if ($record->acc4->item->lastStock)
                            return ['class' => 'text-success-700'];

                        return ['class' => 'text-danger-700'];
                    })
                    ->getStateUsing(function ($record) {
                        if ($record->price and $record->unit_cost)
                            return number_format(profit_percent($record->price, $record->unit_cost), 2) ."%";

                        return "No stock available to calculate";
                    }),

                Tables\Columns\TextColumn::make('date')
                    ->label(__('fields.date'))
                    ->sortable(),

            ])
            ->filters([

                Tables\Filters\Filter::make('product_id')
                    ->label(__('fields.product'))
                    ->form([
                        Forms\Components\Select::make('product_id')
                            ->label(__('fields.product'))
                            ->searchable()
                            ->options(Product::has('lastPrice')->pluck('name', 'id')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['product_id'],
                                function (Builder $query, $product_id) {
                                    return $query->whereHas('acc4', function (Builder $q2) use ($product_id) {
                                        return $q2->whereHasMorph('item', [Product::class], function (Builder $q3) use ($product_id) {
                                            return $q3->where('id', $product_id);
                                        });
                                    });
                                },
                            );
                    }),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('fields.created_from')),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('fields.created_until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })

            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()->using(function (array $data, $record) {
                        unset($data['profit']);
                        $record->update($data);
                    }),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([

            ])->deferLoading();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['unit', 'acc4.item.prices', 'acc4.item.lastPrice', 'acc4.item.lastStock'])->latest();
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageItemPricings::route('/'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            MissingPricingOverview::class
        ];
    }

    public static function canViewAny(): bool
    {
        return parent::canViewAny(); // TODO: Change the autogenerated stub
    }

    protected static bool $shouldCheckPolicyExistence = true;
}
