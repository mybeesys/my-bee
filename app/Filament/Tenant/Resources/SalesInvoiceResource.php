<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\SalesInvoiceResource\Pages;
use App\Filament\Tenant\Resources\SalesInvoiceResource\RelationManagers;
use App\Models\AdditionalCostType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\PriceOffer;
use App\Models\Product;
use App\Models\ProductExtra;
use App\Models\ProductVariant;
use App\Models\ReceiptVoucher;
use App\Models\ServiceType;
use App\Models\Tax;
use App\Models\TaxProfile;
use App\Models\VariantLibrary;
use App\Models\VariantLibraryOption;
use App\Rules\UniqueTenantItemRule;
use App\Services\AccountingService;
use App\Services\CacheService;
use App\Services\MathService;
use App\Services\PricingService;
use App\Services\StockService;
use Awcodes\Shout\Components\Shout;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Actions\StaticAction;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class SalesInvoiceResource extends Resource
{

    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 2;
    protected static ?string $slug = "invoices/sales";

    protected static ?string $recordTitleAttribute = "no";

    public static function getNavigationGroup(): ?string
    {
        return user_setting('fav.sales_invoices', false) ? __('fields.navigation_group_favourites') : __('fields.nav_group_sales');
    }

    public static function getLabel(): ?string
    {
        return __('fields.sales_invoice');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.sales_invoices');
    }

    public static function getNavigationBadge(): ?string
    {
        return Invoice::sales()->where('temp', false)->count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Hidden::make('price_offer_id')->dehydrated(false),

                Shout::make('inv-con-alert')
                    ->visible(fn() => Invoice::sales()->count() == 0)
                    ->content(__('fields.config_invoice_alert'))
                    ->icon("")
                    ->color(Color::Sky)
                    ->type('warning'),

                Shout::make('from-price-offer')
                    ->visible(fn(Get $get) => $get('price_offer_id') !== null)
                    ->content(fn(Get $get) => PriceOffer::find($get('price_offer_id'))?->description)
                    ->icon("")
                    ->color(Color::Yellow)
                    ->columnSpan(2)
                    ->type('warning'),

                Forms\Components\Section::make()
                    ->disabled($form->getRecord()?->locked_at !== null)
                    ->schema([

                        Forms\Components\Hidden::make('status')->default('sale_order'),

                        Forms\Components\Hidden::make('type')->default('sales'),

                        Forms\Components\Hidden::make('for')->default('customer'),

                        hidden_tenant_id_field(),

                        hidden_user_id_field(),

                        TextInput::make('no')
                            ->label(__('fields.invoice_no'))
                            ->readOnly()
                            ->required()
                            ->default(fn() => generate_invoice_no())
                            ->rules([new UniqueTenantItemRule(Invoice::class, 'no', $form->getRecord()?->id)]),

                        /////////////////
                        hidden_invoice_no_field(),
                        ////////////////
                        ///
                        DatePicker::make('date')
                            ->label(__('fields.date'))
                            ->seconds(false)
                            ->minDate(now()->subDays(30))
                            ->maxDate(now())
                            ->default(now())
                            ->required()
                            ->displayFormat('d/m/Y'),

                        Select::make('customer_id')
                            ->label(__('fields.client'))
                            ->searchable()
                            ->options(Customer::pluck('name', 'id'))
                            ->createOptionForm(CustomerResource::getSchema())
                            ->createOptionUsing(function ($data) {
                                $data['tenant_id'] = filament()->getTenant()->id;
                                $model = Customer::create($data);
                                return $model->id;
                            })
                            ->createOptionAction(
                                fn(Forms\Components\Actions\Action $action) => $action->modalWidth('5xl'),
                            )
                            ->required(),


                    ])->columns(4),

                Forms\Components\Section::make()
                    ->disabled($form->getRecord()?->locked_at !== null)
                    ->key('items-section')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('add_product')
                            ->color('primary')
                            ->label(__('fields.add_product'))
                            ->disabled($form->getRecord()?->locked_at !== null)
                            ->modalSubmitAction(fn(StaticAction $action) => $action->label(__('fields.add'))->color('primary'))
                            ->modalCancelAction(fn(StaticAction $action) => $action->label(__('fields.close'))->color('danger'))
                            ->form([
                                Forms\Components\Section::make()
                                    ->schema([

                                        hidden_tenant_id_field(),

                                        Forms\Components\Hidden::make('name'),
                                        Forms\Components\Hidden::make('type'),
                                        Forms\Components\Hidden::make('model_id'),
                                        Forms\Components\Hidden::make('model_type'),
                                        Forms\Components\Hidden::make('unit_price'),
                                        Forms\Components\Hidden::make('max_qty')->default(0),

                                        Forms\Components\Hidden::make('variant_options'),

                                        Select::make('product_id')
                                            ->label(__('fields.product'))
                                            ->required()
                                            ->live()
                                            ->searchable()
                                            ->options(Product::pluck('name', 'id'))
//                                            ->options(Product::groupedAsOptions())
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                if ($state) {
                                                    $product = Product::find($state);

                                                    if (!$product) {
                                                        $set('type', null);
                                                        $set('name', null);
                                                        $set('model_id', null);
                                                        $set('model_type', null);
                                                        $set('unit_price', null);
                                                        $set('max_qty', 0);
                                                        fns()->sendDanger("Product not found!");
                                                        return;
                                                    }

                                                    if ($product->type == Product::$TYPE_BASIC) {
                                                        $set('type', $product->type);
                                                        $set('name', $product->name);
                                                        $set('model_id', $product->id);
                                                        $set('model_type', Product::class);
                                                        $set('unit_price', number_format(PricingService::instance()->getRetailPrice($product), currency_decimals(), '.', ''));
                                                        $set('max_qty', StockService::instance()->getAvailableStock($product));
                                                    }

                                                } else {
                                                    $set('type', null);
                                                    $set('name', null);
                                                    $set('model_id', null);
                                                    $set('model_type', null);
                                                    $set('unit_price', null);
                                                    $set('max_qty', 0);
                                                }
                                            }),


                                        Forms\Components\Fieldset::make(__('fields.options'))
                                            ->visible(fn(Forms\Get $get) => Product::where('type', Product::$TYPE_VARIANTS)->where('id', $get('product_id'))->first())
                                            ->schema(function (Forms\Get $get, $livewire) {
                                                $product_id = $get('product_id');
                                                if ($product_id)
                                                    return self::getVariantFieldsBasedOnOptions($product_id, $livewire);

                                                return [];
                                            }),

                                        Forms\Components\Fieldset::make(__('fields.product_extras'))
                                            ->visible(function (Get $get) {
                                                $product_id = $get('product_id');
                                                return $product_id != null and count(self::getProductExtras($product_id));
                                            })
                                            ->schema(function (Forms\Get $get, $livewire) {
                                                $product_id = $get('product_id');
                                                if ($product_id)
                                                    return self::getProductExtras($product_id);

                                                return [];
                                            }),

                                    ])->columns(2)
                            ])
                            ->action(function (array $data, $livewire, Forms\Components\Actions\Action $action, array $arguments) {

                                $product = Product::with(['variants', 'extras'])->findOrFail($data['product_id']);

                                $existingDetails = $livewire->data['items'] ?? [];

                                $productExtrasIds = extract_data_from_array_that_has_key_starts_with("px@", $data);

                                if ($product->type == Product::$TYPE_VARIANTS) {

                                    $variantOptions = extract_data_from_array_that_has_key_starts_with("vo@", $data);
                                    $variantLibraryOptions = extract_values_from_array_that_has_key_starts_with("vo@", $data);
                                    if (count($variantOptions) < 0) {
                                        fns()->sendDanger("Something went-wrong!");
                                        $action->halt();
                                    }

                                    //check if variant is available

                                    $variant = $product->Variants->filter(function ($item) use ($variantLibraryOptions) {
                                        $array1 = $item->variant_library_options_ids;
                                        $array2 = $variantLibraryOptions;
                                        return array_diff($array1, $array2) == array_diff($array2, $array1);
                                    })->first();

                                    if (!$variant) {
                                        fns()->sendDanger("Option not found");
                                    }
                                    $productExtras = ProductExtra::with('lastPrice')->findMany($productExtrasIds);

                                    $tenant_id = $data['tenant_id'];
                                    $name = $variant->name;
                                    $model_id = $variant->id;
                                    $model_type = ProductVariant::class;

                                    $item[Str::uuid()->toString()] = [
                                        'tenant_id' => $tenant_id,
                                        'item_id' => $model_id,
                                        'item_type' => $model_type,
                                        'product_id' => $model_type == Product::class ? $model_id : ProductVariant::find($model_id)->product_id,
                                        'product_variant_id' => $model_type == ProductVariant::class ? $model_id : null,
                                        'name' => $name,
                                        'price' => number_format(PricingService::instance()->getRetailPrice($variant), currency_decimals(), '.', ''),
                                        'qty' => 1,
                                        'discount' => 0,
                                        'tax' => 0,
                                        'product_extras_ids' => $productExtrasIds,
                                        'available_product_extras_ids' => $product->extras->pluck('id')->toArray(),
                                        'extras' => implode(', ', $productExtras->pluck('name')->toArray()),
                                        'tax_profile_id' => null,
                                        'tax_profile_data' => null,
                                        'extras_total' => PricingService::instance()->getRetailPrices($productExtras),
                                    ];

                                } else {
                                    //basic
                                    $productExtras = ProductExtra::with('lastPrice')->findMany($productExtrasIds);

                                    $tenant_id = $data['tenant_id'];
                                    $name = $data['name'];
                                    $model_id = $data['model_id'];
                                    $model_type = $data['model_type'];

                                    $item[Str::uuid()->toString()] = [
                                        'tenant_id' => $tenant_id,
                                        'item_id' => $model_id,
                                        'item_type' => $model_type,
                                        'product_id' => $model_type == Product::class ? $model_id : ProductVariant::find($model_id)->product_id,
                                        'product_variant_id' => $model_type == ProductVariant::class ? $model_id : null,
                                        'name' => $name,
                                        'price' => number_format(PricingService::instance()->getRetailPrice(Product::find($model_id)), currency_decimals(), '.', ''),
                                        'qty' => 1,
                                        'discount' => 0,
                                        'tax' => 0,
                                        'tax_profile_id' => null,
                                        'tax_profile_data' => null,
                                        'product_extras_ids' => $productExtrasIds,
                                        'available_product_extras_ids' => $product->extras->pluck('id')->toArray(),
                                        'extras' => implode(', ', $productExtras->pluck('name')->toArray()),
                                        'extras_total' => PricingService::instance()->getRetailPrices($productExtras),
                                    ];
                                }

//                                $itemExists = collect($existingDetails)->where('product_id', $product->id)->where('product_variant_id', $product->product_variant_id)->first();

//                                if ($itemExists) {
//                                    fns()->sendWarning(__('fields.order_details_item_already_exists'));
//                                    $action->halt();
//                                }


                                foreach ($livewire->data['items'] as $index => $it) {
                                    if ($it['product_id'] == null) {
                                        unset($livewire->data['items'][$index]);
                                        unset($existingDetails[$index]);
                                    }
                                }

                                $livewire->data['items'] = array_merge($existingDetails, $item);

                                self::updateInvoicePropertiesFromLivewire($livewire);

                                fns()->saved();

                                $action->halt();
                            }),
                    ])
                    ->schema([
                        TableRepeater::make('items')
                            ->dehydrated(false)
                            ->label(__('fields.items'))
                            ->headers([
                                Header::make('name')
                                    ->width("155px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.name')),

                                Header::make('extras')
                                    ->width("180px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->label(__('fields.product_extras')),

                                Header::make('qty')
                                    ->width("80px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.qty')),

                                Header::make('price')
                                    ->width("120px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.price')),

                                Header::make('discount')
                                    ->width("100px")
                                    ->markAsRequired()
                                    ->label(__('fields.discount')),

                                Header::make('tax_profile_id')
                                    ->width("200px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.tax_profile')),

                                Header::make('tax')
                                    ->width("120px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->label(__('fields.tax')),

                                Header::make('sub_total')
                                    ->width("120px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.sub_total')),

                            ])
//                            ->relationship('items')
                            ->emptyLabel(__('fields.no_records_placeholder'))
                            ->addActionLabel(__('fields.add'))
                            ->addable(false)
                            ->defaultItems(0)
                            ->minItems(1)
                            ->deletable($form->getRecord() === null)
                            ->deleteAction(
                                fn(Forms\Components\Actions\Action $action) => $action->requiresConfirmation(),
                            )
                            ->mutateRelationshipDataBeforeFillUsing(function ($data) {
                                if ($data['product_variant_id']) {
                                    $data['item_id'] = $data['product_variant_id'];
                                    $data['item_type'] = ProductVariant::class;
                                } else {
                                    $data['item_id'] = $data['product_id'];
                                    $data['item_type'] = Product::class;
                                }
                                $data['price'] = number_format($data['price'], currency_decimals(), '.', '');
                                $data['discount'] = number_format($data['discount'], currency_decimals(), '.', '');
                                $data['total'] = format_amount($data['qty'] * $data['price']);

                                return $data;
                            })
                            ->afterStateUpdated(function ($livewire) {
                                self::updateInvoicePropertiesFromLivewire($livewire);
                            })
                            ->schema([

                                hidden_tenant_id_field(),

                                Forms\Components\Hidden::make('item_id')->dehydrated(false),
                                Forms\Components\Hidden::make('item_type')->dehydrated(false),

                                Forms\Components\Hidden::make('extras_total')->dehydrated(false),

                                Forms\Components\Hidden::make('product_id'),
                                Forms\Components\Hidden::make('product_variant_id'),
                                Forms\Components\Hidden::make('tax_profile_data'),

//                                Forms\Components\Hidden::make('product_extras_ids')->dehydrated(false),

                                TextInput::make('name')->label(__('fields.product'))->readOnly(),

                                Forms\Components\Select::make('product_extras_ids')
                                    ->label(__('fields.product_extras'))
                                    ->live()
                                    ->default(function (Get $get) {
                                        return ProductExtra::findMany($get('available_product_extras_ids'))->pluck('name', 'id');
                                    })
                                    ->options(function (Get $get) {
                                        return ProductExtra::findMany($get('available_product_extras_ids'))->pluck('name', 'id');
                                    })
                                    ->afterStateUpdated(function (Get $get, $livewire) {
                                        self::updateInvoicePropertiesFromLivewire($livewire, true);
                                    })
                                    ->suffix(function (Get $get) {
                                        $exts = ProductExtra::findMany($get('product_extras_ids'));
                                        return format_amount(PricingService::instance()->getItemsPrices($exts));
                                    })
                                    ->multiple(),

//                                TextInput::make('extras')
//                                    ->label(__('fields.product_extras'))
//                                    ->suffix(function (Get $get) {
//                                        if ($extras_total = $get('extras_total')) {
//                                            return format_amount($extras_total);
//                                        }
//                                    })
//                                    ->dehydrated(false)
//                                    ->readOnly(),

                                TextInput::make('qty')
                                    ->live(true)
                                    ->label(__('fields.qty'))
                                    ->numeric()
                                    ->extraInputAttributes(['min' => 1, 'max' => 250000], true)
                                    ->minValue(1)
                                    ->maxValue(250000)
                                    ->afterStateUpdated(function ($livewire) {
                                        self::updateInvoicePropertiesFromLivewire($livewire);
                                    })
                                    ->helperText(function ($livewire, $state, Get $get) {
                                        if ($invoiceItem = InvoiceItem::find($get('id')) and $invoiceItem->qty_returned > 0) {
                                            $msg = app()->getLocale() == "en" ? "returned $invoiceItem->qty_returned" : "تم إرجاع $invoiceItem->qty_returned";
                                            return $msg;
                                        }
                                    })
                                    ->translateFrontValidationGt()
                                    ->required(),

                                TextInput::make('price')
                                    ->live(true)
                                    ->label(__('fields.sale_price'))
                                    ->numeric()
                                    ->extraInputAttributes(['min' => 1, 'max' => PHP_INT_MAX], true)
                                    ->minValue(1)
                                    ->maxValue(PHP_INT_MAX)
                                    ->afterStateUpdated(function (Set $set, Get $get, $state, $livewire) {
                                        self::updateInvoicePropertiesFromLivewire($livewire);
                                    })
                                    ->translateFrontValidationGt()
                                    ->required(),


                                TextInput::make('discount')
                                    ->readOnly(fn(Forms\Get $get) => $get('data.discount_option_overall', true) === true)
                                    ->label(__('fields.discount_amount'))
                                    ->numeric()
                                    ->default(0)
                                    ->extraInputAttributes(['min' => 0, 'max' => PHP_INT_MAX])
                                    ->live(true)
                                    ->afterStateUpdated(function (Set $set, Get $get, $livewire) {
                                        self::updateInvoicePropertiesFromLivewire($livewire);
                                    }),

                                Select::make('tax_profile_id')
                                    ->label(__('fields.tax'))
                                    ->placeholder('غير خاضع للضريبة')
                                    ->live()
                                    ->afterStateUpdated(function ($state, $livewire, Set $set) {
                                        self::updateInvoicePropertiesFromLivewire($livewire);

                                        if ($state) {
                                            $set('tax_profile_data', json_encode(TaxProfile::with('taxes')->find($state)->toArray()));
                                        }
                                    })
                                    ->options(TaxProfile::asOptions())
                                    ->searchable(),

                                TextInput::make('tax')
                                    ->label(__('fields.tax'))
                                    ->readOnly(),


                                TextInput::make('sub_total')
                                    ->label(__('fields.sub_total'))
                                    ->readOnly()
                                    ->dehydrated(false),

                            ])
                    ]),

                Forms\Components\Toggle::make('prices_includes_taxes')
                    ->default(true)
                    ->label(__('fields.prices_includes_taxes'))
                    ->live()
                    ->afterStateUpdated(fn($livewire) => self::updateInvoicePropertiesFromLivewire($livewire)),

                Forms\Components\Section::make(__('fields.services'))
                    ->collapsible()
                    ->disabled($form->getRecord()?->locked_at !== null)
                    ->schema([
                        Repeater::make('services')
                            ->label('')
                            ->relationship('services')
                            ->afterStateUpdated(function ($livewire) {
                                self::updateInvoicePropertiesFromLivewire($livewire);
                            })
                            ->afterStateHydrated(function ($livewire) {
                                self::updateInvoicePropertiesFromLivewire($livewire);
                            })
                            ->schema([

                                hidden_tenant_id_field(),

                                Forms\Components\Hidden::make('tax_profile_data'),

                                Select::make('service_type_id')
                                    ->label(__('fields.service_type'))
                                    ->required()
                                    ->options(ServiceType::pluck('name', 'id'))
                                    ->createOptionForm([
                                        Forms\Components\Section::make(__('fields.service_types'))
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label(__('fields.name'))
                                                    ->required()
                                                    ->autofocus()
                                                    ->rules([new UniqueTenantItemRule(ServiceType::class, 'name')]),
                                            ])
                                    ])
                                    ->createOptionUsing(function ($data) {
                                        $model = new ServiceType();

                                        $model->tenant_id = filament()->getTenant()->id;
                                        $model->name = $data['name'];
                                        $model->save();

                                        return $model->id;
                                    })
                                    ->createOptionAction(
                                        fn(Forms\Components\Actions\Action $action) => $action->modalWidth('md'),
                                    )
                                    ->searchable(),

                                Select::make('tax_profile_id')
                                    ->live()
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
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Set $set, $livewire) {
//                                        $prices_includes_taxes = $get('data.prices_includes_taxes', true) ?? true;
//                                        $tax = 0;
//                                        if ($taxProfile = TaxProfile::with('taxes')->find($state) and $price = $get('price')) {
//                                            $set('tax_profile_data', $taxProfile->toArray());
//                                            $tax = MathService::instance()->getTaxFromTaxProfile($price, $taxProfile, $prices_includes_taxes);
//                                            $set('total', number_format($price + $tax, currency_decimals(), '.', ''));
//                                            $set('tax', number_format($tax, currency_decimals(), '.', ''));
//                                        } else {
//                                            $set('tax_profile_data', null);
//                                            if ($price = $get('price')) {
//                                                $set('tax', number_format($tax, currency_decimals(), '.', ''));
//                                            }
//                                        }

                                        self::updateInvoicePropertiesFromLivewire($livewire);
                                    })
                                    ->createOptionAction(
                                        fn(Forms\Components\Actions\Action $action) => $action->modalWidth('5xl'),
                                    ),

                                TextInput::make('price')
                                    ->live(true)
                                    ->label(__('fields.price'))
                                    ->numeric()
                                    ->extraInputAttributes(['min' => 1, 'max' => PHP_INT_MAX])
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Set $set, $livewire) {
                                        self::updateInvoicePropertiesFromLivewire($livewire);
                                    })
                                    ->currency()
                                    ->required(),

                                TextInput::make('tax')
                                    ->readOnly()
                                    ->label(__('fields.tax'))
                                    ->dehydrated(false)
                                    ->readOnly(),

                                TextInput::make('total')
                                    ->readOnly()
                                    ->label(__('fields.total'))
                                    ->dehydrated(false)
                                    ->readOnly(),

                                TextInput::make('description')
                                    ->label(__('fields.description'))
                                    ->required()
                                    ->columnSpan(2),

                            ])
                            ->addActionLabel(__('fields.add'))
                            ->grid(1)
                            ->defaultItems(0)
                            ->columns(7),
                    ]),

                Forms\Components\Section::make(__('fields.additional_costs'))
                    ->collapsible()
                    ->disabled($form->getRecord()?->locked_at !== null)
                    ->schema([
                        Repeater::make('additional_costs')
                            ->label('')
                            ->relationship('additionalCosts')
                            ->afterStateUpdated(function ($livewire) {
                                self::updateInvoicePropertiesFromLivewire($livewire);
                            })
                            ->afterStateHydrated(function ($livewire) {
                                self::updateInvoicePropertiesFromLivewire($livewire);
                            })
                            ->schema([

                                hidden_tenant_id_field(),

                                Forms\Components\Hidden::make('tax_profile_data'),

                                Select::make('additional_cost_type_id')
                                    ->label(__('fields.invoice_additional_cost_type'))
                                    ->required()
                                    ->options(AdditionalCostType::pluck('name', 'id'))
                                    ->createOptionForm([
                                        Forms\Components\Section::make(__('fields.invoice_additional_cost_type'))
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label(__('fields.name'))
                                                    ->required()
                                                    ->autofocus()
                                                    ->rules([new UniqueTenantItemRule(AdditionalCostType::class, 'name')]),
                                            ])
                                    ])
                                    ->createOptionUsing(function ($data) {
                                        $model = new AdditionalCostType();

                                        $model->tenant_id = filament()->getTenant()->id;
                                        $model->name = $data['name'];
                                        $model->save();

                                        return $model->id;
                                    })
                                    ->createOptionAction(
                                        fn(Forms\Components\Actions\Action $action) => $action->modalWidth('md'),
                                    )
                                    ->searchable(),

                                Select::make('tax_profile_id')
                                    ->live()
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
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Set $set, $livewire) {
                                        self::updateInvoicePropertiesFromLivewire($livewire);
                                    })
                                    ->createOptionAction(
                                        fn(Forms\Components\Actions\Action $action) => $action->modalWidth('5xl'),
                                    ),

                                TextInput::make('cost')
                                    ->live(true)
                                    ->label(__('fields.cost'))
                                    ->numeric()
                                    ->extraInputAttributes(['min' => 1, 'max' => PHP_INT_MAX])
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Set $set, $livewire) {
                                        self::updateInvoicePropertiesFromLivewire($livewire);
                                    })
                                    ->currency()
                                    ->required(),

                                TextInput::make('tax')
                                    ->readOnly()
                                    ->label(__('fields.tax'))
                                    ->dehydrated(false)
                                    ->readOnly(),

                                TextInput::make('total')
                                    ->readOnly()
                                    ->label(__('fields.total'))
                                    ->dehydrated(false)
                                    ->readOnly(),

                                TextInput::make('statement')
                                    ->label(__('fields.statement'))
                                    ->required()
                                    ->columnSpan(2),

                            ])
                            ->addActionLabel(__('fields.add'))
                            ->grid(1)
                            ->defaultItems(0)
                            ->columns(7),
                    ]),

                Forms\Components\Section::make(__('fields.discounts'))
                    ->disabled($form->getRecord()?->locked_at !== null)
                    ->collapsible()
                    ->schema([

                        Forms\Components\Hidden::make('discount_option')->default('per-item'),

                        Forms\Components\Toggle::make('discount_option_overall')
                            ->dehydrated(false)
                            ->label(__('fields.discount_per_invoice'))
                            ->live()
                            ->default(false)
                            ->afterStateUpdated(function ($state, Set $set, $livewire) {

                                $set('total_purchases_post_discount', null);
                                $set('total_invoice_post_discount', null);

                                if ($state) //true
                                {
                                    $set('discount_option', 'overall');
                                } else {
                                    $set('discount_option', 'per-item');
                                    $set('discount_method', null);
                                    $set('discount_amount', null);
                                    $set('discount_percent', null);
                                    $newItems = [];
                                    foreach ($livewire->data['items'] as $item) {
                                        $item['discount'] = 0;
                                        $newItems[] = $item;
                                    }
                                    $livewire->data['items'] = $newItems;
                                }

                                self::updateInvoicePropertiesFromLivewire($livewire);
                            }),

                        Forms\Components\Radio::make('discount_method')
                            ->visible(fn(Forms\Get $get) => $get('discount_option_overall') === true)
                            ->required()
                            ->label(__('fields.discount_method'))
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, $livewire) {

                                if ($state == "amount")
                                    $set('discount_percent', null);

                                if ($state == "percent")
                                    $set('discount_amount', null);

                                self::updateInvoicePropertiesFromLivewire($livewire);

                            })
                            ->options([
                                'amount' => __('fields.discount_by_amount'),
                                'percent' => __('fields.discount_by_percent'),
                            ]),

                        TextInput::make('discount_amount')
                            ->visible(fn(Forms\Get $get) => $get('discount_option_overall') == true and $get('discount_method') == "amount")
                            ->required()
                            ->label(__('fields.discount_amount'))
                            ->numeric()
                            ->extraInputAttributes(['min' => 1, 'max' => PHP_INT_MAX])
                            ->required()
                            ->live(true)
                            ->afterStateUpdated(function ($state, Get $get, Set $set, $livewire) {
                                self::updateInvoicePropertiesFromLivewire($livewire);
                            })
                            ->currency(),

                        TextInput::make('discount_percent')
                            ->visible(fn(Forms\Get $get) => $get('discount_option_overall') == true and $get('discount_method') == "percent")
                            ->label(__('fields.discount_percent'))
                            ->numeric()
                            ->extraInputAttributes(['min' => 1, 'max' => 100])
                            ->suffix("%")
                            ->live(true)
                            ->afterStateUpdated(function ($state, Get $get, Set $set, $livewire) {
                                self::updateInvoicePropertiesFromLivewire($livewire);
                            })->currency()
                            ->required(),


                    ])
                    ->columns(5),

                Forms\Components\Section::make()
                    ->disabled($form->getRecord()?->locked_at !== null)
                    ->schema([

                        Forms\Components\Placeholder::make('total_invoice_pre_discount_pre_tax')
                            ->label(__('fields.total'))
                            ->dehydrated(false)
                            ->content(function ($livewire) {
                                $value = $livewire->data['total_invoice_pre_discount_pre_tax'];
                                return new HtmlString("<h3 style='color: #0464ff;font-weight: bold'>$value</h3>");
                            }),

                        Forms\Components\Placeholder::make('total_discount')
                            ->label(__('fields.discount'))
                            ->dehydrated(false)
                            ->content(function ($livewire) {
                                $value = $livewire->data['total_discount'];
                                return new HtmlString("<h3 style='color: #ff1815;font-weight: bold'>$value</h3>");
                            }),

                        Forms\Components\Placeholder::make('total_invoice_post_discount')
                            ->label(__('fields.total_invoice_net_post_discount'))
                            ->dehydrated(false)
                            ->content(function ($livewire) {
                                $value = $livewire->data['total_invoice_post_discount'];
                                return new HtmlString("<h3 style='color: #0464ff;font-weight: bold'>$value</h3>");
                            }),

                        Forms\Components\Placeholder::make('total_taxes')
                            ->label(__('fields.tax'))
                            ->dehydrated(false)
                            ->content(function ($livewire) {
                                $value = $livewire->data['total_taxes'];
                                return new HtmlString("<h3 style='color: #0464ff;font-weight: bold'>$value</h3>");
                            }),

                        Forms\Components\Placeholder::make('total_invoice_with_taxes')
                            ->label(__('fields.invoice_total_with_tax'))
                            ->dehydrated(false)
                            ->helperText(function ($livewire) {
                                $value = $livewire->data['total_invoice_with_taxes'];
                                $value = numbers_to_words($value);
                                return new HtmlString("<h3 style='color: #ff3e3e;font-weight: bolder'>$value</h3>");
                            })
                            ->content(function ($livewire) {
                                $value = $livewire->data['total_invoice_with_taxes'];
                                return new HtmlString("<h3 style='color: #0464ff;font-weight: bolder'>$value</h3>");
                            }),

                    ])->columns(5),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->emptyStateHeading(__('fields.table_empty_state'))
            ->columns([
                Tables\Columns\TextColumn::make('no')
                    ->label(__('fields.invoice_no'))
                    ->description(function (Invoice $record) {
                        $order = Order::where('invoice_id', $record->id)->first();

                        if ($order) {
                            return "Order No: $order->no";
                        }
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('fields.client'))
                    ->url(function ($record) {
                        return CustomerResource::getUrl('edit', ['record' => $record->customer_id]);
                    }, true)
                    ->color(Color::Sky)
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('fields.status'))
                    ->badge()
                    ->getStateUsing(function ($record) {
                        return __("fields.invoice_status_" . $record->status);
                    })
                    ->color(function (Invoice $record, $state) {
                        return match ($record->status) {
                            'sale_order' => 'gray',
                            'cancelled' => 'danger',
                            'confirmed' => 'success',
                            default => 'warning',
                        };
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('date')
                    ->label(__('fields.date'))
                    ->dateTime('M j, Y')
                    ->searchable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label(__('fields.paid_amount'))
                    ->getStateUsing(function ($record) {
                        return main_currency_iso_code() . " " . format_amount($record->total_paid);
                    })
                    ->description(function (Invoice $record) {
                        return format_amount(percent($record->total_paid, $record->getItemsCost(true, true, true))) . "%";
                    })
                    ->tooltip(function (Invoice $record) {
                        return numbers_to_words($record->total_paid);
                    })
                    ->summarize(Tables\Columns\Summarizers\Summarizer::make()
                        ->label(__('fields.total'))
                        ->using(function (Table $table) {
                            return main_currency_iso_code() . " " . format_amount($table->getRecords()->sum('total_paid'));
                        })
                    ),

//                Tables\Columns\TextColumn::make('paid_amount_percent')
//                    ->extraAttributes(function ($record) {
//                        if (percent($record->total_paid, $record->getItemsCost(true, true, true)) > 0) {
//                            return ['class' => 'text-success-700'];
//                        }
//
//                        return ['class' => 'text-danger-700'];
//                    })
//                    ->label(__('fields.paid_amount_percent'))
//                    ->getStateUsing(function ($record) {
//                        return format_amount(percent($record->total_paid, $record->getItemsCost(true, true, true))) . "%";
//                    }),

                Tables\Columns\TextColumn::make('services')
                    ->label(__('fields.services'))
                    ->toggleable()
                    ->getStateUsing(function ($record) {
                        return main_currency_iso_code() . " " . format_amount($record->getServicesCost(true));
                    })
                    ->tooltip(function (Invoice $record) {
                        return numbers_to_words($record->getServicesCost(true));
                    })
                    ->summarize(Tables\Columns\Summarizers\Summarizer::make()
                        ->label(__('fields.total'))
                        ->using(function (Table $table) {
                            return main_currency_iso_code() . " " . format_amount($table->getRecords()->sum('services_cost'));
                        })
                    ),

                Tables\Columns\TextColumn::make('additional_costs')
                    ->label(__('fields.additional_costs'))
                    ->toggleable()
                    ->getStateUsing(function ($record) {
                        return main_currency_iso_code() . " " . format_amount($record->getAdditionalCosts(true));
                    })
                    ->tooltip(function (Invoice $record) {
                        return numbers_to_words($record->getAdditionalCosts(true));
                    })
                    ->summarize(Tables\Columns\Summarizers\Summarizer::make()
                        ->label(__('fields.total'))
                        ->using(function (Table $table) {
                            return main_currency_iso_code() . " " . format_amount($table->getRecords()->sum('additional_cost'));
                        })
                    ),


                Tables\Columns\TextColumn::make('invoice_total')
                    ->label(__('fields.invoice_total'))
                    ->color(Color::Violet)
                    ->tooltip(function ($record) {
                        return numbers_to_words($record->getItemsCost(true, true, true));
                    })
                    ->getStateUsing(function ($record) {
                        return format_amount($record->getItemsCost(true, true, true));
                    })
                    ->summarize(Tables\Columns\Summarizers\Summarizer::make()
                        ->label(__('fields.total'))
                        ->using(function (Table $table) {
                            return main_currency_iso_code() . " " . format_amount($table->getRecords()->sum('items_cost'));
                        })
                    ),
            ])
            ->groups([
//                Tables\Grouping\Group::make('status')
//                    ->getTitleFromRecordUsing(fn(Invoice $record): string => __("fields.invoice_status_" . $record->status))
//                    ->label(__('fields.status')),

                Tables\Grouping\Group::make('customer.name')
                    ->label(__('fields.client')),

                Tables\Grouping\Group::make('created_at')
                    ->getTitleFromRecordUsing(fn(Invoice $record): string => $record->created_at->format('d-m-Y'))
                    ->label(__('fields.date')),
            ])
            ->filters([

                Tables\Filters\Filter::make('created_at')
                    ->columnSpanFull()
                    ->label(__('fields.created_at'))
                    ->form([

                        Select::make('status')
                            ->label(__('fields.status'))
                            ->multiple()
                            ->options([
                                'sale_order' => __('fields.invoice_status_sale_order'),
                                'cancelled' => __('fields.invoice_status_cancelled'),
                                'confirmed' => __('fields.invoice_status_confirmed'),
                            ]),

                        Select::make('customers')
                            ->label(__('fields.client'))
                            ->multiple()
                            ->options(Customer::pluck('name', 'id')),

                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('fields.created_from')),

                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('fields.created_until')),
                    ])
                    ->indicateUsing(function (array $data): ?string {
                        $indicator = null;
                        if ($data['created_from'] or $data['created_until']) {
                            $indicator = $indicator . __('fields.date');
                        }
                        if ($data['customers']) {
                            $indicator = $indicator . __('fields.client');
                        }
                        if ($data['status']) {
                            $indicator = $indicator . __('fields.status');
                        }
                        return $indicator;
                    })
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['customers'],
                                fn(Builder $query, $customers): Builder => $query->whereIn('customer_id', $customers),
                            )
                            ->when(
                                $data['status'],
                                fn(Builder $query, $status): Builder => $query->whereIn('status', $status),
                            )
                            ->when($data['created_from'],
                                fn($query) => $query->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'],
                                fn($query) => $query->whereDate('created_at', '<=', $data['created_until']));
                    })
            ])
            ->actions([

//                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('invoice_url')
                    ->label(__('fields.invoice_url'))
                    ->color(Color::Sky)
                    ->url(fn(Invoice $record) => $record->url, true),

                Tables\Actions\Action::make('status')
                    ->visible(function ($record) {
                        return $record->locked_at == null;
                    })
                    ->color('warning')
                    ->icon('heroicon-o-pencil-square')
                    ->label(__('fields.change_status'))
                    ->modalWidth('lg')
                    ->requiresConfirmation()
                    ->fillForm(function (Invoice $record) {
                        return [
                            'current_status' => __('fields.invoice_status_' . $record->status),
                        ];
                    })
                    ->form([
                        Forms\Components\Section::make([

                            Forms\Components\TextInput::make('no')
                                ->label("")
                                ->formatStateUsing(fn($record) => $record->no)
                                ->readOnly()
                                ->dehydrated(false),

                            Forms\Components\TextInput::make('to')
                                ->label("")
                                ->formatStateUsing(fn($record) => $record->getInvoicePerson())
                                ->readOnly()
                                ->dehydrated(false),


                            TextInput::make('current_status')
                                ->label(__('fields.current_status'))
                                ->dehydrated(false)
                                ->readOnly(),

                            Forms\Components\Select::make('status')
                                ->label(__('fields.change_status_to'))
                                ->default(null)
                                ->live()
                                ->options([
                                    'confirmed' => __('fields.invoice_status_confirmed'),
                                    'cancelled' => __('fields.invoice_status_cancelled'),
                                ]),

                            Forms\Components\Placeholder::make('info')
                                ->visible(function (Get $get) {
                                    $status = $get('status');
                                    return ($status == "confirmed" or $status == "cancelled");
                                })
                                ->label(function () {
                                    $msg = __("fields.invoice_will_be_locked_after_this_action");
                                    return new HtmlString("<strong style='color: #ff301d;'> $msg </strong>");
                                }),
                        ])
                    ])
                    ->action(function (Invoice $record, array $data) {

                        if (!can_lock_invoice()) {
                            fns()->persist(true)->sendWarning(__('fields.insufficient_permission'));
                            return;
                        }

                        if ($record->locked_at) {
                            fns()->sendWarning(__('fields.invoice_locked_edit_disabled'));
                            return;
                        }

                        try {

                            DB::beginTransaction();

                            if ($data['status'] == "confirmed") {
                                StockService::instance()->takeStockFromSalesInvoice($record);
                                $record->update(['status' => $data['status'], 'locked_at' => now()]);

                                $tax = $record->items->sum('tax');
                                if ($tax > 0) {
                                    $op = make_taxes_op();
                                    $accService = new AccountingService();
                                    $accService
                                        ->setUp(
                                            $op->id,
                                            now(),
                                            main_currency_iso_code(),
                                            generate_double_entry_transaction_id(),
                                            $tax,
                                            null,
                                            'Invoice items taxes',
                                            'Invoice items taxes',
                                            $record->id,
                                            meta: ['type' => 'sales_invoice', 'id' => $record->id],
                                        )->make('120100001', '122800001')
                                        ->finish();
                                }

                                foreach ($record->services as $service) {
                                    $service_tax = MathService::instance()->getTaxFromTaxProfile($service->price, $service->taxProfile, false);

                                    if ($service_tax > 0) {
                                        $op = make_taxes_op();
                                        $accService = new AccountingService();
                                        $accService
                                            ->setUp(
                                                $op->id,
                                                now(),
                                                main_currency_iso_code(),
                                                generate_double_entry_transaction_id(),
                                                $service_tax,
                                                null,
                                                'Service tax',
                                                'Service tax',
                                                null,
                                                meta: ['type' => 'service', 'id' => $service->id],
                                            )->make('120100001', '122800001')
                                            ->finish();
                                    }
                                }
                                fns()->sendSuccess(__('fields.invoice_updated'));
                            } else {
                                $record->update(['status' => $data['status']]);
                            }

                            DB::commit();

                        } catch (\Exception $exception) {
                            DB::rollBack();
                            fns()->displayException($exception);
                        }

                    }),

                Tables\Actions\Action::make('complete_payment')
                    ->label(__('fields.complete_payment'))
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->visible(function ($record) {
                        return !$record->paid;
                    })
                    ->action(function (Invoice $record) {
                        if ($record->salesPayments->isEmpty()) {
                            return redirect(ReceiptVoucherResource::getUrl('create', ['invoice_id' => $record->id]));
                        }

                        $rv = ReceiptVoucher::whereInvoiceId($record->id)->first();

                        if ($rv)
                            return redirect(ReceiptVoucherResource::getUrl('edit', ['record' => $rv->id, 'rv' => $rv->id]));

                    }),
            ])
            ->bulkActions([
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesInvoices::route('/'),
            'create' => Pages\CreateSalesInvoice::route('/create'),
            'edit' => Pages\EditSalesInvoice::route('/{record}/edit'),
//            'view' => Pages\ViewSalesInvoice::route('/{record}/view'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->sales()
            ->where('temp', false)
            ->with(
                [
                    'items.orderDetails.orderDetailsExtras.productExtra.extra',
                    'items.product',
                    'items.extras',
                    'items.productVariant',
                    'salesPayments',
                    'customer',
                    'receiptVoucher',
                    'representative',
                    'client',
                    'user',
                    'reviewedBy',
                    'additionalCosts',
                ])->latest();
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        $record = $infolist->getRecord();

        return $infolist
            ->schema([

                Section::make()->schema([

                    TextEntry::make('no')
                        ->label(__('fields.invoice_no')),

                    TextEntry::make('order_no')
                        ->label(__('fields.order_no'))
                        ->getStateUsing(function () use ($record) {
                            $order = Order::firstWhere('invoice_id', $record->id);
                            if ($order) {
                                return $order->no;
                            }
                        }),

                    TextEntry::make('status')
                        ->label(__("fields.status"))
                        ->getStateUsing(function () use ($record) {
                            return __("fields.invoice_status_$record->status");
                        }),

                    TextEntry::make('customer.name')
                        ->url(function ($record) {
                            return CustomerResource::getUrl('edit', ['record' => $record->customer_id]);
                        }, true)
                        ->color(Color::Sky)
                        ->label(__('fields.client')),

                    TextEntry::make('created_at')
                        ->label(__('fields.date')),

                    TextEntry::make('invoice_total')
                        ->label(__('fields.invoice_total'))
                        ->getStateUsing(function ($record) {
                            return main_currency_iso_code() . " " . format_amount($record->getItemsCost(true, true, true));
                        }),

                    TextEntry::make('additional_costs')
                        ->label(__('fields.additional_costs'))
                        ->getStateUsing(function ($record) {
                            return main_currency_iso_code() . " " . format_amount($record->getAdditionalCosts());
                        }),

                    TextEntry::make('paid_amount')
                        ->label(__("fields.paid_amount"))
                        ->getStateUsing(function () use ($record) {
                            return main_currency_iso_code() . " " . format_amount($record->total_paid);
                        }),

                    TextEntry::make('paid_amount_percent')
                        ->label(__("fields.paid_amount_percent"))
                        ->getStateUsing(function () use ($record) {
                            return format_amount(percent($record->total_paid, $record->getItemsCost(true, true, true))) . "%";
                        }),

                ])->columns(3),

                Section::make()->schema([
                    RepeatableEntry::make('items')
                        ->label(__('fields.items'))
                        ->schema([
                            TextEntry::make('name')
                                ->label(__('fields.product'))
                                ->getStateUsing(function ($record) {
                                    if ($record->product_variant_id) {
                                        return ProductVariant::find($record->product_variant_id)->name;
                                    }
                                    return $record->product->name;
                                }),

                            TextEntry::make('product_extras')
                                ->label(__('fields.product_extras'))
                                ->getStateUsing(function ($record) {
                                    $names = [];
                                    foreach ($record->orderDetails?->orderDetailsExtras ?? [] as $orderDetailsExtra) {
                                        $names[] = $orderDetailsExtra->productExtra->extra->name . " (" . format_amount($orderDetailsExtra->unit_price) . " " . main_currency_iso_code() . ")";
                                    }
                                    return implode(', ', $names);
                                }),


                            TextEntry::make('price')
                                ->label(__("fields.price"))
                                ->getStateUsing(function ($record) {
                                    return main_currency_iso_code() . " " . format_amount($record->price);
                                }),

                            TextEntry::make('taxes')
                                ->label(__("fields.taxes"))
                                ->getStateUsing(function ($record) {
                                    return main_currency_iso_code() . " " . format_amount($record->getTaxesAsAmount());
                                }),

                            TextEntry::make('qty')
                                ->label(__('fields.qty'))
                                ->helperText(function ($record) {
                                    if ($record and $record->qty_returned > 0) {
                                        $msg = app()->getLocale() == "en" ? "returned $record->qty_returned" : "تم إرجاع $record->qty_returned";
                                        return $msg;
                                    }
                                }),

                            TextEntry::make('sub_total')
                                ->label(__("fields.sub_total"))
                                ->getStateUsing(function ($record) {
                                    $price = $record->qty * $record->price;
                                    $extras = 0;
                                    $discount = 0;
                                    if ($record->orderDetails) {
                                        $extras = $record->orderDetails->orderDetailsExtras->sum('unit_price');
                                        $discount = $record->orderDetails->orderDetailsExtras->sum('discount');
                                    }

                                    $taxes = $record->getTaxesAsAmount();
                                    return main_currency_iso_code() . " " . format_amount($price + $extras + $taxes - $discount);
                                }),
                        ])
                        ->columns(6),
                ]),

                Section::make()->schema([
                    RepeatableEntry::make('additionalCosts')
                        ->label(__('fields.additional_costs'))
                        ->schema([
                            TextEntry::make('type.name')
                                ->label(__('fields.type'))
                                ->getStateUsing(function ($record) {
                                    return $record->type->name;
                                }),

                            TextEntry::make('statement')
                                ->label(__('fields.statement')),

                            TextEntry::make('cost')
                                ->label(__("fields.cost"))
                                ->getStateUsing(function ($record) {
                                    return main_currency_iso_code() . " " . format_amount($record->cost);
                                }),
                        ])
                        ->columns(3),
                ]),


            ]);
    }

    public static function updateInvoicePropertiesFromLivewire($livewire, $updateUIFields = true): array
    {

        $startTime = microtime(true);

        self::updateItems($livewire);

        $prices_includes_taxes = $livewire->data['prices_includes_taxes'] ?? true;

        $items = $livewire->data['items'] ?? [];
        $services = $livewire->data['services'] ?? [];
        $additionalCosts = $livewire->data['additional_costs'] ?? [];
        $discountOption = $livewire->data['discount_option'] ?? [];
        $discountMethod = $livewire->data['discount_method'] ?? null;
        $discountAmount = $livewire->data['discount_amount'] ?? null;
        $discountPercent = $livewire->data['discount_percent'] ?? [];

        $totals = [
            'total_purchases' => 0,
            'total_additional_costs' => 0,
            'total_services' => 0,
            'total_discount' => 0,
            'total_taxes' => 0,
            'execution_time' => 0,
        ];

        $taxProfiles = CacheService::instance()->remember('taxProfiles', 5 * 60, function () {
            return TaxProfile::all();
        });

        foreach ($items as $item) {

            $price = $item['price'] ?? 0;
            $qty = $item['qty'] ?? 0;
            $extras_total = count($item['product_extras_ids'] ?? []) > 0 ? PricingService::instance()->getItemsPrices(ProductExtra::findMany($item['product_extras_ids'])) : 0;
            $discount = $item['discount'] ?? 0;
            $tax = 0;

            if (is_number($qty))
                $extras_total = $extras_total * $qty;

            if (is_number($qty) and is_number($price))
                $totals['total_purchases'] += $qty * $price;

            $totals['total_purchases'] += $extras_total;

            if ($discountOption == "per-item" and is_number($discount))
                $totals['total_discount'] += $discount;

            $taxProfileId = $item['tax_profile_id'] ?? null;

            if ($taxProfileId and is_number($price) and is_number($qty)) {
                $taxProfile = $taxProfiles->where('id', $taxProfileId)->first();

                if (!$taxProfile)
                    $taxProfile = TaxProfile::find($taxProfileId);

                if ($taxProfile) {
                    $sub_total = ($price * $qty) + $extras_total;
                    $original_sub_total = ($price * $qty) + $extras_total;

                    if (is_number($discount))
                        $sub_total -= $discount;

                    $tax = MathService::instance()->getTaxFromTaxProfile($sub_total, $taxProfile, $prices_includes_taxes);
                    $totals['total_taxes'] += $tax;
                }
            }

        }

        foreach ($services as $service) {
            $price = $service['price'] ?? 0;
            $totals['total_services'] += $price;

            $taxProfileId = $service['tax_profile_id'] ?? null;
            $taxProfile = TaxProfile::find($taxProfileId);

            if ($taxProfile) {
                $totals['total_taxes'] += MathService::instance()->getTaxFromTaxProfile($price, $taxProfile, $prices_includes_taxes);
            }
        }

        foreach ($additionalCosts as $additionalCost) {
            $cost = $additionalCost['cost'] ?? 0;
            $totals['total_additional_costs'] += $cost;

            $taxProfileId = $additionalCost['tax_profile_id'] ?? null;
            $taxProfile = TaxProfile::find($taxProfileId);

            if ($taxProfile) {
                $totals['total_taxes'] += MathService::instance()->getTaxFromTaxProfile($cost, $taxProfile, $prices_includes_taxes);
            }
        }

        if ($discountOption == "overall" and $discountMethod == "amount" and is_number($discountAmount))
            $totals['total_discount'] = $discountAmount;

        if ($discountOption == "overall" and $discountMethod == "percent" and is_number($discountPercent)) {
//            20/100 = 0.2
            $discountInAmount = $totals['total_purchases'] * ($discountPercent / 100);

            $totals['total_discount'] = number_format($discountInAmount, currency_decimals(), '.', '');

        }

//        dd($totals['total_discount']);
        if ($updateUIFields) {
            $livewire->data['total_invoice_pre_discount_pre_tax'] = format_amount($totals['total_purchases'] + $totals['total_services'] + $totals['total_additional_costs']);
            $livewire->data['total_discount'] = format_amount($totals['total_discount']);
            $livewire->data['total_taxes'] = format_amount($totals['total_taxes']);
            $livewire->data['total_invoice_post_discount'] = format_amount($totals['total_purchases'] + $totals['total_services'] + $totals['total_additional_costs'] - $totals['total_discount']);
            $livewire->data['total_invoice_with_taxes'] = format_amount($totals['total_purchases'] + $totals['total_services'] + $totals['total_additional_costs'] - $totals['total_discount'] + $totals['total_taxes']);
        }

        $endTime = microtime(true);

        $totals['execution_time'] = $endTime - $startTime;

        return $totals;
    }

    public static function updateItems($livewire)
    {

        $taxProfiles = CacheService::instance()->remember('taxProfiles', 5 * 60, function () {
            return TaxProfile::all();
        });

        $prices_includes_taxes = $livewire->data['prices_includes_taxes'] ?? true;

        $discountOption = $livewire->data['discount_option'] ?? null;
        $discountMethod = $livewire->data['discount_method'] ?? null;
        $discountAmount = 0;

        if ($livewire->data['discount_amount'] ?? null > 0)
            $discountAmount = $livewire->data['discount_amount'];

        if ($livewire->data['discount_percent'] ?? null > 0)
            $discountAmount = $livewire->data['discount_percent'];


        $newItems = [];
        foreach ($livewire->data['items'] ?? [] as $item) {

            $extras_total = count($item['product_extras_ids'] ?? []) > 0 ? PricingService::instance()->getItemsPrices(ProductExtra::findMany($item['product_extras_ids'])) : 0;
            $price = $item['price'] ?? null;
            $qty = $item['qty'] ?? null;
            $tax = 0;

            if (is_number($qty))
                $extras_total = $extras_total * $qty;

            if ($discountOption == "overall") {
                if ($discountMethod == "percent") {
                    if (is_number($price) and is_number($qty)) {
                        $amountFromPercent = ($price * $qty) * ($discountAmount / 100);
                        $item['discount'] = number_format($amountFromPercent, currency_decimals(), '.', '');
                    } else {
                        $item['discount'] = -1;
                    }

                } else {
                    $item['discount'] = is_number($discountAmount) ? number_format($discountAmount, currency_decimals(), '.', '') : null;
                }
            }


            //set sub_total
            if (is_number($price) and is_number($qty)) {
                $discount = $item['discount'] ?? null;

                if (!$discount or $discount < 0)
                    $discount = 0;

                $subTotal = ($price * $qty) + $extras_total;
                $original_sub_total = ($price * $qty) + $extras_total;

                $subTotal -= $discount;

                if (is_number($item['tax_profile_id'] ?? null)) {
                    $taxProfileId = $item['tax_profile_id'];

                    $taxProfile = $taxProfiles->where('id', $taxProfileId)->first();

                    if (!$taxProfile)
                        $taxProfile = TaxProfile::find($taxProfileId);

                    if ($taxProfile) {
                        $tax = MathService::instance()->getTaxFromTaxProfile($subTotal, $taxProfile, $prices_includes_taxes);

                        if (!$prices_includes_taxes) {
                            $subTotal += $tax;
                        }
                    }
                }

                $item['tax'] = number_format($tax, currency_decimals(), '.', '');

                $item['sub_total'] = format_amount($subTotal);

            }
            $newItems[] = $item;
        }

        $livewire->data['items'] = $newItems;

        $newServices = [];

        foreach ($livewire->data['services'] ?? [] as $service) {
            $taxProfile = TaxProfile::with('taxes')->find($service['tax_profile_id']);
            $price = $service['price'] ?? null;
            if ($taxProfile and $price) {
                $service['tax_profile_data'] = $taxProfile->toArray();
                $tax = MathService::instance()->getTaxFromTaxProfile($price, $taxProfile, $prices_includes_taxes);
                $service['tax'] = number_format($tax, currency_decimals(), '.', '');
                if ($prices_includes_taxes) {
                    $service['total'] = number_format($price, currency_decimals(), '.', '');
                } else {
                    $service['total'] = number_format($price + $tax, currency_decimals(), '.', '');
                }
            } else {
                $service['tax'] = number_format(0, currency_decimals(), '.', '');
                $service['total'] = number_format($price, currency_decimals(), '.', '');
            }
            $newServices[] = $service;
        }

        $livewire->data['services'] = $newServices;

        $newAdditionalCosts = [];

        foreach ($livewire->data['additional_costs'] ?? [] as $additionalCost) {
            $taxProfile = TaxProfile::with('taxes')->find($additionalCost['tax_profile_id']);
            $cost = $additionalCost['cost'] ?? null;
            if ($taxProfile and $cost) {
                $additionalCost['tax_profile_data'] = $taxProfile->toArray();
                $tax = MathService::instance()->getTaxFromTaxProfile($cost, $taxProfile, $prices_includes_taxes);
                $additionalCost['tax'] = number_format($tax, currency_decimals(), '.', '');
                if ($prices_includes_taxes) {
                    $additionalCost['total'] = number_format($cost, currency_decimals(), '.', '');
                } else {
                    $additionalCost['total'] = number_format($cost + $tax, currency_decimals(), '.', '');
                }
            } else {
                $additionalCost['tax'] = number_format(0, currency_decimals(), '.', '');
                $additionalCost['total'] = number_format($cost, currency_decimals(), '.', '');
            }
            $newAdditionalCosts[] = $additionalCost;
        }

        $livewire->data['additional_costs'] = $newAdditionalCosts;

    }


    protected static function getVariantFieldsBasedOnOptions($product_id, $livewire): array
    {
        $fields = [];

        $product = Product::with(['variants', 'variantOptions'])->find($product_id);

        if ($product->type !== Product::$TYPE_VARIANTS)
            return [];

        $variantOptions = $product->variantOptions;

        foreach ($variantOptions as $variantOption) {
            $lib = $variantOption->library;

            $options = VariantLibraryOption::findMany($variantOption->values);

            $fields[] = Select::make("vo@$lib->id")
                ->required()
                ->label($lib->name)
                ->options($options->pluck('name', 'id'));
        }

        $livewire->mountedFormComponentActionsData[0]['variant_options'] = json_encode($variantOptions->pluck('id')->toArray());

        return $fields;
    }


    protected static function getVariantLibraryFromOption($option_id): VariantLibrary
    {

        $variantLibraries = Cache::remember("variantLibraries@" . \filament()->getTenant()->id, 60, function () {
            return VariantLibrary::with(['options'])->get();
        });

        $vl = $variantLibraries->filter(function ($item) use ($option_id) {
            return in_array($option_id, $item->options->pluck('id')->toArray());
        })->first();

        if (!$vl)
            $vl = VariantLibrary::with(['options'])->whereHas('options', function ($q) use ($option_id) {
                return $q->where('id', $option_id);
            })->first();

        return $vl;
    }

    protected static function getProductExtras($product_id)
    {
        $fields = [];
        foreach (Product::with(['extras.extra', 'extras.prices', 'extras.lastPrice'])->find($product_id)?->extras ?? [] as $productExtra) {
            $price = PricingService::instance()->getRetailPrice($productExtra, null);

            $formattedPrice = is_number($price) ? (main_currency_iso_code() . " " . format_amount($price)) : null;

            $fields[] = Forms\Components\Checkbox::make("px@$productExtra->id")
                ->helperText($formattedPrice)
                ->disabled($price === null or $price == 0)
                ->default(0)
                ->label($productExtra->extra->name);
        }
        return $fields;
    }
}
