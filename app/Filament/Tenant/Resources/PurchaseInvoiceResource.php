<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\PurchaseInvoiceResource\Pages;
use App\Filament\Tenant\Resources\PurchaseInvoiceResource\RelationManagers;
use App\Models\Acc4;
use App\Models\CashDet;
use App\Models\Invoice;
use App\Models\InvoiceAdditionalCost;
use App\Models\InvoiceAdditionalCostType;
use App\Models\InvoiceStatus;
use App\Models\Op;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseInvoiceStatus;
use App\Models\Supplier;
use App\Models\TaxProfile;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Rules\UniqueTenantItemRule;
use App\Services\CacheService;
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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
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

    protected static $product_search_key = null;
    protected static $product_search_results_first_id = null;

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
                                ->label(__('fields.invoice_no'))
                                ->readOnly()
                                ->required()
                                ->default(fn() => generate_invoice_no())
                                ->rules([new UniqueTenantItemRule(Invoice::class, 'no', $form->getRecord()?->id)])
                                ->helperText(function () {
//                                    if (auth()->user()->isClient()) {
//                                        $url = Settings::getUrl();
//                                        $text = __("fields.change_invoice_numbering_helper_text");
//                                        return new HtmlString("<a target='_blank' style='color: #0000EE;' href='$url'>$text</a>");
//                                    }
                                    return null;
                                }),

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

                        ])->columns(4),

                    Forms\Components\Section::make(__('fields.purchases'))
                        ->disabled($form->getRecord()?->locked_at !== null)
                        ->schema([
                            TableRepeater::make('items')
                                ->relationship('items')
                                ->label("")
                                ->addActionLabel(__('fields.add'))
                                ->withoutHeader()
                                ->columnSpan('full')
                                ->columnWidths([
                                    'product_id' => '200px',
                                    'unit_id' => '150px',
                                    'qty' => '120px',
                                    'price' => '150px',
                                    'discount' => '150px',
                                    'tax_profile_id' => '200px',
                                    'sub_total' => '150px',
                                ])
                                ->mutateRelationshipDataBeforeFillUsing(function ($data) {
                                    $data['total'] = format_amount($data['qty'] * $data['price']);
                                    return $data;
                                })
                                ->afterStateUpdated(function ($livewire) {
                                    self::updateInvoicePropertiesFromLivewire($livewire);
                                })
                                ->schema([

                                    hidden_tenant_id_field(),

                                    hidden_main_currency_field(),

                                    Forms\Components\Hidden::make('barcode_search_result_unit_id')->dehydrated(false),
                                    Forms\Components\Hidden::make('barcode_search_result_product_id')->dehydrated(false),

                                    Select::make('product_id')
                                        ->label(__('fields.product'))
                                        ->searchable()
                                        ->getSearchResultsUsing(function ($search, $livewire, Set $set) {
//                                            $start = microtime(true);
//
//                                            $products = Cache::remember("products@".filament()->getTenant()->id, 5 * 60 , function (){
//                                               return Product::with(['units', 'acc4'])->get();
//                                            });
//
//                                            $products = $products->filter(function ($item) use ($search) {
//                                                return false !== stripos($item, $search);
//                                            })->pluck('name', 'id')->toArray();
//
//                                            $time = microtime(true) - $start;
//                                            fns()->sendWarning($time);
//                                            return $products;


                                            $products = Product::where('name', 'like', "%{$search}%")
                                                ->orWhere('barcode', 'like', "%{$search}%")
                                                ->orWhereHas('units', function ($q) use ($search) {
                                                    $q->where('barcode', 'like', "%{$search}%");
                                                })
                                                ->limit(50)->pluck('name', 'id')->toArray();


                                            $productUnit = ProductUnit::where('barcode', $search)->first();

                                            if ($productUnit) {
                                                $set('barcode_search_result_unit_id', $productUnit->unit_id);
                                                $set('barcode_search_result_product_id', $productUnit->product_id);
                                            } else {
                                                $set('barcode_search_result_unit_id', null);
                                                $set('barcode_search_result_product_id', null);
                                            }

                                            return $products;
                                        })
                                        ->live()
                                        ->options(Acc4::asOptions(item_class: Product::class, useItemId: true, withUnitsAsOptions: false))
                                        ->required()
                                        ->afterStateUpdated(function (Forms\Set $set, Get $get, $state, $livewire, Select $component) {
                                            $set('unit_id', null);
                                            $set('price', null);


                                            $barcode_search_result_unit_id = $get('barcode_search_result_unit_id');
                                            $barcode_search_result_product_id = $get('barcode_search_result_product_id');

                                            if (str($state)->contains("-") and $data = explode('-', $state)) {
//                                        product_id - unit_id
//                                        22-18
                                                $component->state($data[0] ?? null);
                                                $set('unit_id', $data[1] ?? null);
                                            }

                                            if ($state and $state == $barcode_search_result_product_id and $barcode_search_result_unit_id) {
                                                $set('unit_id', $barcode_search_result_unit_id);
                                                $set('barcode_search_result_unit_id', null);
                                                $set('barcode_search_result_product_id', null);
                                            }

                                            self::updateInvoicePropertiesFromLivewire($livewire);

                                        }),

                                    Forms\Components\Select::make('unit_id')
                                        ->label(__('fields.unit'))
                                        ->required()
                                        ->searchable()
                                        ->live()
                                        ->options(function (Forms\Get $get) {
                                            $product = Product::with(['prices', 'availableStocks', 'units.unit'])
                                                ->find($get('product_id'));

                                            if ($product and $product->type === Product::$TYPE_UNITS) {
                                                return $product->unitsAsOptions();
                                            }

                                            return [];
                                        })
                                        ->disableOptionWhen(function ($value, Get $get, $livewire) {
                                            //disable when unit is the selected by other products except this index of repeater
//
                                            $product_id = $get('product_id');

                                            return collect($livewire->data['items'])
                                                    ->where('product_id', $product_id)
                                                    ->where('unit_id', $value)->first() !== null;
                                        })
                                        ->afterStateUpdated(function (Forms\Set $set, Get $get, $state, $livewire) {
                                            $set('price', null);

                                            $product = Product::find($get('product_id'));
                                            $unit = Unit::find($state);

                                            if ($product and $unit and $itemPrice = PricingService::instance()->getLastPriceForUnit($product, $unit->id)) {
                                                $set('price', number_format($itemPrice->unit_cost, 0, '.', ''));
                                            }

                                            self::updateInvoicePropertiesFromLivewire($livewire);

                                        }),

                                    TextInput::make('qty')
                                        ->live(true)
                                        ->suffix('x')
                                        ->label(__('fields.qty'))
                                        ->numeric()
                                        ->extraInputAttributes(['min' => 1, 'max' => 250000], true)
                                        ->minValue(1)
                                        ->maxValue(250000)
                                        ->translateFrontValidationGt()
                                        ->afterStateUpdated(function ($livewire) {
                                            self::updateInvoicePropertiesFromLivewire($livewire);
                                        })->required(),

                                    TextInput::make('price')
                                        ->live(true)
                                        ->label(__('fields.purchase_price'))
                                        ->numeric()
                                        ->extraInputAttributes(['min' => 1, 'max' => PHP_INT_MAX], true)
                                        ->minValue(1)
                                        ->maxValue(PHP_INT_MAX)
                                        ->translateFrontValidationGt()
                                        ->afterStateUpdated(function (Set $set, Get $get, $state, $livewire) {
                                            self::updateInvoicePropertiesFromLivewire($livewire);
                                        })
                                        ->currency()
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
                                        })
                                        ->currency(),

                                    Select::make('tax_profile_id')
                                        ->label(__('fields.tax'))
                                        ->required()
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
                                        )
                                        ->live()
                                        ->afterStateUpdated(function ($state, $livewire) {
                                            self::updateInvoicePropertiesFromLivewire($livewire);
                                        })
                                        ->searchable(),


                                    TextInput::make('sub_total')
                                        ->label(__('fields.sub_total'))
                                        ->readOnly()
                                        ->dehydrated(false),

//                            DatePicker::make('expiration_date')
//                                ->label(__('fields.expiration_date'))
//                                ->seconds(false)
//                                ->minDate(now())
//                                ->maxDate(now()->addYears(20))
//                                ->displayFormat('d/m/Y'),


                                ]),
                        ]),

                    Forms\Components\Section::make(__('fields.additional_costs'))
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
                                        ->extraInputAttributes(['min' => 1, 'max' => PHP_INT_MAX])
                                        ->afterStateUpdated(function (Set $set, $livewire) {
                                            self::updateInvoicePropertiesFromLivewire($livewire);
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
                                        $set('discount_method', 'none');
                                        $set('discount_amount', null);
                                        $set('discount_percent', null);
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
                                })
