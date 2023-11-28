<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ProductResource\Pages;
use App\Filament\Tenant\Resources\ProductResource\RelationManagers;
use App\Filament\Tenant\Resources\ProductResource\Widgets\PricingOverview;
use App\Models\Acc4;
use App\Models\Category;
use App\Models\ItemPrice;
use App\Models\Product;
use App\Models\ProductionCost;
use App\Models\ProductProductionCost;
use App\Models\ProductRawMaterial;
use App\Models\ProductStock;
use App\Models\RawMaterial;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Rules\UniqueTenantItemRule;
use App\Services\PricingService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = "products/products";

    public static function getNavigationGroup(): ?string
    {
        return __('fields.products');
    }

    public static function getLabel(): ?string
    {
        return __('fields.product');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.products');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Forms\Form $form): Forms\Form
    {
//        if($form->getOperation() == "edit")
//        {
//            return $form->schema([
//                Forms\Components\Tabs::make()
//                    ->columnSpanFull()
//                    ->schema([
//
//                    Forms\Components\Tabs\Tab::make('Inventory')->schema([
//
//                    ]),
//
//                    Forms\Components\Tabs\Tab::make('Variations')->schema([
//
//                    ]),
//
//                    Forms\Components\Tabs\Tab::make('Pricing')->schema([
//
//                    ]),
//
//                    Forms\Components\Tabs\Tab::make('Settings')->schema([
//
//                    ]),
//
//                ])
//            ]);
//        }
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([

                        hidden_tenant_id_field(),

                        Forms\Components\TextInput::make('name')
                            ->autofocus()
                            ->required()
                            ->rules([new UniqueTenantItemRule(Product::class, 'name', $form->getRecord()?->id)])
                            ->label(__('fields.name')),

                        Forms\Components\TextInput::make('barcode')
                            ->label(__('fields.barcode'))
                            ->rules([new UniqueTenantItemRule(Product::class, 'barcode', $form->getRecord()?->id)]),

                        Select::make('category_id')
                            ->label(__('fields.category'))
                            ->options(Category::canListProduct()->pluck('name', 'id'))
                            ->required()
                            ->createOptionForm([
                                Forms\Components\Section::make(__('fields.category'))
                                    ->schema([

                                        TextInput::make('name')->label(__('fields.name'))->required(),

                                        Select::make('parent_id')
                                            ->label(__('fields.category_parent'))
                                            ->options(Category::canBecomeParent()->pluck('name', 'id'))
                                            ->searchable(),

                                        Forms\Components\TextInput::make('sort')
                                            ->label(__('fields.sort'))
                                            ->required()
                                            ->default(1)
                                            ->numeric(),

                                    ])
                            ])
                            ->createOptionUsing(function ($data) {
                                $model = new Category();

                                $model->tenant_id = filament()->getTenant()->id;
                                $model->name = $data['name'];
                                $model->parent_id = $data['parent_id'] ?? null;
                                $model->sort = $data['sort'];

                                $model->save();

                                return $model->id;
                            })
                            ->createOptionAction(
                                fn(Forms\Components\Actions\Action $action) => $action->modalWidth('md'),
                            )
                            ->searchable(),


                        Forms\Components\TextInput::make('security_stock')
                            ->required()
                            ->default(10)
                            ->numeric()
                            ->minValue(1)
                            ->label(__('fields.security_stock')),

                    ])->columns(4),

                Forms\Components\Section::make()->schema([

                    Select::make('main_unit_id')
                        ->label(__('fields.main_unit'))
                        ->options(Unit::pluck('name', 'id'))
                        ->required()
                        ->live()
                        ->createOptionForm([
                            Forms\Components\Section::make(__('fields.unit'))
                                ->schema([

                                    TextInput::make('name')
                                        ->label(__('fields.name'))
                                        ->required()
                                        ->autofocus()
                                        ->rules([new UniqueTenantItemRule(Unit::class, 'name')]),
                                ])
                        ])
                        ->createOptionUsing(function ($data) {
                            $model = new Unit();

                            $model->tenant_id = filament()->getTenant()->id;
                            $model->name = $data['name'];
                            $model->save();

                            return $model->id;
                        })
                        ->createOptionAction(
                            fn(Forms\Components\Actions\Action $action) => $action->modalWidth('md'),
                        )
                        ->searchable(),


                    TextInput::make('main_unit_cost')
                        ->label(__('fields.purchase_price'))
                        ->live(true)
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(PHP_INT_MAX)
                        ->formatStateUsing(function ($record) {
                            if ($record) {
                                $unit_cost = $record->acc4?->prices?->where('unit_id', $record->main_unit_id)->last()?->unit_cost;
                                if (is_number($unit_cost)) {
                                    return number_format($unit_cost, 2, '.', '');
                                }
                            }
                        })
                        ->helperText(function ($state) {
                            return numbers_to_words($state);
                        })
//                        ->required()
                        ->mainCurrencySuffix(),

                    TextInput::make('main_unit_price')
                        ->label(__('fields.sale_price'))
                        ->live(true)
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(PHP_INT_MAX)
                        ->formatStateUsing(function ($record) {
                            if ($record) {
                                $price = $record->acc4?->prices?->where('unit_id', $record->main_unit_id)->last()?->price;
                                if (is_number($price)) {
                                    return number_format($price, 2, '.', '');
                                }
                            }
                        })
                        ->helperText(function ($state) {
                            return numbers_to_words($state);
                        })
//                        ->gt('main_unit_cost')
//                        ->required()
                        ->mainCurrencySuffix(),

                ])->columns(3),

                Forms\Components\Section::make()->schema([
//                    Forms\Components\Checkbox::make('add_more_units')->live()->default(0),

                    Repeater::make('units')
                        ->label(__('fields.more_units'))
//                        ->visible(fn(Forms\Get $get): bool => $get('add_more_units') === true)
                        ->relationship('units')
                        ->mutateRelationshipDataBeforeFillUsing(function (array $data) use ($form) {
                            $prices = $form->getRecord()?->acc4?->prices;

                            if ($prices and $itemPrice = $prices->where('unit_id', $data['unit_id'])->last()) {
                                $itemPrice = $prices->where('unit_id', $data['unit_id'])->last();
                                $data['unit_cost'] = $itemPrice->unit_cost == null ? null : number_format($itemPrice->unit_cost, 2, '.', '');
                                $data['retail_price'] = number_format($itemPrice->price, 2, '.', '');
                            }

                            return $data;
                        })
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data) use ($form) {

                            $unitCost = $data['unit_cost'];
                            $retailPrice = $data['retail_price'];
                            $newPrice = PricingService::instance()->addPrice($form->getRecord(), $data['unit_id'], $unitCost, $retailPrice);

                            unset($data['unit_cost']);
                            unset($data['retail_price']);

                            $data['tenant_id'] = \filament()->getTenant()->id;

                            return $data;
                        })
                        ->mutateRelationshipDataBeforeSaveUsing(function (array $data) use ($form) {

                            $unitCost = $data['unit_cost'];
                            $retailPrice = $data['retail_price'];

                            $newPrice = PricingService::instance()->addPrice($form->getRecord(), $data['unit_id'], $unitCost, $retailPrice);

                            unset($data['unit_cost']);
                            unset($data['retail_price']);

                            return $data;
                        })
                        ->schema([

                            TextInput::make('barcode')
                                ->label(__('fields.barcode'))
                                ->maxLength(255),

                            Select::make('unit_id')
                                ->label(__('fields.unit'))
                                ->required()
                                ->searchable()
                                ->createOptionForm([
                                    Forms\Components\Section::make(__('fields.unit'))
                                        ->schema([

                                            TextInput::make('name')
                                                ->label(__('fields.name'))
                                                ->required()
                                                ->autofocus()
                                                ->rules([new UniqueTenantItemRule(Unit::class, 'name')]),
                                        ])
                                ])
                                ->createOptionUsing(function ($data) {
                                    $model = new Unit();

                                    $model->tenant_id = filament()->getTenant()->id;
                                    $model->name = $data['name'];
                                    $model->save();

                                    return $model->id;
                                })
                                ->createOptionAction(
                                    fn(Forms\Components\Actions\Action $action) => $action->modalWidth('md'),
                                )
                                ->rules([
                                    function ($component) {
                                        return function (string $attribute, $value, \Closure $fail) use ($component) {

                                            $items = $component->getContainer()->getParentComponent()->getState();

                                            $selected = array_column($items, $component->getName());

                                            if (count(array_unique($selected)) < count($selected)) {
                                                $fail('You can only select one option.');
                                            }
                                        };
                                    },
                                ])
                                ->options(function (Forms\Get $get) {
                                    $main_unit_id = $get('../../main_unit_id');

                                    if ($main_unit_id) {
                                        return Unit::whereNotIn('id', [$main_unit_id])->pluck('name', 'id')->toArray();
                                    }
                                    return Unit::pluck('name', 'id')->toArray();
                                }),

                            TextInput::make('unit_count_from_main_unit')
                                ->label(__('fields.unit_count_from_main_unit'))
                                ->required()
                                ->live(true)
//                                ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
//                                    $main_unit_price = $get('../../main_unit_price');
//                                    if (is_number($state) and is_number($main_unit_price)) {
//                                        $set('retail_price', number_format($state * $main_unit_price, 2, '.', ''));
//                                    }
//                                })
                                ->numeric()
                                ->rules([
                                    function ($component) {
                                        return function (string $attribute, $value, \Closure $fail) use ($component) {

                                            $items = $component->getContainer()->getParentComponent()->getState();

                                            $selected = array_column($items, $component->getName());

                                            if (count(array_unique($selected)) < count($selected)) {
                                                $fail('Duplicate entries.');
                                            }
                                        };
                                    },
                                ])
                                ->minValue(2),


                            TextInput::make('unit_cost')
                                ->label(__('fields.purchase_price'))
                                ->default(null)
//                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(PHP_INT_MAX)
                                ->live(true)
//                                ->gt('data.main_unit_price', isStatePathAbsolute: true)
                                ->validationMessages([
                                    'gt' => __('fields.validate_unit_price_must_be_bigger_than_main_unit_price'),
                                ])
                                ->formatStateUsing(fn($state) => is_number($state) ? number_format($state, 2, '.', '') : null)
                                ->helperText(function ($state) {
                                    return numbers_to_words($state);
                                })
                                ->rules([
                                    function ($component) {
                                        return function (string $attribute, $value, \Closure $fail) use ($component) {

                                            $items = $component->getContainer()->getParentComponent()->getState();

                                            $selected = array_column($items, $component->getName());

                                            if (count(array_unique($selected)) < count($selected)) {
                                                $fail('Duplicate entries.');
                                            }
                                        };
                                    },
                                ])
                                ->mainCurrencySuffix(),

                            TextInput::make('retail_price')
                                ->label(__('fields.sale_price'))
                                ->default(null)
                                //->required()
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(PHP_INT_MAX)
                                ->live(true)
//                                ->gt('unit_cost')
//                                ->gt('data.main_unit_price', isStatePathAbsolute: true)
//                                ->validationMessages([
//                                    'gt' => __('fields.validate_unit_price_must_be_bigger_than_main_unit_price'),
//                                ])
                                ->formatStateUsing(fn($state) => is_number($state) ? number_format($state, 2, '.', '') : null)
                                ->helperText(function ($state) {
                                    return numbers_to_words($state);
                                })
                                ->rules([
                                    function ($component) {
                                        return function (string $attribute, $value, \Closure $fail) use ($component) {

                                            $items = $component->getContainer()->getParentComponent()->getState();

                                            $selected = array_column($items, $component->getName());

                                            if (count(array_unique($selected)) < count($selected)) {
                                                $fail('Duplicate entries.');
                                            }
                                        };
                                    },
                                ])
                                ->mainCurrencySuffix(),

                        ])
                        ->reorderable()
                        ->defaultItems(0)
                        ->addActionLabel(__('fields.add'))
                        ->columns(5)
                        ->columnSpanFull()

                ])->columns(2),

                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Section::make()->schema([
                            Forms\Components\Checkbox::make('enable_variations')
                                ->live()
                                ->label(__('fields.enable_variations'))
                                ->helperText(__('fields.enable_variations_description'))
                                ->default(0),
                        ]),
                    ]),

