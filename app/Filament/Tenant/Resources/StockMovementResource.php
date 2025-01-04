<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\StockMovementResource\Pages;
use App\Filament\Tenant\Resources\StockMovementResource\RelationManagers;
use App\Models\ItemStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Components\View;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-circle';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = "warehouses/stock-movement";

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationGroup(): ?string
    {
        return __('fields.warehouses');
    }

    public static function getLabel(): ?string
    {
        return __('fields.stock_movement');
    }


    public static function getPluralLabel(): ?string
    {
        return __('fields.stock_movement');
    }

    public static function getNavigationBadge(): ?string
    {
        return StockMovement::count();
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema(static::getFormSchema(Forms\Components\Card::class))
            ->columns([
                'sm' => 1,
                'lg' => null,
            ]);
    }

    public static function getFormSchema(string $layout = Forms\Components\Grid::class): array
    {
        return [
            Forms\Components\Group::make()
                ->schema([

                    $layout::make()
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->reactive()
                                ->label(__('fields.product'))
                                ->searchable()
                                ->options(Product::asOptions(true, false, 'id'))
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    if (!$state) {
                                        $set('max_qty', null);
                                        $set('qty', null);
                                        $set('target_warehouse_id', null);
                                        $set('destination_warehouse_id', null);
                                    }
                                })
                                ->required(),

                            Forms\Components\Hidden::make('max_qty'),
                            Forms\Components\Hidden::make('target_warehouse_pre_movement_qty'),
                            Forms\Components\Hidden::make('destination_warehouse_pre_movement_qty'),

                            Forms\Components\Select::make('target_warehouse_id')
                                ->label(__('fields.from_warehouse'))
                                ->reactive()
                                ->disabled(fn(Forms\Get $get) => $get('product_id') === null)
                                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                    $product_id = $get('product_id');

                                    $ava = ItemStock::whereItemType(Product::class)
                                        ->whereItemId($product_id)->whereWarehouseId($state)->get()->sum(function ($i) {
                                            return $i->available;
                                        });

                                    $set('target_warehouse_pre_movement_qty', $ava);

                                    $set('max_qty', $ava);
                                    $set('qty', $ava);


                                })
                                ->options(function (Forms\Get $get) {
                                    $product_id = $get('product_id');

                                    if ($product_id)
                                        return Warehouse::hasProduct($product_id)->pluck('name', 'id');

                                    return [];
                                })
                                ->required(),

                            Forms\Components\TextInput::make('qty')
                                ->reactive()
                                ->label(__('fields.qty'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(function (Forms\Get $get) {
                                    return $get('max_qty') ?? 1;
                                })
                                ->afterStateUpdated(function (Forms\Components\TextInput $component, $state, Forms\Get $get) {
                                    $max = $get('max_qty');

                                    if ($state > $max)
                                        $component->state($max);
                                })
                                ->disabled(fn(Forms\Get $get) => $get('target_warehouse_id') === null),

                            Forms\Components\Select::make('destination_warehouse_id')
                                ->label(__('fields.to_warehouse'))
                                ->disabled(fn(Forms\Get $get) => $get('target_warehouse_id') === null)
                                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                    $set('destination_warehouse_pre_movement_qty', null);

                                    if ($state) {
                                        $product_id = $get('product_id');

                                        $ava = ItemStock::whereItemType(Product::class)
                                            ->whereItemId($product_id)->whereWarehouseId($state)->get()->sum(function ($i) {
                                                return $i->available;
                                            });

                                        $set('destination_warehouse_pre_movement_qty', $ava);

                                    }

                                })
                                ->options(function (Forms\Get $get) {
                                    $target_warehouse_id = $get('target_warehouse_id');

                                    return Warehouse::whereNotIn('id', [$target_warehouse_id])->pluck('name', 'id');
                                })
                                ->required(),

                        ])->columns(2),

                    View::make('components.loading'),


                ]),
        ];
    }


    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('targetWarehouse.name')
                    ->label(__('fields.from_warehouse'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('destinationWarehouse.name')
                    ->label(__('fields.to_warehouse'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('qty')
                    ->label(__('fields.qty'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('target_warehouse_pre_movement_qty')
                    ->label(__('fields.target_warehouse_pre_movement_qty'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('target_warehouse_post_movement_qty')
                    ->label(__('fields.target_warehouse_post_movement_qty'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('destination_warehouse_pre_movement_qty')
                    ->label(__('fields.destination_warehouse_pre_movement_qty'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('destination_warehouse_post_movement_qty')
                    ->label(__('fields.destination_warehouse_post_movement_qty'))
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
//                    Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['targetWarehouse', 'destinationWarehouse', 'user'])->latest(); // TODO: Change the autogenerated stub
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\StockMovementResource\Pages\ManageStockMovements::route('/'),
        ];
    }
}