//                                ->helperText(function (Get $get, $livewire) {
//
//                                    $totals = self::calculateInvoiceProperties($livewire->data['items'] ?? [], $livewire->data['additional_costs'] ?? [],
//                                        $get('discount_option'), $get('discount_method'), $get('discount_amount'), $get('discount_percent'));
//
//                                    $discount = $totals['total_discount'];
//
//                                    if (is_number($discount) and $discount > 0)
//                                        return format_amount($discount);
//
//                                })
                                ->currency()
                                ->required(),


                        ])->columns(5),


                    Forms\Components\Section::make()
                        ->disabled($form->getRecord()?->locked_at !== null)
                        ->schema([

                            TextInput::make('total_invoice_pre_discount_pre_tax')
                                ->label(__('fields.invoice_total'))
                                ->dehydrated(false)
                                ->readOnly()
                                ->mainCurrencySuffix(),

                            TextInput::make('total_discount')
                                ->label(__('fields.invoice_total_discount'))
                                ->dehydrated(false)
                                ->readOnly()
                                ->mainCurrencySuffix(),

                            TextInput::make('total_invoice_post_discount')
                                ->label(__('fields.total_invoice_net_post_discount'))
                                ->dehydrated(false)
                                ->readOnly()
                                ->mainCurrencySuffix(),

                            TextInput::make('total_invoice_with_taxes')
                                ->label(__('fields.invoice_total_with_tax'))
                                ->dehydrated(false)
                                ->readOnly()
                                ->helperText(fn($state) => numbers_to_words($state))
                                ->mainCurrencySuffix(),

                        ])->columns(4),

                ]
            );
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('no')
                    ->label(__('fields.invoice'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('purchase_status_id')
                    ->label(__('fields.type'))
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

                Tables\Columns\TextColumn::make('tax')
                    ->label(__('fields.tax'))
                    ->getStateUsing(function ($record) {
                        return setting('main_currency', 'SAR') . " " . format_amount($record->getTaxesAsAmount());
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
                    ->label(__('fields.amount_money'))
                    ->getStateUsing(function ($record) {
                        return setting('main_currency', 'SAR') . " " . format_amount($record->getItemsCost(true, true, true,));
                    }),

                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->label(__('fields.payment_status'))
                    ->getStateUsing(fn(Invoice $record) => $record->payment_status),

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
                                        return $record->purchase_status_id == $value;
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

                            if (!PurchaseInvoiceStatus::firstWhere('locks_invoice', true)) {
                                fns()->persist(true)->sendWarning("لم يتم إيجاد نوع فاتورة يقوم بعملية الإغلاق الرجاء تهيئة نوع من واجهة أنواع فواتير المشتريات");
                                return;
                            }

                            if (!PurchaseInvoiceStatus::firstWhere('releases_stock', true)) {
                                fns()->persist(true)->sendWarning("لم يتم إيجاد نوع فاتورة يقوم بعملية إنزال المخزون الرجاء تهيئة نوع من واجهة أنواع فواتير المشتريات");
                                return;
                            }

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
                                        'Payment status' => $record->payment_status,
                                        'Total paid' => $record->total_paid,
                                    ]);

                                Notification::make()
                                    ->title(__('fields.invoice_download_complete') . " " . $record->no)
                                    ->success()
                                    ->persistent()
                                    ->actions([
                                        Action::make('view')
                                            ->label(__('fields.invoice_download_view_file'))
                                            ->button()
                                            ->url($invoice->url(), shouldOpenInNewTab: true)
                                    ])
                                    ->send();
                            } catch (\Throwable $exception) {
                                Notification::make()
                                    ->title(__('fields.invoice_download_error'))
                                    ->body("")
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }

                        }),

                    Tables\Actions\Action::make('payment_details')
                        ->label(__('fields.payment_details'))
                        ->icon('heroicon-o-currency-dollar')
                        ->color('danger')
                        ->url(fn(Invoice $record) => $record->getPaymentVoucherResourceUrl(), true),
                ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('purchase_status_id')
                    ->label(__('fields.type'))
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
                    'purchasePayments',
                    'salesPayments',
                    'client',
                    'representative',
                    'supplier',
                    'user',
                    'reviewedBy',
                    'additionalCosts',
                ])->latest();
    }

    public static function updateInvoicePropertiesFromLivewire($livewire, $updateUIFields = true): array
    {
        $startTime = microtime(true);

        self::updateItemsDiscount($livewire);

        $items = $livewire->data['items'] ?? [];
        $additionalCosts = $livewire->data['additional_costs'] ?? [];
        $discountOption = $livewire->data['discount_option'] ?? [];
        $discountMethod = $livewire->data['discount_method'] ?? null;
        $discountAmount = $livewire->data['discount_amount'] ?? null;
        $discountPercent = $livewire->data['discount_percent'] ?? [];

        $totals = [
            'total_purchases' => 0,
            'total_additional_costs' => 0,
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
            $discount = $item['discount'] ?? 0;
            $tax = 0;

            if (is_number($qty) and is_number($price))
                $totals['total_purchases'] += $qty * $price;

            if ($discountOption == "per-item" and is_number($discount))
                $totals['total_discount'] += $discount;

            $taxProfileId = $item['tax_profile_id'] ?? null;

            if ($taxProfileId and is_number($price) and is_number($qty)) {
                $taxProfile = $taxProfiles->where('id', $taxProfileId)->first();

                if (!$taxProfile)
                    $taxProfile = TaxProfile::find($taxProfileId);

                if ($taxProfile) {
                    $sub_total = $price * $qty;

                    if (is_number($discount))
                        $sub_total -= $discount;

                    $tax = $sub_total * ($taxProfile->total_percentages / 100);
                    $totals['total_taxes'] += $sub_total * ($taxProfile->total_percentages / 100);
                }
            }

        }

        if ($discountOption == "overall" and $discountMethod == "amount" and is_number($discountAmount))
            $totals['total_discount'] = $discountAmount;

        if ($discountOption == "overall" and $discountMethod == "percent" and is_number($discountPercent)) {
//            20/100 = 0.2
            $discountInAmount = $totals['total_purchases'] * ($discountPercent / 100);

            $totals['total_discount'] = number_format($discountInAmount, currency_decimals(), '.', '');

        }

        foreach ($additionalCosts as $item) {
            if (is_number($cost = $item['cost'])) {
                $totals['total_additional_costs'] += $cost;
            }
        }

        if ($updateUIFields) {
            $livewire->data['total_invoice_pre_discount_pre_tax'] = format_amount($totals['total_purchases'] + $totals['total_additional_costs']);
            $livewire->data['total_discount'] = format_amount($totals['total_discount']);
            $livewire->data['total_invoice_post_discount'] = format_amount($totals['total_purchases'] + $totals['total_additional_costs'] - $totals['total_discount']);
            $livewire->data['total_invoice_with_taxes'] = format_amount($totals['total_purchases'] + $totals['total_additional_costs'] - $totals['total_discount'] + $totals['total_taxes']);
        }

        $endTime = microtime(true);

        $totals['execution_time'] = $endTime - $startTime;

        return $totals;
    }

    public static function updateItemsDiscount($livewire)
    {

        $taxProfiles = CacheService::instance()->remember('taxProfiles', 5 * 60, function () {
            return TaxProfile::all();
        });

        $discountOption = $livewire->data['discount_option'] ?? null;
        $discountMethod = $livewire->data['discount_method'] ?? null;
        $discountAmount = $livewire->data['discount_amount'] ?? $livewire->data['discount_percent'] ?? null;

        $newItems = [];
        foreach ($livewire->data['items'] ?? [] as $item) {

            $price = $item['price'] ?? null;
            $qty = $item['qty'] ?? null;
            if ($discountOption == "overall") {
                if ($discountMethod == "percent") {
                    if (is_number($price) and is_number($qty)) {
                        $amountFromPercent = ($price * $qty) * ($discountAmount / 100);
                        $item['discount'] = $amountFromPercent;
                    } else {
                        $item['discount'] = -1;
                    }

                } else {
                    $item['discount'] = $discountAmount;
                }
            }

            //set sub_total
            if (is_number($price) and is_number($qty)) {
                $discount = $item['discount'] ?? null;

                if (!$discount or $discount < 0)
                    $discount = 0;

                $subTotal = $price * $qty;
                $subTotal -= $discount;

                if (is_number($item['tax_profile_id'] ?? null)) {
                    $taxProfileId = $item['tax_profile_id'];

                    $taxProfile = $taxProfiles->where('id', $taxProfileId)->first();

                    if (!$taxProfile)
                        $taxProfile = TaxProfile::find($taxProfileId);

                    if ($taxProfile) {
                        $tax = $subTotal * ($taxProfile->total_percentages / 100);
                        $subTotal += $tax;
                    }
                }


                $item['sub_total'] = format_amount($subTotal);


            }
            $newItems[] = $item;
        }

        $livewire->data['items'] = $newItems;
    }

}
