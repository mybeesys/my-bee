<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ProductResource\Pages;
use App\Filament\Tenant\Resources\ProductResource\RelationManagers;
use App\Filament\Tenant\Resources\ProductResource\Widgets\PricingOverview;
use App\Models\Acc4;
use App\Models\Category;
use App\Models\ItemExtra;
use App\Models\ItemPrice;
use App\Models\Product;
use App\Models\ProductExtra;
use App\Models\ProductionCost;
use App\Models\ProductProductionCost;
use App\Models\ProductRawMaterial;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\ProductVariantOption;
use App\Models\RawMaterial;
use App\Models\TaxProfile;
use App\Models\Unit;
use App\Models\VariantLibrary;
use App\Models\VariantLibraryOption;
use App\Models\Warehouse;
use App\Rules\UniqueTenantItemRule;
use App\Services\FilamentVariantBuilderService;
use App\Services\PricingService;
use App\Services\StockService;
use Awcodes\FilamentTableRepeater\Components\TableRepeater;
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
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = "store/products";

    public static function getNavigationGroup(): ?string
    {
        return user_setting('fav.products', false) ? __('fields.navigation_group_favourites') : __('fields.nav_group_store');
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
                            ->live(true)
                            ->afterStateUpdated(fn($livewire) => self::updateVariantsViewV2($livewire))
                            ->rules([new UniqueTenantItemRule(Product::class, 'name', $form->getRecord()?->id)])
                            ->label(__('fields.name')),

                        Forms\Components\TextInput::make('barcode')
                            ->label(__('fields.barcode'))
                            ->rules([new UniqueTenantItemRule(Product::class, 'barcode', $form->getRecord()?->id)]),

                        TextInput::make('sku')
                            ->required()
                            ->afterStateHydrated(fn(TextInput $component, $record, $operation) => $operation === "create" ? $component->state(random_int(100000000, 999999999)) : $record)
                            ->rules([new UniqueTenantItemRule(Product::class, 'sku', $form->getRecord()?->id)])
                            ->label(__('fields.sku')),

                        Select::make('category_id')
                            ->label(__('fields.category'))
                            ->options(Category::canListProduct()->pluck('name', 'id'))
                            ->required()
                            ->createOptionForm([
                                Forms\Components\Section::make(__('fields.category'))
                                    ->schema([

                                        TextInput::make('name')->label(__('fields.name'))->required(),

//                                        Select::make('parent_id')
//                                            ->label(__('fields.category_parent'))
//                                            ->options(Category::canBecomeParent()->pluck('name', 'id'))
//                                            ->searchable(),

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

                    ])->columns(5),


                Forms\Components\Section::make()->schema([

                    Forms\Components\Hidden::make('type')->default('basic'),

                    Forms\Components\Checkbox::make('enable_variations')
                        ->live()
                        ->label(__('fields.enable_variations'))
                        ->helperText(__('fields.enable_variations_description'))
                        ->dehydrated(false)
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if ($state) {
                                $set('type', 'variants');
                            } else {
                                $set('type', 'basic');
                            }
                        })
                        ->default(0),

                ]),

                Forms\Components\Section::make(__('fields.images'))
                    ->visible(fn(Forms\Get $get): bool => $get('enable_variations') == false)
                    ->collapsible()
                    ->schema([

                        SpatieMediaLibraryFileUpload::make('images')
                            ->label("")
                            ->image()
                            ->reorderable()
                            ->openable()
                            ->downloadable()
                            ->multiple()
                            ->maxSize(2048)
                            ->disk('cdn')
                            ->collection('images')
                            ->directory('products'),

                        //                    Forms\Components\Actions::make([
//                        Forms\Components\Actions\Action::make('Generate excerpt')
//                            ->action(function (Forms\Get $get, Forms\Set $set) {
//                                $set('excerpt', str($get('content'))->words(45, end: ''));
//                            })
//                    ]),

                    ]),

                Forms\Components\Section::make(__('fields.options'))
                    ->visible(fn(Forms\Get $get): bool => $get('enable_variations') == true)
                    ->collapsible()
                    ->schema([
                        Repeater::make('variant_options')
                            ->visible(fn(Forms\Get $get): bool => $get('enable_variations') == true)
                            ->label(__('fields.options'))
                            ->relationship('variantOptions')
                            ->grid(4)
                            ->addActionLabel(__('fields.add'))
                            ->afterStateUpdated(fn($livewire) => self::updateVariantsViewV2($livewire, 'Repeater:variant_options:updated'))
                            ->afterStateHydrated(fn($livewire) => self::updateVariantsViewFromRecord($livewire, 'Repeater:variant_options:hydrated'))
                            ->schema([

                                hidden_tenant_id_field(),

                                Select::make('variant_library_id')
                                    ->required()
                                    ->label(__('fields.option_name'))
                                    ->options(VariantLibrary::all()->pluck('name', 'id'))
                                    ->live()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                                Select::make('values')
                                    ->required()
                                    ->label(__('fields.value'))
                                    ->multiple()
//                                    ->live(true)
                                    ->afterStateUpdated(fn($livewire) => self::updateVariantsViewV2($livewire, 'Repeater:variant_options:select:values:afterStateUpdated'))
                                    ->options(function (Forms\Get $get) {
                                        $variantLib = VariantLibrary::find($get('variant_library_id'));
                                        if ($variantLib) {
                                            return VariantLibraryOption::where('variant_library_id', $variantLib->id)->get()->pluck('name', 'id')->toArray();
                                        }
                                        return [];
                                    })
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                            ]),

                        Forms\Components\Hidden::make('variants_count')->default(0)->dehydrated(false),

                        Forms\Components\Section::make()
                            ->visible(fn(Forms\Get $get): bool => $get('enable_variations') == true)
                            ->key('section-v')
                            ->headerActions(
                                [
                                    Forms\Components\Actions\Action::make('update_all_price')
                                        ->label(__('fields.variants_update_all_price'))
                                        ->color('gray')
                                        ->requiresConfirmation()
                                        ->form([
                                            Forms\Components\Section::make()
                                                ->schema([
                                                    TextInput::make('value')
                                                        ->label(__('fields.value'))
                                                        ->required()
                                                        ->numeric()
                                                        ->minValue(0)
                                                        ->maxValue(PHP_INT_MAX)
                                                        ->extraInputAttributes(['min' => 0, 'max' => PHP_INT_MAX]),
                                                ])
                                        ])
                                        ->action(function (array $data, $livewire) {
                                            foreach ($livewire->data['variants'] ?? [] as $key => $variant) {
                                                $livewire->data['variants'][$key]['price'] = $data['value'];
                                            }
                                        }),

                                    Forms\Components\Actions\Action::make('update_all_discount_price')
                                        ->label(__('fields.variants_update_all_discount_price'))
                                        ->color('gray')
                                        ->requiresConfirmation()
                                        ->form([
                                            Forms\Components\Section::make()
                                                ->schema([
                                                    TextInput::make('value')
                                                        ->label(__('fields.value'))
                                                        ->required()
                                                        ->numeric()
                                                        ->minValue(0)
                                                        ->maxValue(PHP_INT_MAX)
                                                        ->extraInputAttributes(['min' => 0, 'max' => PHP_INT_MAX]),
                                                ])
                                        ])
                                        ->action(function (array $data, $livewire) {
                                            foreach ($livewire->data['variants'] ?? [] as $key => $variant) {
                                                $livewire->data['variants'][$key]['discount_price'] = $data['value'];
                                            }
                                        }),
                                ]
                            )->schema([
                                TableRepeater::make('variants')
                                    ->visible(fn(Forms\Get $get): bool => $get('enable_variations') == true)
                                    ->relationship('variants')
                                    ->hideLabels()
                                    ->emptyLabel(__('fields.no_records_placeholder'))
//                                    ->defaultItems(0)
                                    ->alignHeaders(fn() => app()->getLocale() == "ar" ? "right" : "left")
                                    ->addable(false)
                                    ->label(fn(Forms\Get $get) => __('fields.customize_options') . " (" . $get('variants_count') . ")")
                                    ->addActionLabel(__('fields.add'))
                                    ->live()
                                    ->deleteAction(
                                        fn(Forms\Components\Actions\Action $action) => $action->requiresConfirmation(),
                                    )
                                    ->deletable(function () {
                                        return true;
                                    })
                                    ->minItems(1)
//                                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data) use ($form) {
//                                        $unitCost = $data['unit_cost'];
//                                        $price = $data['price'];
//                                        $discount_price = $data['discount_price'];
//
//                                        PricingService::instance()->addPrice($form->getRecord(), $unitCost, $price, $discount_price);
//
//                                        return $data;
//                                    })
//                                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data) use ($form) {
//
//                                        if($productVariant = ProductVariant::firstWhere('sku', $data['sku'])){
//                                            $unitCost = $data['unit_cost'];
//                                            $price = $data['price'];
//                                            $discount_price = $data['discount_price'];
//                                            PricingService::instance()->addPrice($productVariant, $unitCost, $price, $discount_price);
//                                        }
//
//                                        return $data;
//                                    })
                                    ->mutateRelationshipDataBeforeFillUsing(function (array $data) use ($form) {
                                        $data['price'] = PricingService::instance()->getItemPrice(ProductVariant::find($data['id']), 0);
                                        $data['discount_price'] = PricingService::instance()->getItemDiscountPrice(ProductVariant::find($data['id']), null);
                                        return $data;
                                    })
                                    ->columnWidths([
                                        'name' => '200px',
//                                        'warehouse_id' => '200px',
//                                        'qty' => '90px',
//                                        'unlimited_qty' => '10px',
//                                        'unit_cost' => '135px',
                                        'price' => '200px',
                                        'discount_price' => '200px',
                                    ])
                                    ->schema([

                                        hidden_tenant_id_field(),

                                        Forms\Components\Hidden::make('should_remove')
                                            ->default(false)
                                            ->dehydrated(false),

                                        Forms\Components\Hidden::make('new_item')
                                            ->default(false)
                                            ->dehydrated(false),

                                        Forms\Components\Hidden::make('variant_library_options_ids'),

                                        Forms\Components\Hidden::make('sku'),

                                        Forms\Components\Hidden::make('name_ar'),
                                        Forms\Components\Hidden::make('name_en'),

                                        SpatieMediaLibraryFileUpload::make('image')
                                            ->label("")
                                            ->image()
                                            ->reorderable()
                                            ->openable()
                                            ->downloadable()
                                            ->multiple()
                                            ->maxSize(2048)
                                            ->disk('cdn')
                                            ->collection('images')
                                            ->directory('variants'),

//                                        Forms\Components\Placeholder::make('test'),

                                        TextInput::make('name')
                                            ->label(__('fields.option'))
                                            ->readOnly()
                                            ->extraInputAttributes(function (Forms\Get $get) {
                                                $style = "";

                                                if ($get('should_remove'))
                                                    $style = $style . "text-decoration:line-through;color:#e41414;";

                                                if ($get('new_item'))
                                                    $style = $style . "color:#068f07;";

                                                return ['style' => $style];
                                            })
                                            ->formatStateUsing(function (?ProductVariant $record, $state) {
                                                return $record?->name ?? $state;
                                            })
                                            ->prefix(function (Forms\Get $get) {
                                                $new = $get('new_item');
                                                return $new ? __('fields.new') : "";
                                            })
                                            ->helperText(function (Forms\Get $get) {
                                                return null;
//                                                return json_encode($get('variant_library_options_ids'));
                                            })
                                            ->dehydrated(false),

//                                        Select::make('warehouse_id')
//                                            ->required()
//                                            ->label(__('fields.warehouse'))
//                                            ->options(Warehouse::all()->pluck('name', 'id')),

//                                        TextInput::make('qty')
//                                            ->label(__('fields.qty'))
//                                            ->numeric()
//                                            ->extraInputAttributes(['min' => 0, 'max' => PHP_INT_MAX])
//                                            ->minValue(0)
////                                            ->disabled(fn(Forms\Get $get) => $get('unlimited_qty') == true)
////                                        ->hint(fn(Forms\Get $get) => $get('unlimited_qty') == true ? __('fields.unlimited_qty') : "")
//                                            ->maxValue(PHP_INT_MAX),

//                                        Forms\Components\Checkbox::make('unlimited_qty')
//                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
//                                                if ($state) {
//                                                    $set('qty', null);
//                                                } else {
//                                                    $set('qty', 0);
//                                                }
//                                            })
//                                            ->label(__('fields.unlimited_qty')),

//                                        TextInput::make('unit_cost')
//                                            ->numeric()
//                                            ->minValue(1)
//                                            ->maxValue(PHP_INT_MAX)
//                                            ->extraInputAttributes(['min' => 0, 'max' => PHP_INT_MAX])
//                                            ->nullable()
//                                            ->formatStateUsing(fn($state) => is_number($state) ? number_format($state, 2, '.', '') : null)
//                                            ->label(__('fields.purchase_price')),

                                        TextInput::make('price')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(PHP_INT_MAX)
                                            ->extraInputAttributes(['min' => 0, 'max' => PHP_INT_MAX])
                                            ->nullable()
//                                            ->gt('unit_cost')
                                            ->formatStateUsing(fn($state) => is_number($state) ? number_format($state, 2, '.', '') : null)
                                            ->dehydrated(false)
                                            ->label(__('fields.price')),

                                        TextInput::make('discount_price')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(PHP_INT_MAX)
                                            ->extraInputAttributes(['min' => 0, 'max' => PHP_INT_MAX])
                                            ->nullable()
                                            ->lt('price')
                                            ->formatStateUsing(fn($state) => is_number($state) ? number_format($state, 2, '.', '') : null)
                                            ->dehydrated(false)
                                            ->label(__('fields.discount_price')),

                                    ]),
                            ]),
                    ]),

                Forms\Components\Section::make(__('fields.product_more_details'))
                    ->collapsible()
                    ->schema([

//                        TextInput::make('unit_cost')
//                            ->readOnly(fn(Forms\Get $get): bool => $get('enable_variations') == true)
//                            ->helperText(fn(Forms\Get $get) => $get('enable_variations') == true ? __("fields.calc_by_variants_msg") : null)
//                            ->label(__('fields.purchase_price'))
//                            ->numeric()
//                            ->minValue(1)
//                            ->maxValue(PHP_INT_MAX)
//                            ->extraInputAttributes(['min' => 1, 'max' => PHP_INT_MAX])
//                            ->live(true)
////                                ->gt('data.main_unit_price', isStatePathAbsolute: true)
//                            ->validationMessages([
//                                'gt' => __('fields.validate_unit_price_must_be_bigger_than_main_unit_price'),
//                            ])
//                            ->dehydrated(false)
//                            ->formatStateUsing(function ($record) {
//                                $value = null;
//                                if ($record)
//                                    $value = PricingService::instance()->getItemCost($record);
//                                return is_number($value) ? number_format($value, currency_decimals(), '.', '') : null;
//                            })
//                            ->mainCurrencySuffix(),

                        TextInput::make('price')
                            ->readOnly(fn(Forms\Get $get): bool => $get('enable_variations') == true)
                            ->helperText(fn(Forms\Get $get) => $get('enable_variations') == true ? __("fields.calc_by_variants_msg") : null)
                            ->label(__('fields.sale_price'))
                            //->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(PHP_INT_MAX)
                            ->extraInputAttributes(['min' => 1, 'max' => PHP_INT_MAX])
                            ->live(true)
//                            ->gt('unit_cost')
                            ->dehydrated(false)
                            ->formatStateUsing(function ($record) {
                                $value = null;
                                if ($record)
                                    $value = PricingService::instance()->getItemPrice($record);
                                return is_number($value) ? number_format($value, currency_decimals(), '.', '') : null;
                            })->mainCurrencySuffix(),

                        TextInput::make('discount_price')
                            ->readOnly(fn(Forms\Get $get): bool => $get('enable_variations') == true)
                            ->helperText(fn(Forms\Get $get) => $get('enable_variations') == true ? __("fields.calc_by_variants_msg") : null)
                            ->label(__('fields.discount_price'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(PHP_INT_MAX)
                            ->extraInputAttributes(['min' => 1, 'max' => PHP_INT_MAX])
                            ->live(true)
                            ->lt('price')
                            ->dehydrated(false)
                            ->formatStateUsing(function ($record) {
                                $value = null;
                                if ($record)
                                    $value = PricingService::instance()->getItemDiscountPrice($record);
                                return is_number($value) ? number_format($value, currency_decimals(), '.', '') : null;
                            })
                            ->mainCurrencySuffix(),

                        Select::make('tax_profile_id')
                            ->label(__('fields.tax'))
                            ->placeholder('غير خاضع للضريبة')
                            ->options(TaxProfile::asOptions())
                            ->createOptionForm(TaxProfileResource::getSchemaForCreateOption())
                            ->createOptionUsing(function ($data) {
                                $data['tenant_id'] = filament()->getTenant()->id;
                                $model = TaxProfile::create(Arr::except($data, ['taxes']));
                                foreach ($data['taxes'] as $tax) {
                                    $model->taxes()->create([
                                        'tenant_id' => $data['tenant_id'],
                                        'tax_profile_id' => $model->id,
                                        'description' => $tax['description'],
                                        'percent' => $tax['percent'],
                                    ]);
                                }
                                return $model->id;
                            })
                            ->createOptionAction(
                                fn(Forms\Components\Actions\Action $action) => $action->modalWidth('5xl'),
                            ),


//                        Select::make('warehouse_id')
//                            ->disabled(fn(Forms\Get $get): bool => $get('enable_variations') == true)
//                            ->helperText(fn(Forms\Get $get) => $get('enable_variations') == true ? __("fields.calc_by_variants_msg") : null)
//                            ->required(function (Forms\Get $get) {
//                                return $get('enable_variations') == false;
//                            })
//                            ->label(__('fields.warehouse'))
//                            ->options(Warehouse::all()->pluck('name', 'id'))
//                            ->default(Warehouse::getMainWarehouse()?->id)
//                            ->createOptionForm([
//                                Forms\Components\Section::make(__('fields.warehouse'))
//                                    ->schema([
//                                        TextInput::make('name')
//                                            ->label(__('fields.name'))
//                                            ->required()
//                                            ->autofocus()
//                                            ->rules([new UniqueTenantItemRule(Warehouse::class, 'name')]),
//                                    ])
//                            ])
//                            ->createOptionUsing(function ($data) {
//                                $model = new Warehouse();
//
//                                $model->tenant_id = filament()->getTenant()->id;
//                                $model->name = $data['name'];
//                                $model->save();
//
//                                return $model->id;
//                            })
//                            ->createOptionAction(
//                                fn(Forms\Components\Actions\Action $action) => $action->modalWidth('md'),
//                            ),

//                        TextInput::make('qty')
//                            ->readOnly(fn(Forms\Get $get): bool => $get('enable_variations') == true)
//                            ->helperText(fn(Forms\Get $get) => $get('enable_variations') == true ? __("fields.calc_by_variants_msg") : null)
//                            ->label(__('fields.qty'))
//                            ->numeric()
//                            ->extraInputAttributes(['min' => 0, 'max' => PHP_INT_MAX])
//                            ->minValue(0)
//                            ->dehydrated(false)
//                            ->formatStateUsing(function ($record) {
//                                return StockService::instance()->getAvailableStock($record);
//                            })
////                            ->visible(fn(Forms\Get $get) => $get('unlimited_qty') == false)
//                            ->maxValue(PHP_INT_MAX),


                        TextInput::make('calories')
                            ->label(__('fields.calories'))
                            ->nullable()
                            ->numeric()
                            ->extraInputAttributes(['min' => 1, 'max' => PHP_INT_MAX])
                            ->minValue(1)
                            ->maxValue(PHP_INT_MAX),

//                        Forms\Components\Checkbox::make('unlimited_qty')
//                            ->label(__('fields.unlimited_qty'))
//                            ->live()
//                            ->columnSpanFull()
//                            ->afterStateUpdated(function ($state, Forms\Set $set) {
//                                if ($state)
//                                    $set('qty', null);
//                                else
//                                    $set('qty', 0);
//                            })
//                            ->default(0),

                        Forms\Components\RichEditor::make('description')
                            ->columnSpanFull()
                            ->label(__('fields.description')),

                    ])->columns(4),


                Forms\Components\Section::make(__('fields.product_extras'))
                    ->collapsible()
                    ->schema([

                        TableRepeater::make('extras_table')
                            ->label("")
                            ->defaultItems(0)
                            ->relationship('extras')
                            ->alignHeaders(fn() => app()->getLocale() == "ar" ? "right" : "left")
                            ->hideLabels()
                            ->addActionLabel(__('fields.add'))
                            ->emptyLabel(__('fields.no_records_placeholder'))
                            ->columnWidths([
                                'item_extra_id' => "200px",
//                                'unit_cost' => "200px",
                                'price' => "200px",
                                'discount_price' => "200px",
                            ])
                            ->mutateRelationshipDataBeforeFillUsing(function (array $data) use ($form) {
                                $data['price'] = PricingService::instance()->getItemPrice(ProductExtra::find($data['id']), 0);
                                $data['discount_price'] = PricingService::instance()->getItemDiscountPrice(ProductExtra::find($data['id']), null);
                                return $data;
                            })
                            ->schema([

                                hidden_tenant_id_field(),

                                Select::make('item_extra_id')
                                    ->required()
                                    ->label(__('fields.product_extra'))
                                    ->options(ItemExtra::pluck('name', 'id'))
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->createOptionForm([
                                        Forms\Components\Section::make()->schema([
                                            TextInput::make('name')
                                                ->label(__('fields.name'))
                                                ->required()
                                                ->placeholder(__('fields.extra_placeholder'))
                                                ->autofocus()
                                                ->rules([new UniqueTenantItemRule(ItemExtra::class, 'name')]),
                                        ])
                                    ])
                                    ->createOptionUsing(function ($data) {
                                        $data['tenant_id'] = filament()->getTenant()->id;
                                        $model = ItemExtra::create($data);
                                        return $model->id;
                                    })
                                    ->createOptionAction(
                                        fn(Forms\Components\Actions\Action $action) => $action->modalWidth(MaxWidth::Small),
                                    ),

//                                TextInput::make('unit_cost')
//                                    ->required()
//                                    ->label(__('fields.purchase_price'))
//                                    ->numeric()
//                                    ->minValue(1)
//                                    ->maxValue(PHP_INT_MAX)
//                                    ->extraInputAttributes(['min' => 1, 'max' => PHP_INT_MAX])
//                                    ->live(true)
//                                    ->validationMessages([
//                                        'gt' => __('fields.validate_unit_price_must_be_bigger_than_main_unit_price'),
//                                    ])
//                                    ->formatStateUsing(function ($record, $state) {
//                                        $value = $record?->unit_cost ?? $state;
//                                        return is_number($value) ? number_format($value, currency_decimals(), '.', '') : null;
//                                    })
//                                    ->mainCurrencySuffix(),

                                TextInput::make('price')
                                    ->required()
                                    ->label(__('fields.sale_price'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(PHP_INT_MAX)
                                    ->extraInputAttributes(['min' => 1, 'max' => PHP_INT_MAX])
                                    ->live(true)
//                                    ->gt('unit_cost')
                                    ->formatStateUsing(function ($record, $state) {
                                        $value = $record?->price ?? $state;
                                        return is_number($value) ? number_format($value, currency_decimals(), '.', '') : null;
                                    })
                                    ->dehydrated(false)
                                    ->mainCurrencySuffix(),

                                TextInput::make('discount_price')
                                    ->nullable()
                                    ->label(__('fields.discount_price'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(PHP_INT_MAX)
                                    ->extraInputAttributes(['min' => 1, 'max' => PHP_INT_MAX])
                                    ->live(true)
                                    ->lt('price')
                                    ->formatStateUsing(function ($record, $state) {
                                        $value = $record?->discount_price ?? $state;
                                        return is_number($value) ? number_format($value, currency_decimals(), '.', '') : null;
                                    })
                                    ->dehydrated(false)
                                    ->mainCurrencySuffix(),
                            ]),

                    ]),

                Forms\Components\Section::make()->schema([

                    Forms\Components\TextInput::make('sort')
                        ->label(__('fields.product_sort'))
                        ->nullable()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(50000)
                        ->extraInputAttributes(['min' => 1, 'max' => 50000]),

                    Forms\Components\Checkbox::make('published')
                        ->label(__('fields.product_published'))
                        ->columnSpanFull()
                        ->default(1),

                ])->columns(4),

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
                    ->collection('images'),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('fields.type'))
                    ->getStateUsing(function (Product $record) {

                        if ($record->type === Product::$TYPE_BASIC)
                            return __('fields.product_type_basic');

                        if ($record->type === Product::$TYPE_VARIANTS)
                            return __('fields.product_type_variants');

                        return "-";
                    })
                    ->description(function (Product $record) {
                        if ($record->type === Product::$TYPE_VARIANTS)
                            return $record->variants->count();
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    //'console.log(\'clicked!\')'
//                    ->extraHeaderAttributes(['x-on:click' => 'console.log(\'clicked!\')'])
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

                Tables\Columns\TextColumn::make('price')
                    ->label(__('fields.price'))
                    ->toggleable()
                    ->getStateUsing(function (Product $record) {

                        if ($record->type === Product::$TYPE_BASIC) {
                            $itemPrice = PricingService::instance()->getLastPrice($record);

                            if ($itemPrice) {
                                if ($itemPrice->has_discount) {
                                    $originalPrice = format_amount($itemPrice->price) . " " . main_currency_iso_code();
                                    $retailPrice = format_amount($itemPrice->retail_price) . " " . main_currency_iso_code();
                                    return new HtmlString("<p><h1 style='text-decoration: line-through; font-weight: lighter; color: #ff5028;'>$originalPrice</h1>  $retailPrice</p>");
                                } else {
                                    return main_currency_iso_code() . " " . format_amount(PricingService::instance()->getRetailPrice($record));
                                }
                            }
                        }

                        if ($record->type === Product::$TYPE_VARIANTS)
                            return PricingService::instance()->getProductVariantsPriceRange($record);

                        return "-";
                    })
                    ->description(function ($record) {
                    }),

                Tables\Columns\TextColumn::make('qty')
                    ->label(__('fields.qty'))
                    ->getStateUsing(function (Product $record) {

                        if ($record->type === Product::$TYPE_BASIC)
                            return StockService::instance()->getAvailableStock($record);

                        if ($record->type === Product::$TYPE_VARIANTS) {
                            $qty = 0;

                            foreach ($record->variants as $productVariant) {
                                $qty += (StockService::instance()->getAvailableStock($productVariant));
                            }

                            return $qty;
                        }

                        return "-";
                    }),

//                Tables\Columns\TextColumn::make('description')
//                    ->label(__('fields.description'))
//                    ->toggleable()
//                    ->toggledHiddenByDefault()
//                    ->getStateUsing(function ($record) {
//                        return new HtmlString($record->description);
//                    }),
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
                        Select::make('types')
                            ->label(__('fields.type'))
                            ->multiple()
                            ->options([
                                Product::$TYPE_BASIC => __('fields.product_type_basic'),
                                Product::$TYPE_VARIANTS => __('fields.product_type_variants'),
                            ]),

                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('fields.created_from')),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('fields.created_until')),
                    ])
                    ->indicateUsing(function (array $data): ?string {
                        $indicator = null;
                        if ($data['types']) {
                            $indicator = $indicator . __('fields.type');
                        }
                        if ($data['created_from'] or $data['created_until']) {
                            $indicator = $indicator . __('fields.date');
                        }
                        return $indicator;
                    })
                    ->query(function ($query, array $data) {

                        return $query
                            ->when($data['types'],
                                fn($query, $types) => $query->whereIn('type', $types))
                            ->when($data['created_from'],
                                fn($query) => $query->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'],
                                fn($query) => $query->whereDate('created_at', '<=', $data['created_until']));
                    })
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([

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


    public static function updateVariantsView($record, $livewire): void
    {
        $libraries = collect($livewire->data['variant_options'] ?? []);

//        dd($libraries);
        $variantLibraryOptions = Cache::remember("variantLibraryOptions@" . \filament()->getTenant()->id, 60, function () {
            return VariantLibraryOption::all();
        });

//        #items: array:3 [▼
//        "a6ba4b91-2772-4f2c-a891-c652782ee3b5" => array:2 [▼
//      "variant_library_id" => "1"
//      "values" => array:3 [▶]
//    ]
//    "a254afb0-4027-4bbe-a4e3-5f0b346e77ed" => array:2 [▼
//      "variant_library_id" => "2"
//      "values" => array:5 [▶]
//    ]
//    "aa575a02-bc26-4194-a985-fc3a869b482a" => array:2 [▼
//      "variant_library_id" => null
//      "values" => []
//    ]
//  ]

        //flag to loop first lib only
        $flag = 0;
        $data = [];
        foreach ($libraries as $library) {

            if ($flag > 0)
                continue;
            //we need to loop for each library
            // for example
            //if we have 2 colors and 2 sizes
            // green - l
            // green - xl
            // red - l
            // red - xl


            //example when only one option is selected, display it for example only colors is added
            if ($libraries->except($libraries->keys()->first())->pluck('values')->flatten()->isEmpty()) {
                //add
                $mainLib = $libraries->first();
                foreach ($mainLib['values'] as $value) {
                    //get from cached data or find if not exist
                    $variantLibraryOption = $variantLibraryOptions->firstWhere('id', $value);
                    if (!$variantLibraryOption)
                        $variantLibraryOption = VariantLibraryOption::findOrFail($value);

                    $data[Str::uuid()->toString()] = [
                        'name' => $variantLibraryOption->name,
                        'variant_library_id' => $value,
                    ];

                }
            }


            $currentLibId = $library['variant_library_id'];
            foreach ($library['values'] as $value) {
                //get from cached data or find if not exist
                $variantLibraryOption = $variantLibraryOptions->firstWhere('id', $value);
                if (!$variantLibraryOption)
                    $variantLibraryOption = VariantLibraryOption::findOrFail($value);

                //ignore current lib
                $targetLibs = $libraries->filter(function ($item) use ($currentLibId) {
                    return $item['variant_library_id'] != $currentLibId;
                });

                foreach ($targetLibs as $lib) {
                    if (count($lib['values']) > 0) {

                        foreach ($lib['values'] as $value) {

                            $vlo = $variantLibraryOptions->firstWhere('id', $value);
                            if (!$vlo)
                                $vlo = VariantLibraryOption::findOrFail($value);

                            $data[Str::uuid()->toString()] = [
                                'name' => $variantLibraryOption->name . " - " . $vlo->name,
                                'variant_library_id' => $value,
                            ];
                        }

                    } else {
//                        $data[Str::uuid()->toString()] = [
//                            'name' => $variantOption->name,
//                        ];
                    }

                }
            }

            $flag = 1;
        }

        $livewire->data['variants'] = $data;
    }

    public static function updateVariantsViewV2($livewire, $source = "unknown"): void
    {

        $record = $livewire->record;

        if ($record instanceof ProductVariantOption) {
            $record = $record->product;
        }

        $data = FilamentVariantBuilderService::instance($record, $livewire)->buildOptions();

        if (count($data) > 0) {
            $livewire->data['variants'] = collect($data)->sortByDesc('should_remove')->toArray();
        }

        $livewire->data['variants_count'] = count($data);
    }

    public static function updateVariantsViewFromRecord($livewire, $source = "unknown"): void
    {
        $record = $livewire->record;

        if ($record instanceof ProductVariantOption) {
            $record = $record->product;
        }

        $data = [];

        if ($record)
            $data = FilamentVariantBuilderService::instance($record, $livewire)->buildFromRecord();

        $livewire->data['variants'] = $data;

        $livewire->data['variants_count'] = count($data);
    }

    public static function getRelations(): array
    {
        return [
//            RelationManagers\VariantsRelationManager::class,
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
        return parent::getEloquentQuery()->with(['category', 'allStocks', 'extras.prices', 'extras.lastPrice', 'lastPrice', 'prices', 'stocks'])->latest();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

}