//                Forms\Components\Section::make()
//                    ->disabled(fn(Page $livewire) => $livewire instanceof Pages\EditProduct)
//                    ->visible(function (Page $livewire, Forms\Get $get) {
//                        return $livewire instanceof Pages\CreateProduct and $get('enable_variations') === false;
//                    })
//                    ->schema([
//                        Repeater::make('opening_stock')
//                            ->label(__('fields.opening_stock'))
//                            ->relationship(
//                                'stocks',
//                                modifyQueryUsing: fn(Builder $query) => $query->whereBelongsTo(Filament::getTenant()))
//                            ->schema([
//
//                                hidden_tenant_id_field(),
//
//                                Forms\Components\Hidden::make('currency_iso_code')
//                                    ->default(setting('main_currency', 'SAR')),
//
//                                Forms\Components\Hidden::make('type')
//                                    ->default('opening-stock'),
//
//                                Forms\Components\Hidden::make('user_id')->default(function () {
//                                    return auth()->user()->id;
//                                }),
//
//                                Forms\Components\Hidden::make('date')->default(function () {
//                                    return now()->toDateString();
//                                }),
//                                Select::make('warehouse_id')
//                                    ->label(__('fields.warehouse'))
//                                    ->searchable()
//                                    ->options(Warehouse::pluck('name', 'id'))
//                                    ->required()
//                                    ->columnSpan(1),
//                                Forms\Components\Hidden::make('qty_out')
//                                    ->default(0),
//                                TextInput::make('qty_in')
//                                    ->live(true)
//                                    ->label(__('fields.qty'))
//                                    ->numeric()
//                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
//                                        if ($state) {
//
//                                            $qty = $get('qty_in');
//                                            $unit_cost = $get('unit_cost');
//
//                                            if ($qty and is_numeric($qty) and $qty > 0) {
//                                                if ($unit_cost and (is_numeric($unit_cost) or is_float($unit_cost)) and $unit_cost > 0) {
//                                                    $set('total_price', format_amount($qty * $unit_cost));
//                                                }
//                                            }
//                                        }
//
//                                    })
//                                    ->required(),
//                                TextInput::make('unit_cost')
//                                    ->mainCurrencySuffix()
//                                    ->live(true)
//                                    ->label(__('fields.unit_cost'))
//                                    ->numeric()
//                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
//
//                                        if ($state and (is_numeric($state) or is_float($state)) and $state > 0) {
//
//                                            $qty = $get('qty_in');
//                                            $unit_cost = $state;
//
//                                            if ($qty and is_numeric($qty) and $qty > 0) {
//                                                $set('total_price', format_amount($qty * $unit_cost));
//                                            }
//                                        }
//
//                                    })
//                                    ->required(),
//
//
//                                TextInput::make('total_price')
//                                    ->reactive()
//                                    ->readOnly()
//                                    ->mainCurrencySuffix()
//                                    ->dehydrated(false)
//                                    ->label(__('fields.total_price')),
//
//                            ])
//                            ->addActionLabel(__('fields.add'))
//                            ->grid(1)
//                            ->collapsible()
//                            ->defaultItems(0)
//                            ->columns(4),
//                    ]),

                Forms\Components\Section::make()
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('images')
                            ->label(__('fields.images'))
                            ->image()
                            ->reorderable()
                            ->openable()
                            ->downloadable()
                            ->multiple()
                            ->maxSize(2048)
                            ->disk('cdn')
                            ->directory('products'),

                        //                    Forms\Components\Actions::make([
