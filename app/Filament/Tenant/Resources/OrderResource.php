<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\OrderResource\Pages;
use App\Filament\Tenant\Resources\OrderResource\RelationManagers;
use App\Filament\Tenant\Resources\OrderResource\Widgets\OrderStats;
use App\Models\AdditionalCost;
use App\Models\City;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\PriceOffer;
use App\Models\Product;
use App\Models\ProductExtra;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\ReceiptVoucher;
use App\Models\State;
use App\Models\Supplier;
use App\Models\VariantLibrary;
use App\Models\VariantLibraryOption;
use App\Services\InvoiceService;
use App\Services\PricingService;
use App\Services\StockService;
use Awcodes\Shout\Components\Shout;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Actions\StaticAction;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use function PHPUnit\TestFixture\Generator\f;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $recordTitleAttribute = 'no';

    protected static ?string $slug = "orders";

    protected static ?int $navigationSort = 1;


    public static function getNavigationGroup(): ?string
    {
        return user_setting('fav.orders', false) ? __('fields.navigation_group_favourites') : null;
    }

    public static function getLabel(): ?string
    {
        return __('fields.order');
    }


    public static function getPluralLabel(): ?string
    {
        return __('fields.orders');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::new()->count();
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([

                Forms\Components\Hidden::make('price_offer_id')->dehydrated(false),

                Shout::make('auto-create-inv-from-order-alert')
                    ->visible(fn(Get $get) => $get('price_offer_id') !== null)
                    ->content(__('fields.auto_create_inv_from_order_alert'))
                    ->icon("")
                    ->color(Color::Sky)
                    ->columnSpan(2)
                    ->type('warning'),

                Shout::make('from-price-offer')
                    ->visible(fn(Get $get) => $get('price_offer_id') !== null)
                    ->content(fn(Get $get) => PriceOffer::find($get('price_offer_id'))?->description)
                    ->icon("")
                    ->color(Color::Yellow)
                    ->columnSpan(2)
                    ->type('warning'),

                Forms\Components\Section::make()
                    ->disabled(fn($record) => $record and $record->status == Order::$STATUS_COMPLETED)
                    ->schema([

                        hidden_tenant_id_field(),

                        Select::make('customer_id')
                            ->required()
                            ->label(__('fields.client'))
                            ->searchable()
                            ->options(Customer::pluck('name', 'id'))
                            ->live()
                            ->createOptionForm(CustomerResource::getSchema())
                            ->createOptionUsing(function ($data) {
                                $data['tenant_id'] = filament()->getTenant()->id;
                                $model = Customer::create($data);
                                return $model->id;
                            })
                            ->createOptionAction(
                                fn(Forms\Components\Actions\Action $action) => $action->modalWidth(MaxWidth::ThreeExtraLarge),
                            )->afterStateUpdated(function ($state, Forms\Set $set) {
                                $customer = Customer::find($state);

                                if ($customer) {
                                    $set('delivery_address', $customer->delivery_address);
                                    $set('delivery_address_hint', $customer->location);
                                } else {
                                    $set('delivery_address', null);
                                    $set('delivery_address_hint', null);
                                }
                            }),


                        Forms\Components\Hidden::make('delivery_address_hint')->dehydrated(false),

                        TextInput::make('delivery_address')
                            ->label(__('fields.delivery_address'))
                            ->required()
                            ->helperText(fn(Forms\Get $get) => $get('delivery_address_hint'))
                            ->maxLength(255),

                        TextInput::make('delivery')
                            ->label(__('fields.delivery_price'))
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(PHP_INT_MAX)
                            ->formatStateUsing(fn($state) => is_number($state) ? number_format($state, currency_decimals(), '.', '') : null)
                            ->extraInputAttributes(['min' => 0, 'max' => PHP_INT_MAX]),

                    ])->columns(3),


                Forms\Components\Section::make()
                    ->disabled(fn($record) => $record and $record->status == Order::$STATUS_COMPLETED or $record and $record->status == Order::$STATUS_CANCELLED)
                    ->key('details-section')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('add_product')
                            ->hidden(fn($record) => $record != null and $record->status == Order::$STATUS_COMPLETED or $record and $record->status == Order::$STATUS_CANCELLED)
                            ->color('primary')
                            ->label(__('fields.add_product'))
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
                                            ->options(Product::groupedAsOptions())
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

//                                dd($arguments, $action->getArguments());
                                $product = Product::with(['variants'])->findOrFail($data['product_id']);

                                $existingDetails = $livewire->data['details'] ?? [];

                                $max_qty = $data['max_qty'];
                                $unlimited_qty = $data['unlimited_qty'] ?? false;

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

//                                    dd($variant, $variant->retail_price);

                                    if (!$variant) {
                                        fns()->sendDanger("Option not found");
                                    }

                                    $tenant_id = $data['tenant_id'];
                                    $type = "variants";
                                    $name = $variant->name;
                                    $model_id = $variant->id;
                                    $model_type = ProductVariant::class;
                                    $price = PricingService::instance()->getRetailPrice($variant);
                                    $max_qty = StockService::instance()->getAvailableStock($variant);
                                    $qty = 1;

                                    if (!$unlimited_qty and $max_qty == 0) {
                                        fns()->sendWarning(__('fields.no_stock_available'));
                                        $action->halt();
                                    }

                                    if (!$price or $price == 0) {
                                        fns()->sendWarning(__('fields.product_variant_not_priced'));
                                        $action->halt();
                                    }

                                    $item[Str::uuid()->toString()] = [
                                        'tenant_id' => $tenant_id,
                                        'item_id' => $model_id,
                                        'item_type' => $model_type,
                                        'type' => $type,
                                        'display_name' => $name,
                                        'max_qty' => $max_qty,
                                        'qty' => $qty,
                                        'unit_price' => $price,
                                        'price' => format_amount($qty * $price),
                                        'tax' => PricingService::instance()->getTaxAmount($product, $price, $qty),
                                        'product_extras_ids' => $productExtrasIds,
                                        'available_product_extras_ids' => $product->extras->pluck('id')->toArray(),
                                        'extras' => implode(', ', ProductExtra::findMany($productExtrasIds)->pluck('name')->toArray()),
                                    ];

                                } else {
                                    //basic

                                    $tenant_id = $data['tenant_id'];
                                    $type = $data['type'];
                                    $name = $data['name'];
                                    $model_id = $data['model_id'];
                                    $model_type = $data['model_type'];
                                    $price = $data['unit_price'];
                                    $max_qty = StockService::instance()->getAvailableStock($product);

                                    $qty = 1;

                                    if (!$unlimited_qty and $max_qty == 0) {
                                        fns()->sendWarning(__('fields.no_stock_available'));
                                        $action->halt();
                                    }

                                    if (!$price or $price == 0) {
                                        fns()->sendWarning(__('fields.product_variant_not_priced'));
                                        $action->halt();
                                    }

                                    $item[Str::uuid()->toString()] = [
                                        'tenant_id' => $tenant_id,
                                        'item_id' => $model_id,
                                        'item_type' => $model_type,
                                        'type' => $type,
                                        'display_name' => $name,
                                        'max_qty' => $max_qty,
                                        'qty' => $qty,
                                        'unit_price' => $price,
                                        'price' => format_amount($qty * $price),
                                        'tax' => PricingService::instance()->getTaxAmount($product, $price, $qty),
                                        'product_extras_ids' => $productExtrasIds,
                                        'extras' => implode(', ', ProductExtra::findMany($productExtrasIds)->pluck('name')->toArray()),
                                    ];
                                }

                                $itemExists = collect($existingDetails)->where('product_id', $product->id)->where('product_variant_id', $product->product_variant_id)->first();

                                if ($itemExists) {
                                    fns()->sendWarning(__('fields.order_details_item_already_exists'));
                                    $action->halt();
                                }


                                foreach ($livewire->data['details'] as $index => $it) {
                                    if ($it['product_id'] == null) {
                                        unset($livewire->data['details'][$index]);
                                        unset($existingDetails[$index]);
                                    }
                                }

                                $livewire->data['details'] = array_merge($existingDetails, $item);

                                self::updateTotal($livewire);

//                                dd($arguments, $action->getArguments());
//                                fns()->sendWarning($arguments['another'] ?? false);
//
//                                if ($arguments['another'] ?? false) {
//                                    fns()->sendWarning('another');
//                                    return;
//                                }

                                fns()->saved();

                                $action->halt();
                            }),
                    ])
                    ->schema([
                        TableRepeater::make('details')
                            ->label(__('fields.order_details'))
                            ->headers([
                                Header::make('display_name')
                                    ->width("165px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.name')),

                                Header::make('extras')
                                    ->width("200px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.product_extras')),

                                Header::make('qty')
                                    ->width("80px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.qty')),

                                Header::make('tax')
                                    ->width("120px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.tax')),

                                Header::make('price')
                                    ->width("150px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.price')),
                            ])
//                            ->relationship('details')
                            ->emptyLabel(__('fields.no_records_placeholder'))
                            ->addActionLabel(__('fields.add'))
                            ->addable(false)
                            ->defaultItems(0)
                            ->deleteAction(
                                fn(Forms\Components\Actions\Action $action) => $action->requiresConfirmation(),
                            )
                            ->afterStateHydrated(fn($livewire) => self::updateTotal($livewire))
                            ->afterStateUpdated(fn($livewire) => self::updateTotal($livewire))
                            ->mutateRelationshipDataBeforeFillUsing(function ($data, $record) {
                                $data['price'] = number_format($data['qty'] * $data['unit_price'], currency_decimals(), '.', '');
                                return $data;
                            })
                            ->live()
                            ->schema([

                                hidden_tenant_id_field(),

                                hidden_user_id_field(),

                                Forms\Components\Hidden::make('max_qty')->dehydrated(false),

                                Forms\Components\Hidden::make('type'),
                                Forms\Components\Hidden::make('item_id'),
                                Forms\Components\Hidden::make('item_type'),
                                Forms\Components\Hidden::make('unit_price'),

//                                Forms\Components\Hidden::make('product_extras_ids'),

                                TextInput::make('display_name')->label(__('fields.product'))->readOnly(),

//                                TextInput::make('extras')->label(__('fields.product_extras'))->readOnly(),

                                Select::make('product_extras_ids')
                                    ->label(__('fields.product_extras'))
                                    ->live()
                                    ->default(function (Get $get) {
                                        return ProductExtra::findMany($get('available_product_extras_ids'))->pluck('name', 'id');
                                    })
                                    ->options(function (Get $get) {
                                        return ProductExtra::findMany($get('available_product_extras_ids'))->pluck('name', 'id');
                                    })
                                    ->afterStateUpdated(function (Get $get, $livewire) {
                                        self::updateTotal($livewire);
                                    })
                                    ->suffix(function (Get $get) {
                                        $exts = ProductExtra::findMany($get('product_extras_ids'));
                                        return format_amount(PricingService::instance()->getRetailItemsPrices($exts));
                                    })
                                    ->multiple(),

                                TextInput::make('qty')
                                    ->label(__('fields.qty'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(fn(Forms\Get $get) => $get('max_qty'))
                                    ->live(true)
                                    ->afterStateHydrated(function ($record, Forms\Set $set) {
                                        if ($record) {
//                                            $set('max_qty', $record->item->inventory_count ?? 0);
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        if ($state) {
                                            $set('price', format_amount($state * $get('unit_price')));
                                        }
                                    })
                                    ->extraInputAttributes(function (Forms\Get $get) {
                                        return [
                                            'min' => 1,
                                            'max' => $get('max_qty'),
                                        ];
                                    }),

                                TextInput::make('price')
                                    ->label(__('fields.price'))
                                    ->dehydrated(false)
                                    ->readOnly(),

                                TextInput::make('tax')
                                    ->label(__('fields.tax'))
                                    ->readOnly(),

                            ])
                    ]),

                Forms\Components\Section::make()->schema([

                    TextInput::make('products_total')
                        ->label(__('fields.products'))
                        ->readOnly()
                        ->suffix(fn() => main_currency_iso_code())
                        ->dehydrated(false),

                    TextInput::make('extras_total')
                        ->label(__('fields.products_extras'))
                        ->readOnly()
                        ->suffix(fn() => main_currency_iso_code())
                        ->dehydrated(false),

                    TextInput::make('taxes_total')
                        ->label(__('fields.tax'))
                        ->readOnly()
                        ->suffix(fn() => main_currency_iso_code())
                        ->dehydrated(false),

                    TextInput::make('total')
                        ->label(__('fields.total'))
                        ->readOnly()
                        ->suffix(fn() => main_currency_iso_code())
                        ->dehydrated(false),

                ])->columns(4),

            ]);
    }


    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('no')
                    ->label(__('fields.order_no'))
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('fields.client'))
                    ->toggleable()
                    ->searchable()
                    ->color(Color::Sky)
                    ->url(function (Order $record) {
                        return CustomerResource::getUrl("edit", ['record' => $record->customer_id]);
                    }, true),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('fields.status'))
                    ->badge()
                    ->color(function (Order $record) {
                        return match ($record->status) {
                            'new' => 'gray',
                            'packaging' => 'warning',
                            'delivery-in-progress' => 'success',
                            'completed' => Color::Green,
                            'cancelled' => 'danger',
                            default => 'danger',
                        };
                    })
                    ->getStateUsing(fn($record) => __('fields.order_status_' . $record->status))
                    ->searchable(),

                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->label(__('fields.payment_status'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->getStateUsing(fn(Order $record) => $record->invoice?->payment_status),

                Tables\Columns\TextColumn::make('delivery')
                    ->toggleable()
                    ->label(__('fields.delivery_price'))
                    ->getStateUsing(function (Order $record) {
                        return main_currency_iso_code() . " " . format_amount($record->delivery);
                    })
                    ->summarize(Tables\Columns\Summarizers\Sum::make()
                        ->label(__('fields.total'))
                        ->formatStateUsing(function ($state) {
                            return main_currency_iso_code() . " " . format_amount($state);
                        })),

//                Tables\Columns\TextColumn::make('sub_total')
//                    ->toggleable()
//                    ->label(__('fields.sub_total'))
//                    ->tooltip(function ($record) {
//                        return format_amount($record->sub_total);
//                    })
//                    ->getStateUsing(function (Order $record) {
//                        if ($record->discount > 0) {
//                            $originalPrice = format_amount($record->sub_total + $record->discount) . " " . main_currency_iso_code();
//                            $discountedPrice = format_amount($record->sub_total) . " " . main_currency_iso_code();
//                            return new HtmlString("<p><h1 style='text-decoration: line-through; font-weight: lighter; color: #ff5028;'>$originalPrice</h1>  $discountedPrice</p>");
//                        }
//                        return main_currency_iso_code() . " " . format_amount($record->sub_total);
//                    })->description(function (Order $record) {
//                        return $record['coupon_data']['code'] ?? null;
//                    }),

                Tables\Columns\TextColumn::make('total')
                    ->toggleable()
                    ->label(__('fields.total'))
                    ->getStateUsing(function (Order $record) {
                        return main_currency_iso_code() . " " . format_amount($record->total);
                    }),

                Tables\Columns\TextColumn::make('delivery_type')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->label(__('fields.delivery_type'))
                    ->searchable(),
//
//                Tables\Columns\TextColumn::make('payment_method')
//                    ->toggleable()
//                    ->toggledHiddenByDefault()
//                    ->label(__('fields.payment_method'))
//                    ->searchable(),
//
//                Tables\Columns\TextColumn::make('other_payment_method')
//                    ->toggleable()
//                    ->toggledHiddenByDefault()
//                    ->label(__('fields.other_payment_method'))
//                    ->searchable(),

                Tables\Columns\TextColumn::make('delivery_address')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->label(__('fields.delivery_address'))
                    ->searchable(),

//                Tables\Columns\TextColumn::make('discount')
//                    ->toggleable()
//                    ->toggledHiddenByDefault()
//                    ->label(__('fields.discount'))
//                    ->searchable(),

//                Tables\Columns\TextColumn::make('delivery')
//                    ->toggleable()
//                    ->label(__('fields.delivery_price'))
//                    ->searchable(),
//
//                Tables\Columns\TextColumn::make('delivery_extra')
//                    ->toggleable()
//                    ->label(__('fields.additional_delivery_price'))
//                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.order_date'))
                    ->dateTime('M j, Y')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_paid')
                    ->label(__('fields.paid'))
                    ->boolean()
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->getStateUsing(fn(Order $record) => $record->invoice->paid),

                Tables\Columns\TextColumn::make('delivery_date')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->label(__('fields.delivery_date'))
                    ->dateTime('M j, Y')
                    ->sortable(),

//                Tables\Columns\TextColumn::make('paid_date')
//                    ->toggleable()
//                    ->toggledHiddenByDefault()
//                    ->label(__('fields.paid_date'))
//                    ->dateTime('M j, Y')
//                    ->sortable(),

                Tables\Columns\TextColumn::make('canceled_date')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->label(__('fields.canceled_date'))
                    ->dateTime('M j, Y')
                    ->sortable(),

            ])
            ->filters([

                Tables\Filters\Filter::make('created_at')
                    ->label(__('fields.created_at'))
                    ->columnSpanFull()
                    ->form([
                        Select::make('status')
                            ->label(__('fields.status'))
                            ->multiple()
                            ->options([
                                Order::$STATUS_NEW => __('fields.order_status_' . Order::$STATUS_NEW),
                                Order::$STATUS_PACKAGING => __('fields.order_status_' . Order::$STATUS_PACKAGING),
                                Order::$STATUS_DELIVERY_IN_PROGRESS => __('fields.order_status_' . Order::$STATUS_DELIVERY_IN_PROGRESS),
                                Order::$STATUS_CANCELLED => __('fields.order_status_' . Order::$STATUS_CANCELLED),
                                Order::$STATUS_COMPLETED => __('fields.order_status_' . Order::$STATUS_COMPLETED),
                            ]),

                        Select::make('customers')
                            ->label(__('fields.client'))
                            ->multiple()
                            ->options(Order::with('customer')->get()->pluck('customer.name', 'customer.id')),

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
                            ->when($data['status'],
                                fn($query) => $query->whereDate('status', $data['status']))
                            ->when($data['customers'],
                                fn($query) => $query->whereIn('customer_id', $data['customers']))
                            ->when($data['created_from'],
                                fn($query) => $query->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'],
                                fn($query) => $query->whereDate('created_at', '<=', $data['created_until']));
                    })

            ])
            ->actions([

                Tables\Actions\ViewAction::make(),
//                    Tables\Actions\Action::make('download_invoice')
//                        ->label(__('fields.download_invoice'))
//                        ->icon('heroicon-o-download')
//                        ->color('success')
//                        ->action(function (Order $record) {
//                            $invoice = (new InvoiceService())
//                                ->filePath('invoices/orders')
//                                ->getInvoice($record->invoice, 0, $record->delivery, [
//                                    'phone' => $record->client->phone,
//                                    'delivery address' => $record->delivery_address,
//                                    'delivery fees' => number_format($record->delivery, 2) . ' SDG',
//                                    'status' => $record->invoice->paid ? "Paid" : "Unpaid",
//                                ]);
//
//                            Notification::make()
//                                ->title('Invoice download completed')
//                                ->success()
//                                ->persistent()
//                                ->actions([
//                                    Action::make('view')
//                                        ->label('View file')
//                                        ->button()
//                                        ->url($invoice->url(), shouldOpenInNewTab: true)
//                                ])
//                                ->send();
//                        }),

                Tables\Actions\Action::make('change_status')
                    ->label(__('fields.change_status'))
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->disabled(fn($record) => $record and $record->status === Order::$STATUS_CANCELLED or $record->status == Order::$STATUS_COMPLETED)
                    ->modalWidth(MaxWidth::Small)
                    ->form(function (Order $record) {
                        return [
                            Forms\Components\Section::make()->schema([

                                Select::make('status')
                                    ->label(__('fields.status'))
                                    ->live()
                                    ->options([
                                        Order::$STATUS_PACKAGING => __('fields.order_status_' . Order::$STATUS_PACKAGING),
                                        Order::$STATUS_DELIVERY_IN_PROGRESS => __('fields.order_status_' . Order::$STATUS_DELIVERY_IN_PROGRESS),
                                        Order::$STATUS_CANCELLED => __('fields.order_status_' . Order::$STATUS_CANCELLED),
                                        Order::$STATUS_COMPLETED => __('fields.order_status_' . Order::$STATUS_COMPLETED),
                                    ])
                                    ->default($record->status)
                                    ->required(),

                                Forms\Components\DatePicker::make('delivery_date')
                                    ->label(__('fields.delivery_date'))
                                    ->required()
                                    ->default(today())
                                    ->visible(fn(Get $get) => $get('status') === Order::$STATUS_COMPLETED),

                                Forms\Components\DatePicker::make('canceled_date')
                                    ->label(__('fields.canceled_date'))
                                    ->required()
                                    ->default(today())
                                    ->visible(fn(Get $get) => $get('status') === Order::$STATUS_CANCELLED),

                                Forms\Components\Textarea::make('canceled_reason')
                                    ->label(__('fields.canceled_reason'))
                                    ->visible(fn(Get $get) => $get('status') === Order::$STATUS_CANCELLED)
                                    ->cols(5)
                                    ->rows(5),

                                TextInput::make('delivery')
                                    ->label(__('fields.delivery_price'))
                                    ->visible(fn(Get $get) => $get('status') === Order::$STATUS_COMPLETED or $get('status') === Order::$STATUS_DELIVERY_IN_PROGRESS)
                                    ->default($record->delivery)
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(PHP_INT_MAX)
                                    ->formatStateUsing(fn($state) => is_number($state) ? number_format($state, currency_decimals(), '.', '') : null)
                                    ->extraInputAttributes(['min' => 0, 'max' => PHP_INT_MAX]),


                                Forms\Components\Placeholder::make('info')
                                    ->visible(function (Get $get) {
                                        return ($get('status') === Order::$STATUS_COMPLETED or $get('status') === Order::$STATUS_CANCELLED);
                                    })
                                    ->label(function () {
                                        $msg = __("fields.order_will_be_locked_after_this_action");
                                        return new HtmlString("<strong style='color: #ff301d;'> $msg </strong>");
                                    }),
                            ]),
                        ];
                    })
                    ->modalWidth(MaxWidth::Small)
                    ->action(function (Order $record, array $data) {
                        try {
                            DB::beginTransaction();

                            if (array_key_exists('delivery', $data)) {
                                //sync additional cost
                                $invoice = $record->invoice;
                                $invAdditionalCost = AdditionalCost::where('meta->type', 'delivery_fees')->where('item_type', Invoice::class)->where('item_id', $invoice->id)->first();

                                if ($invAdditionalCost) {
                                    $invAdditionalCost->update([
                                        'cost' => $data['delivery'],
                                    ]);
                                }

                            }

                            if ($data['status'] == Order::$STATUS_CANCELLED) {
                                //cancel invoice
                                $record->invoice->update([
                                    'status' => 'cancelled',
                                    'locked_by_id' => auth()->id(),
                                    'locked_at' => now(),
                                ]);
                            }

                            if ($data['status'] == Order::$STATUS_COMPLETED) {
                                //confirmed invoice
                                $record->invoice->update([
                                    'status' => 'confirmed',
                                    'locked_by_id' => auth()->id(),
                                    'locked_at' => now(),
                                ]);

                                StockService::instance()->takeStockFromSalesInvoice($record->invoice);

                            }

                            $record->update($data);

                            DB::commit();

                            fns()->saved();

                        } catch (\Throwable $exception) {
                            DB::rollBack();
                            report($exception);

                            fns()->displayException($exception);
                        }
                    }),


                Tables\Actions\Action::make('view_invoice')
                    ->label(__('fields.view_invoice'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn(Order $record) => SalesInvoiceResource::getUrl('edit', ['record' => $record->invoice->id]), true),

                Tables\Actions\Action::make('complete_payment')
                    ->label(__('fields.payment_details'))
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->visible(function ($record) {
                        return !$record->invoice?->paid;
                    })
                    ->url(function (Order $record) {
                        if ($record->invoice->salesPayments->isEmpty()) {
                            return ReceiptVoucherResource::getUrl('create', ['invoice_id' => $record->invoice->id, 'order_id' => $record->id]);
                        }

                        $rv = ReceiptVoucher::whereInvoiceId($record->id)->first();

                        if ($rv)
                            return ReceiptVoucherResource::getUrl('edit', ['record' => $rv->id]);

                    }, true),

            ])
            ->bulkActions([
            ]);
    }

    protected static function updateTotal($livewire)
    {
        if (!isset($livewire->data))
            return;

        $extras = 0;
        $taxes = 0;
        $delivery = $livewire->data['delivery'];
        $total = 0;

        foreach ($livewire->data['details'] ?? [] as $index => $item) {

            if ($item['item_type'] and $item['item_id']) {
                $model = ($item['item_type'])::find($item['item_id']);
                $livewire->data['details'][$index]['tax'] = number_format(PricingService::instance()->getTaxAmount($model instanceof Product ? $model : $model->product, $item['unit_price'], $item['qty']), currency_decimals(), '.', '');
            }

            if (is_number($item['qty']) and is_number($item['unit_price']))
                $total += $item['qty'] * $item['unit_price'];

            if (is_number($item['tax']))
                $taxes += $item['tax'];

            foreach ($item['product_extras_ids'] ?? [] as $productExtraId) {
                $productExtra = ProductExtra::with(['lastPrice'])->find($productExtraId);

                $extras += PricingService::instance()->getRetailPrice($productExtra);
            }
        }


        $livewire->data['products_total'] = format_amount($total);
        $livewire->data['extras_total'] = format_amount($extras);
        $livewire->data['taxes_total'] = format_amount($taxes);
        $livewire->data['total'] = format_amount($total + $extras + $taxes + $delivery);
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(
            [
                'invoice.items.orderDetails.orderDetailsExtras',
                'invoice.items.extras.productExtra.extra',
                'invoice.salesPayments',
                'customer',
                'user',
                'details.item',
                'details.orderDetailsExtras.productExtra.extra',
                'state',
                'city',
                'area'
            ])->latest();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getWidgets(): array
    {
        return [
            OrderStats::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
            'view' => Pages\ViewOrder::route('/{record}/view'),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make([

                TextEntry::make('status')
                    ->label(__('fields.status'))
                    ->weight(FontWeight::ExtraBold)
                    ->size(TextEntry\TextEntrySize::Large)
                    ->color(function (Order $record) {
                        return match ($record->status) {
                            'new' => 'gray',
                            'packaging' => 'warning',
                            'delivery-in-progress' => 'success',
                            'completed' => Color::Green,
                            'cancelled' => 'danger',
                            default => 'danger',
                        };
                    })
                    ->formatStateUsing(fn($state) => __('fields.order_status_' . $state)),

                TextEntry::make('no')
                    ->label(__('fields.order_no'))
                    ->copyable()
                    ->weight(FontWeight::Bold),

                TextEntry::make('customer.name')
                    ->label(__('fields.client'))
                    ->color(Color::Sky)
                    ->url(fn($record) => CustomerResource::getUrl('edit', ['record' => $record->customer_id]), true),

                TextEntry::make('created_at')
                    ->label(__('fields.order_date'))
                    ->dateTime('l jS \of F h:i A'),


                TextEntry::make('delivery_address')
                    ->label(__('fields.delivery_address'))
                    ->getStateUsing(fn(Order $record) => $record->full_address),

                TextEntry::make('delivery')
                    ->label(__('fields.delivery_price'))
                    ->formatStateUsing(fn($state) => number_format($state, currency_decimals(), '.', ',')),

                TextEntry::make('invoice.no')
                    ->label(__('fields.invoice'))
                    ->color(Color::Sky)
                    ->url(fn($record) => SalesInvoiceResource::getUrl('edit', ['record' => $record->invoice_id]), true),

                TextEntry::make('invoice_total')
                    ->label(__('fields.invoice_total'))
                    ->color(Color::Sky)
                    ->tooltip(function (Order $record) {
                        return numbers_to_words(number_format($record->invoice->getItemsCost(true, true, true), currency_decimals(), '.', ','));
                    })
                    ->getStateUsing(function (Order $record) {
                        return main_currency_iso_code() . " " . number_format($record->invoice->getItemsCost(true, true, true), currency_decimals(), '.', ',');
                    })
            ])->columns(2),

            RepeatableEntry::make('invoice.items')
                ->label(__('fields.items') . "(".$infolist->getRecord()->invoice->items->count().")")
                ->schema([

                    TextEntry::make('name')
                        ->label(__('fields.name')),

                    TextEntry::make('extras')
                        ->label(__('fields.product_extras'))
                        ->size(TextEntry\TextEntrySize::ExtraSmall)
                        ->listWithLineBreaks()
                        ->getStateUsing(function (InvoiceItem $record) {
                            return $record->extras_names;
                        }),

                    TextEntry::make('extras_total')
                        ->label(__('fields.extras_total'))
                        ->getStateUsing(function (InvoiceItem $record) {
                            return number_format($record->extras_total, currency_decimals(), '.', ',');
                        }),

                    TextEntry::make('qty')
                        ->label(__('fields.qty')),

                    TextEntry::make('discount')
                        ->formatStateUsing(fn($state) => number_format($state, currency_decimals(), '.', ','))
                        ->label(__('fields.discount')),

                    TextEntry::make('tax')
                        ->formatStateUsing(fn($state) => number_format($state, currency_decimals(), '.', ','))
                        ->label(__('fields.tax')),

                    TextEntry::make('price')
                        ->formatStateUsing(fn($state) => number_format($state, currency_decimals(), '.', ','))
                        ->label(__('fields.price')),

                    TextEntry::make('sub_total')
                        ->label(__('fields.sub_total'))
                        ->weight(FontWeight::ExtraBold)
                        ->tooltip(function (InvoiceItem $record) {
                            return numbers_to_words(number_format($record->sub_total, currency_decimals(), '.', ','));
                        })
                        ->getStateUsing(fn(InvoiceItem $record) => main_currency_iso_code() . " " .number_format($record->sub_total,  currency_decimals(), '.', ',')),

                ])->columns(8),

        ])->columns(1);
    }
}
