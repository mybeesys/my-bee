<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\PurchaseInvoiceResource\Pages;
use App\Filament\Tenant\Resources\PurchaseInvoiceResource\RelationManagers;
use App\Models\Acc4;
use App\Models\AccountingTransaction;
use App\Models\CashDet;
use App\Models\Invoice;
use App\Models\InvoiceAdditionalCost;
use App\Models\InvoiceAdditionalCostType;
use App\Models\InvoiceStatus;
use App\Models\Op;
use App\Models\Product;
use App\Models\PurchaseInvoiceStatus;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Rules\UniqueTenantItemRule;
use App\Services\InvoiceService;
use App\Services\PricingService;
use Awcodes\FilamentTableRepeater\Components\TableRepeater;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class PurchaseInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = "invoices/purchases";

    protected static ?string $recordTitleAttribute = "no";

    public static function getNavigationGroup(): ?string
    {
        return __('fields.invoices');
    }

    public static function getLabel(): ?string
    {
        return __('fields.purchases_invoice');
    }


    public static function getPluralLabel(): ?string
    {
        return __('fields.purchases_invoices');
    }

    public static function getNavigationBadge(): ?string
    {
        return Invoice::purchases()->count();
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([

                    Forms\Components\Section::make()
                        ->disabled($form->getRecord()?->locked_at !== null)
                        ->schema([

                            Forms\Components\Hidden::make('type')->default('purchases'),

                            Forms\Components\Hidden::make('for')->default('supplier'),

                            hidden_tenant_id_field(),

                            hidden_user_id_field(),

                            TextInput::make('no')
//                        ->afterStateHydrated(fn(Set $set) => $set('no', generate_invoice_no()))
                                ->disabled()
                                ->default(fn() => generate_invoice_no())
                                ->label(__('fields.invoice_no'))
                                ->required(),

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

                            Select::make('supplier_id')
                                ->label(__('fields.supplier'))
                                ->searchable()
                                ->options(Supplier::pluck('name', 'id'))
                                ->createOptionForm(SupplierResource::getSchema())
                                ->createOptionUsing(function ($data) {
                                    $data['tenant_id'] = filament()->getTenant()->id;
                                    $model = Supplier::create($data);
                                    return $model->id;
                                })
                                ->createOptionAction(
                                    fn(Forms\Components\Actions\Action $action) => $action->modalWidth('5xl'),
                                )
                                ->required(),

//                    TextInput::make('exchange_rate')
//                        ->reactive()
//                        ->afterStateUpdated(function ($state) {
//                            if ($state) {
////                                $this->items = [];
////                                $this->additional_costs = [];
////                                $this->total_price_sdg = 0;
////                                $this->total_price_usd = 0;
//                            }
//                        })
//                        ->label(__('fields.exchange_rate'))
//                        ->numeric()
//                        ->required(),

                        ])->columns(3),

                    Forms\Components\Section::make(__('fields.discounts'))
                        ->disabled($form->getRecord()?->locked_at !== null)
                        ->collapsible()
                        ->schema([

                            Forms\Components\Select::make('discount_option')
                                ->label(__('fields.discounts'))
                                ->live()
                                ->default('none')
                                ->options([
                                    'none' => __('fields.no_discount'),
                                    'overall' => __('fields.discount_per_invoice'),
                                    'per-item' => __('fields.discount_per_product'),
                                ])->afterStateUpdated(function ($state, Set $set) {

                                    $set('total_purchases_post_discount', null);
                                    $set('total_invoice_post_discount', null);

                                    if ($state !== "overall") {
                                        $set('discount_method', 'none');
                                        $set('discount_amount', null);
                                        $set('discount_percent', null);
                                    }
                                }),

                            Forms\Components\Select::make('discount_method')
                                ->visible(fn(Forms\Get $get) => $get('discount_option') == "overall")
                                ->label(__('fields.discount_method'))
                                ->live()
                                ->options([
                                    'amount' => __('fields.discount_by_amount'),
                                    'percent' => __('fields.discount_by_percent'),
                                ]),

                            TextInput::make('discount_amount')
                                ->visible(fn(Forms\Get $get) => $get('discount_method') == "amount")
                                ->label(__('fields.discount_amount'))
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(PHP_INT_MAX)
                                ->required()
                                ->live(true)
                                ->afterStateUpdated(function (Get $get, Set $set, $livewire) {

                                    $totals = self::calculateInvoiceProperties($livewire->data['items'] ?? [], $livewire->data['additional_costs'] ?? [],
                                        $get('discount_option'), $get('discount_method'), $get('discount_amount'), $get('discount_percent'));

                                    $total_purchases = $totals['total_purchases'];
                                    $total_additional_costs = $totals['total_additional_costs'];
                                    $total_discount = $totals['total_discount'];

                                    $set('total_purchases', format_amount($total_purchases));
                                    $set('total_additional_costs', format_amount($total_additional_costs));
                                    $set('total_invoice', format_amount($total_purchases + $total_additional_costs));


                                    $set('total_purchases_post_discount', format_amount($total_purchases - $total_discount));
                                    $set('total_invoice_post_discount', format_amount($total_purchases + $total_additional_costs - $total_discount));

                                })
                                ->currency(),

                            TextInput::make('discount_percent')
                                ->visible(fn(Forms\Get $get) => $get('discount_method') == "percent")
                                ->label(__('fields.discount_percent'))
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(100)
                                ->suffix("%")
                                ->live(true)
                                ->afterStateUpdated(function (Get $get, Set $set, $livewire) {

                                    $totals = self::calculateInvoiceProperties($livewire->data['items'] ?? [], $livewire->data['additional_costs'] ?? [],
                                        $get('discount_option'), $get('discount_method'), $get('discount_amount'), $get('discount_percent'));

                                    $total_purchases = $totals['total_purchases'];
                                    $total_additional_costs = $totals['total_additional_costs'];
                                    $total_discount = $totals['total_discount'];

                                    $set('total_purchases', format_amount($total_purchases));
                                    $set('total_additional_costs', format_amount($total_additional_costs));
                                    $set('total_invoice', format_amount($total_purchases + $total_additional_costs));


                                    $set('total_purchases_post_discount', format_amount($total_purchases - $total_discount));
                                    $set('total_invoice_post_discount', format_amount($total_purchases + $total_additional_costs - $total_discount));

                                })
                                ->helperText(function (Get $get, $livewire) {

                                    $totals = self::calculateInvoiceProperties($livewire->data['items'] ?? [], $livewire->data['additional_costs'] ?? [],
                                        $get('discount_option'), $get('discount_method'), $get('discount_amount'), $get('discount_percent'));

                                    $discount = $totals['total_discount'];

                                    if (is_number($discount) and $discount > 0)
                                        return format_amount($discount);

                                })
                                ->currency()
                                ->required(),


                        ])->columns(3),

                    Forms\Components\Section::make(__('fields.purchases'))
                        ->disabled($form->getRecord()?->locked_at !== null)
                        ->schema([
                            Repeater::make('items')
                                ->relationship('items')
                                ->mutateRelationshipDataBeforeFillUsing(function ($data) {
                                    $data['total'] = format_amount($data['qty'] * $data['price']);
                                    return $data;
                                })
                                ->label('')
                                ->schema([

                                    hidden_tenant_id_field(),

                                    hidden_main_currency_field(),

                                    Select::make('product_id')
                                        ->label(__('fields.product'))
                                        ->searchable()
                                        ->live()
                                        ->options(Acc4::asOptions(item_class: Product::class, useItemId: true, withUnitsAsOptions: true))
                                        ->required()
                                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                                            $set('unit_id', null);
                                            $set('price', null);

                                            if (str($state)->contains("-") and $data = explode('-', $state)) {
//                                        product_id - unit_id
//                                        22-18
                                                $set('unit_id', $data[1] ?? null);
                                            }
                                        }),

                                    Forms\Components\Select::make('unit_id')
                                        ->label(__('fields.unit'))
                                        ->required()
                                        ->searchable()
                                        ->live()
                                        ->options(function (Forms\Get $get) {
                                            $product = Product::with(['prices', 'availableStocks', 'units.unit'])
                                                ->find($get('product_id'));

                                            if ($product) {
                                                return $product->unitsAsOptions();
                                            }

                                            return [];
                                        })
                                        ->afterStateUpdated(function (Forms\Set $set, Get $get, $state) {
                                            $set('price', null);

                                            $product = Product::find($get('product_id'));
                                            $unit = Unit::find($state);

                                            if ($product and $unit and $itemPrice = PricingService::instance()->getLastPriceForUnit($product, $unit->id)) {
                                                $set('price', number_format($itemPrice->unit_cost, 0, '.', ''));
                                            }
                                        }),

                                    Select::make('warehouse_id')
                                        ->label(__('fields.warehouse'))
                                        ->searchable()
                                        ->options(Warehouse::pluck('name', 'id'))
                                        ->createOptionForm([
                                            Forms\Components\Section::make(__('fields.warehouse'))
                                                ->schema([
                                                    TextInput::make('name')
                                                        ->label(__('fields.name'))
                                                        ->required()
                                                        ->autofocus()
                                                        ->rules([new UniqueTenantItemRule(Warehouse::class, 'name')]),
                                                ])
                                        ])
                                        ->createOptionUsing(function ($data) {
                                            $model = new Warehouse();

                                            $model->tenant_id = filament()->getTenant()->id;
                                            $model->name = $data['name'];
                                            $model->save();

                                            return $model->id;
                                        })
                                        ->createOptionAction(
                                            fn(Forms\Components\Actions\Action $action) => $action->modalWidth('md'),
                                        )
                                        ->required(),

                                    TextInput::make('qty')
                                        ->live(true)
                                        ->suffix('x')
                                        ->label(__('fields.qty'))
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(9000000)
                                        ->afterStateUpdated(function ($livewire, Set $set, Get $get, $state) {
                                            if ($state) {

                                                $qty = $get('qty');
                                                $price = $get('price');

                                                if ($qty and is_numeric($qty) and $qty > 0) {
                                                    if ($price and (is_numeric($price) or is_float($price)) and $price > 0) {
                                                        $set('total', format_amount($qty * $price));
                                                    }
                                                }
                                            }

                                            //update totals

                                            $totals = self::calculateInvoiceProperties($livewire->data['items'] ?? [], $livewire->data['additional_costs'] ?? [],
                                                $get('data.discount_option', true), $get('data.discount_method', true), $get('data.discount_amount', true), $get('data.discount_percent', true));

                                            $total_purchases = $totals['total_purchases'];
                                            $total_additional_costs = $totals['total_additional_costs'];
                                            $total_discount = $totals['total_discount'];


                                            $set('../../total_purchases', format_amount($total_purchases));
                                            $set('../../total_additional_costs', format_amount($total_additional_costs));
                                            $set('../../total_invoice', format_amount($total_purchases + $total_additional_costs));


                                            $set('../../total_purchases_post_discount', format_amount($total_purchases - $total_discount));
                                            $set('../../total_invoice_post_discount', format_amount($total_purchases + $total_additional_costs - $total_discount));

                                        })->required(),

                                    TextInput::make('price')
                                        ->live(true)
                                        ->label(__('fields.purchase_price'))
                                        ->numeric()
                                        ->maxValue(9000000)
                                        ->afterStateUpdated(function (Set $set, Get $get, $state, $livewire) {

                                            if ($state and (is_numeric($state) or is_float($state)) and $state > 0) {

                                                //update total

                                                $qty = $get('qty');

                                                if ($qty and is_numeric($qty) and $qty > 0) {
                                                    $set('total', format_amount($qty * $state));
                                                }
                                            }

                                            //update totals
                                            $totals = self::calculateInvoiceProperties($livewire->data['items'] ?? [], $livewire->data['additional_costs'] ?? [],
                                                $get('data.discount_option', true), $get('data.discount_method', true), $get('data.discount_amount', true), $get('data.discount_percent', true));

                                            $total_purchases = $totals['total_purchases'];
                                            $total_additional_costs = $totals['total_additional_costs'];
                                            $total_discount = $totals['total_discount'];


                                            $set('../../total_purchases', format_amount($total_purchases));
                                            $set('../../total_additional_costs', format_amount($total_additional_costs));
                                            $set('../../total_invoice', format_amount($total_purchases + $total_additional_costs));


                                            $set('../../total_purchases_post_discount', format_amount($total_purchases - $total_discount));
                                            $set('../../total_invoice_post_discount', format_amount($total_purchases + $total_additional_costs - $total_discount));

                                        })
                                        ->currency()
                                        ->required(),


                                    TextInput::make('discount')
                                        ->visible(fn(Forms\Get $get) => $get('data.discount_option', true) == "per-item")
                                        ->label(__('fields.discount_amount'))
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(PHP_INT_MAX)
                                        ->live(true)
                                        ->afterStateUpdated(function (Set $set, Get $get, $livewire) {
                                            $totals = self::calculateInvoiceProperties($livewire->data['items'] ?? [], $livewire->data['additional_costs'] ?? [],
                                                $get('data.discount_option', true), $get('data.discount_method', true), $get('data.discount_amount', true), $get('data.discount_percent', true));

                                            $total_purchases = $totals['total_purchases'];
                                            $total_additional_costs = $totals['total_additional_costs'];
                                            $total_discount = $totals['total_discount'];

                                            $set('data.total_purchases_post_discount', format_amount($total_purchases - $total_discount), true);
                                            $set('data.total_invoice_post_discount', format_amount($total_purchases + $total_additional_costs - $total_discount), true);

                                        })
                                        ->currency(),

//                            DatePicker::make('expiration_date')
//                                ->label(__('fields.expiration_date'))
//                                ->seconds(false)
//                                ->minDate(now())
//                                ->maxDate(now()->addYears(20))
//                                ->displayFormat('d/m/Y'),


//                            TextInput::make('total')
//                                ->reactive()
//                                ->disabled()
//                                ->dehydrated(false)
//                                ->mainCurrencySuffix()
//                                ->label(__('fields.total')),


                                ])
//                        ->addActionLabel(__('fields.add'))
////                        ->grid(1)
//                        ->collapsible()
////                        ->defaultItems(1)
//                        ->deleteAction(
//                            fn(Forms\Components\Actions\Action $action) => $action->requiresConfirmation(),
//                        )
                                ->columns(3),
                        ]),

                    Forms\Components\Section::make(__('fields.additional_costs'))
                        ->disabled($form->getRecord()?->locked_at !== null)
                        ->schema([
                            Repeater::make('additional_costs')
                                ->label('')
                                ->relationship('additionalCosts')
                                ->schema([

                                    hidden_tenant_id_field(),

                                    hidden_main_currency_field(),

                                    Select::make('invoice_additional_cost_type_id')
                                        ->label(__('fields.invoice_additional_cost_type'))
                                        ->required()
                                        ->options(InvoiceAdditionalCostType::pluck('name', 'id'))
                                        ->createOptionForm([
                                            Forms\Components\Section::make(__('fields.invoice_additional_cost_type'))
                                                ->schema([
                                                    TextInput::make('name')
                                                        ->label(__('fields.name'))
                                                        ->required()
                                                        ->autofocus()
                                                        ->rules([new UniqueTenantItemRule(InvoiceAdditionalCostType::class, 'name')]),
                                                ])
                                        ])
                                        ->createOptionUsing(function ($data) {
                                            $model = new InvoiceAdditionalCostType();

                                            $model->tenant_id = filament()->getTenant()->id;
                                            $model->name = $data['name'];
                                            $model->save();

                                            return $model->id;
                                        })
                                        ->createOptionAction(
                                            fn(Forms\Components\Actions\Action $action) => $action->modalWidth('md'),
                                        )
                                        ->searchable(),

                                    TextInput::make('cost')
                                        ->live(true)
                                        ->label(__('fields.cost'))
                                        ->numeric()
                                        ->maxValue(9000000)
                                        ->afterStateUpdated(function (Set $set, $livewire) {
                                            $total_purchases = self::calculateTotalPurchases($livewire->data['items'] ?? []);
                                            $total_additional_costs = self::calculateAdditionalCosts($livewire->data['additional_costs'] ?? []);

                                            $set('../../total_purchases', format_amount($total_purchases));
                                            $set('../../total_additional_costs', format_amount($total_additional_costs));
                                            $set('../../total_invoice', format_amount($total_purchases + $total_additional_costs));
                                        })
                                        ->currency()
                                        ->required(),

                                    TextInput::make('statement')
                                        ->label(__('fields.statement'))
                                        ->required()
                                        ->columnSpan(2),

                                ])
                                ->addActionLabel(__('fields.add'))
                                ->grid(1)
                                ->collapsible()
                                ->defaultItems(0)
                                ->columns(4),
                        ]),

                    Forms\Components\Section::make()
                        ->disabled($form->getRecord()?->locked_at !== null)
                        ->schema([

                            TextInput::make('total_purchases')
                                ->dehydrated(false)
                                ->formatStateUsing(function ($record) {
                                    if ($record) {
                                        return format_amount(self::calculateTotalPurchases($record->items->toArray()));
                                    }
                                    return 0;
                                })
                                ->readOnly()
                                ->helperText(fn($state) => numbers_to_words($state))
                                ->mainCurrencySuffix()
                                ->label(__('fields.total_purchases')),

                            TextInput::make('total_additional_costs')
                                ->dehydrated(false)
                                ->formatStateUsing(function ($record) {
                                    if ($record) {
                                        return format_amount(self::calculateAdditionalCosts($record->additionalCosts->toArray()));
                                    }
                                    return 0;
                                })
                                ->readOnly()
                                ->helperText(fn($state) => numbers_to_words($state))
                                ->mainCurrencySuffix()
                                ->label(__('fields.total_additional_costs')),

                            TextInput::make('total_invoice')
                                ->dehydrated(false)
                                ->formatStateUsing(function ($record) {
                                    if ($record) {
                                        return format_amount(self::calculateTotalPurchases($record->items->toArray()) +
                                            self::calculateAdditionalCosts($record->additionalCosts->toArray()));
                                    }
                                    return 0;
                                })
                                ->readOnly()
                                ->helperText(fn($state) => numbers_to_words($state))
                                ->mainCurrencySuffix()
                                ->label(__('fields.invoice_total')),

                        ])->columns(5),

                    Forms\Components\Section::make()
                        ->disabled($form->getRecord()?->locked_at !== null)
                        ->visible(fn(Forms\Get $get) => $get('discount_option') !== "none")
                        ->schema([

                            TextInput::make('total_purchases_post_discount')
                                ->visible(fn(Forms\Get $get) => $get('discount_option') !== "none")
                                ->dehydrated(false)
                                ->formatStateUsing(function ($record) {
                                    if ($record) {
                                        $items = $record->items->toArray();
                                        $additionalCosts = $record->additionalCosts->toArray();
                                        $discountOption = $record->discount_option;
                                        $discountMethod = $record->discount_method;
                                        $discountAmount = $record->discount_amount;
                                        $discountPercent = $record->discount_percent;

                                        $totals = self::calculateInvoiceProperties($items, $additionalCosts,
                                            $discountOption, $discountMethod, $discountAmount, $discountPercent);

                                        return format_amount($totals['total_purchases'] - $totals['total_discount']);
                                    }
                                    return null;
                                })
                                ->readOnly()
                                ->helperText(fn($state) => numbers_to_words($state))
                                ->mainCurrencySuffix()
                                ->label(__('fields.total_purchases_post_discount')),

                            TextInput::make('total_invoice_post_discount')
                                ->visible(fn(Forms\Get $get) => $get('discount_option') !== "none")
                                ->dehydrated(false)
                                ->formatStateUsing(function ($record) {
                                    if ($record) {

                                        $items = $record->items->toArray();
                                        $additionalCosts = $record->additionalCosts->toArray();
                                        $discountOption = $record->discount_option;
                                        $discountMethod = $record->discount_method;
                                        $discountAmount = $record->discount_amount;
                                        $discountPercent = $record->discount_percent;

                                        $totals = self::calculateInvoiceProperties($items, $additionalCosts,
                                            $discountOption, $discountMethod, $discountAmount, $discountPercent);

                                        return format_amount($totals['total_purchases'] + $totals['total_additional_costs'] - $totals['total_discount']);
                                    }
                                    return 0;
                                })
                                ->readOnly()
                                ->helperText(fn($state) => numbers_to_words($state))
                                ->mainCurrencySuffix()
                                ->label(__('fields.invoice_total_post_discount')),

                        ])->columns(4),
                ]
            );
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('no')
                    ->label(__('fields.invoice_no'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('purchase_status_id')
                    ->label(__('fields.status'))
                    ->searchable()
                    ->badge()
                    ->color("gray")
                    ->getStateUsing(function (Invoice $record) {
                        if ($record->purchaseStatus) {
                            $name = $record->purchaseStatus->name;
                            $color = $record->purchaseStatus->color;
                            return new HtmlString("<strong style='color: $color; font-weight: bolder;'> $name </strong>");
                        }
                    }),
                Tables\Columns\TextColumn::make('date')
                    ->label(__('fields.date'))
                    ->dateTime('M j, Y')
                    ->searchable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label(__('fields.supplier'))
                    ->searchable(),

//                Tables\Columns\TextColumn::make('exchange_rate')
//                    ->label(__('fields.exchange_rate'))
//                    ->searchable(),

                Tables\Columns\TextColumn::make('purchases_total')
                    ->label(__('fields.purchases'))
                    ->description(function ($record) {
                        $amount = $record->getDiscountInAmount();

                        if ($amount > 0)
                            return __('fields.discount') . " " . setting('main_currency', 'SAR') . " " . format_amount($amount);

                        return null;
                    })
                    ->tooltip(function ($record) {
                        if ($record->discount_option === "overall") {
                            if (is_number($record->discount_percent))
                                return format_amount($record->discount_percent) . "%";

                            if (is_number($record->discount_amount))
                                return format_amount($record->discount_amount);
                        }

                        return null;
                    })
                    ->getStateUsing(function ($record) {
                        return setting('main_currency', 'SAR') . " " . format_amount($record->getItemsCost());
                    }),

                Tables\Columns\TextColumn::make('additional_costs_total')
                    ->label(__('fields.additional_costs'))
                    ->getStateUsing(function ($record) {
                        return setting('main_currency', 'SAR') . " " . format_amount($record->getAdditionalCosts(setting('main_currency', 'SAR')));
                    }),

//                Tables\Columns\TextColumn::make('discount')
//                    ->label(__('fields.discount'))
//                    ->getStateUsing(function ($record) {
//                        return setting('main_currency', 'SAR') . " " . format_amount($record->getDiscountInAmount());
//                    }),

                Tables\Columns\TextColumn::make('invoice_total')
                    ->label(__('fields.invoice_total'))
                    ->getStateUsing(function ($record) {
                        return setting('main_currency', 'SAR') . " " . format_amount($record->getItemsCost() + $record->getAdditionalCosts());
                    }),

            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),

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
                                'current_status' => $record->purchaseStatus?->name,
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
                                    ->helperText(function (Invoice $record) {
//                                        if ($record->purchaseStatus) {
//                                            $service = StatusService::instance(UmrahApplication::class);
//                                            $canChange = $service->canChangeLocked($record->currentStatus());
//                                            if ($canChange === false)
//                                                return "You do not have permission to change the current status.";
//                                        }
                                    })
                                    ->readOnly(),

                                Forms\Components\Select::make('status_id')
                                    ->label(__('fields.change_status_to'))
                                    ->default(null)
                                    ->live()
                                    ->options(PurchaseInvoiceStatus::pluck('name', 'id'))
                                    ->required()
                                    ->disableOptionWhen(function ($record, $value) {
                                        return $record->purchase_status_id === $value;
                                    }),
//                                    ->afterStateHydrated(function (Forms\Components\Select $component, $record) {
//                                        $component->state($record->purchase_status_id);
//                                    }),

                                Forms\Components\Placeholder::make('info')
                                    ->visible(function (Get $get) {
                                        $status = PurchaseInvoiceStatus::find($get('status_id'));
                                        return ($status and $status->locks_invoice);
                                    })
                                    ->label(function () {
                                        $msg = __("fields.invoice_will_be_locked_after_this_action");
                                        return new HtmlString("<strong style='color: #ff301d;'> $msg </strong>");
                                    }),
                            ])
                        ])
                        ->action(function ($record, array $data) {

                            if (!can_lock_invoice()) {
                                fns()->sendWarning(__('fields.insufficient_permission'));
                                return;
                            }

                            if ($record->locked_at) {
                                fns()->sendWarning(__('fields.invoice_locked_edit_disabled'));
                                return;
                            }

                            try {

                                DB::beginTransaction();

                                $newStatus = PurchaseInvoiceStatus::find($data['status_id']);

                                if ($newStatus->locks_invoice) {
                                    $record->lockPurchaseInvoice($newStatus->id);
                                } else {
                                    $record->update(['purchase_status_id' => $newStatus->id]);
                                }

                                DB::commit();

                                fns()->sendSuccess(__('fields.invoice_updated'));

                            } catch (\Exception $exception) {
                                DB::rollBack();
                                fns()->displayException($exception);
                            }

                        }),

                    Tables\Actions\Action::make('download_invoice')
                        ->label(__('fields.download_invoice'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (Invoice $record) {
                            try {
                                $invoice = (new InvoiceService())
                                    ->filePath('invoices/purchases')
                                    ->getInvoice($record, 0, 0, [
                                        'Total paid' => $record->total_paid['sdg'],
                                    ]);

                                Notification::make()
                                    ->title('Invoice download completed')
                                    ->success()
                                    ->persistent()
                                    ->actions([
                                        Action::make('view')
                                            ->label('View file')
                                            ->button()
                                            ->url($invoice->url(), shouldOpenInNewTab: true)
                                    ])
                                    ->send();
                            } catch (\Exception $exception) {
                                Notification::make()
                                    ->title('Unable to download invoice')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }

                        }),

                ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('purchase_status_id')
                    ->label(__('fields.status'))
                    ->multiple()
                    ->options(PurchaseInvoiceStatus::pluck('name', 'id'))
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
            'index' => Pages\ListPurchaseInvoices::route('/'),
            'create' => Pages\CreatePurchaseInvoice::route('/create'),
            'edit' => Pages\EditPurchaseInvoice::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->purchases()
            ->with(
                [
                    'purchaseStatus',
                    'items',
                    'payments',
                    'client',
                    'representative',
                    'supplier',
                    'user',
                    'reviewedBy',
                    'additionalCosts',
                ])->latest();
    }

    public static function calculateTotalPurchases($items = []): float|int
    {
        $total = 0;

        foreach ($items as $item) {
            $qty = $item['qty'];
            $price = $item['price'];

            if (is_number($qty) and is_number($price)) {
                $total += $qty * $price;
            }
        }
        return $total;
    }

    public static function calculateAdditionalCosts($additional_costs = []): float|int
    {
        $total = 0;

        foreach ($additional_costs as $item) {
            $cost = $item['cost'];
            if (is_number($cost)) {
                $total += $cost;
            }
        }
        return $total;
    }

    public static function calculateInvoiceProperties($items, $additionalCosts, $discountOption, $discountMethod, $discountAmount = null, $discountPercent = null): array
    {

        $data = [
            'total_purchases' => 0,
            'total_additional_costs' => 0,
            'total_discount' => 0,
        ];
        foreach ($items as $item) {
            if (is_number($qty = $item['qty']) and is_number($price = $item['price']))
                $data['total_purchases'] += $qty * $price;

            if ($discountOption == "per-item" and is_number($discount = $item['discount']))
                $data['total_discount'] += $discount;
        }

        if ($discountOption == "overall" and $discountMethod == "amount" and is_number($discountAmount))
            $data['total_discount'] = $discountAmount;

        if ($discountOption == "overall" and $discountMethod == "percent" and is_number($discountPercent)) {
//            20/100 = 0.2
            $discountInAmount = $data['total_purchases'] * ($discountPercent / 100);

            $data['total_discount'] = number_format($discountInAmount, currency_decimals(), '.', '');

        }

        foreach ($additionalCosts as $item) {
            if (is_number($cost = $item['cost'])) {
                $data['total_additional_costs'] += $cost;
            }
        }
        return $data;
    }

}