//                        Forms\Components\Actions\Action::make('Generate excerpt')
//                            ->action(function (Forms\Get $get, Forms\Set $set) {
//                                $set('excerpt', str($get('content'))->words(45, end: ''));
//                            })
//                    ]),

                    ]),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([

                SpatieMediaLibraryImageColumn::make('images')
                    ->label(__('fields.image'))
                    ->disk('cdn')
                    ->toggleable()
                    ->collection('products'),


                Tables\Columns\TextColumn::make('name')
                    //'console.log(\'clicked!\')'
                    ->extraHeaderAttributes(['x-on:click' => 'console.log(\'clicked!\')'])
                    ->label(__('fields.name'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->searchable()
                    ->toggleable()
                    ->label(__('fields.category')),

                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('fields.barcode'))
                    ->toggleable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('stock')
                    ->label(__('fields.stock'))
                    ->toggleable()
                    ->getStateUsing(function ($record) {
                        return $record->stocks->sum(function ($i) {
                            return $i->available;
                        });
                    }),

                Tables\Columns\TextColumn::make('price')
                    ->label(__('fields.price'))
                    ->toggleable()
                    ->getStateUsing(function ($record) {
                        //get main unit price if its has no custom units
                        if ($itemPrice = $record->acc4->getItemPricePriceForUnit($record->main_unit_id)) {
                            return $itemPrice->currency_iso_code . " " . format_amount($itemPrice->price);
                        }

                        return "-";
                    })
                    ->description(function ($record) {
                        return $record->mainUnit->name;
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('fields.description'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->getStateUsing(function ($record) {
                        return new HtmlString($record->description);
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->toggleable()
                    ->label(__('fields.created_at'))
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->label(__('fields.created_at'))
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('fields.created_from')),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('fields.created_until')),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'],
                                fn($query) => $query->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'],
                                fn($query) => $query->whereDate('created_at', '<=', $data['created_until']));
                    })
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([

//                    Tables\Actions\Action::make('add_price')
//                        ->label(__('fields.add_pricing'))
//                        ->requiresConfirmation()
//                        ->modalWidth('lg')
//                        ->form([
//                            Forms\Components\TextInput::make('item')
//                                ->label(__('fields.product'))
//                                ->disabled(1)
//                                ->afterStateHydrated(function (Forms\Components\TextInput $component, $record) {
//                                    $component->state($record->name);
//                                }),
//
//                            Forms\Components\TextInput::make('last_pricing_date')
//                                ->dehydrated(false)
//                                ->label(__('fields.last_pricing_date'))
//                                ->disabled(1)
//                                ->afterStateHydrated(function (Forms\Components\TextInput $component, $record) {
//                                    $lastPrice = $record->acc4->lastPrice;
//                                    $state = $lastPrice == null ? "-" : $lastPrice->date->format('d-m-Y');
//                                    $component->state($state);
//                                }),
//
//                            Forms\Components\TextInput::make('last_pricing')
//                                ->dehydrated(false)
//                                ->label(__('fields.last_pricing'))
//                                ->disabled(1)
//                                ->afterStateHydrated(function (Forms\Components\TextInput $component, $record) {
//                                    $lastPrice = $record->acc4->lastPrice;
//                                    $state = $lastPrice == null ? "-" : number_format($lastPrice->price, 2);
//                                    $component->state($state);
//                                }),
//
//
//                            Forms\Components\TextInput::make('exchange_rate')
//                                ->label(__('fields.exchange_rate'))
//                                ->required()
//                                ->minValue(1)
//                                ->maxValue(99000000)
//                                ->afterStateHydrated(function (Forms\Components\TextInput $component, $record) {
//                                    $component->state(ex_rate());
//                                }),
//
//                            Forms\Components\TextInput::make('price')
//                                ->reactive()
//                                ->label(__('fields.price'))
//                                ->required()
//                                ->numeric()
//                                ->minValue(1)
//                                ->maxValue(99000000),
//                        ])
//                        ->icon('heroicon-o-pencil')
//                        ->action(function (Product $record, array $data) {
//                            ItemPrice::create(
//                                [
//                                    'tenant_id' => \filament()->getTenant()->id,
//                                    'acc4_code' => $record->acc4->code,
//                                    'price' => $data['price'],
//                                    'exchange_rate' => $data['exchange_rate'],
//                                    'date' => now(),
//                                ]
//                            );
//
//                            Notification::make()
//                                ->title(__('fields.added_pricing_alert'))
//                                ->success()
//                                ->send();
//                        })
//                        ->color('success'),

                    Tables\Actions\DeleteAction::make()
                        ->action(function (Product $record) {
                            if (can_delete_product($record)) {
                                try {
                                    DB::beginTransaction();

                                    $deleted = Acc4::where('code', $record->acc4->code)->first()->delete();

                                    if (!$deleted)
                                        throw new \Exception('Unable to delete account.');

                                    $record->delete();

                                    DB::commit();

                                    Notification::make()
                                        ->title(__('fields.record_deleted_alert'))
                                        ->success()
                                        ->send();

                                } catch (\Exception $exception) {
                                    DB::rollBack();
                                    fns()->displayException($exception);
                                }
                            } else {
                                Notification::make()
                                    ->title(__('fields.record_in_use_alert'))
                                    ->warning()
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->bulkActions([
            ])->deferLoading();
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StocksRelationManager::class,
//            RelationManagers\RawMaterialsRelationManager::class,
//            RelationManagers\CostsRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            PricingOverview::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['category', 'mainUnit', 'units', 'acc4.lastPrice', 'acc4.prices', 'costs.ProductionCost', 'rawMaterials', 'stocks.warehouse'])->latest();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }


    public static function canViewAny(): bool
    {
//        dd(auth()->user()->roles->first()->permissions->pluck('name')->toArray());

        return parent::canViewAny(); // TODO: Change the autogenerated stub
    }
}
