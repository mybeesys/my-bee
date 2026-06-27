<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Concerns\InlineProductLineItems;
use App\Filament\Tenant\Concerns\InvoiceDocumentFormLayout;
use App\Filament\Tenant\Resources\PurchaseInvoiceResource\Pages;
use App\Filament\Tenant\Resources\PurchaseInvoiceResource\RelationManagers;
use App\Models\Acc4;
use App\Models\AdditionalCostType;
use App\Models\CashDet;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Op;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SupplyOrder;
use App\Models\TaxProfile;
use App\Models\Unit;
use App\Models\VariantLibrary;
use App\Models\VariantLibraryOption;
use App\Models\Warehouse;
use App\Rules\UniqueTenantItemRule;
use App\Services\AccountingService;
use App\Services\InvoicePaymentTermsService;
use App\Services\CacheService;
use App\Services\InvoiceService;
use App\Services\MathService;
use App\Services\PricingService;
use App\Services\ProductService;
use App\Services\StockService;
use Awcodes\Shout\Components\Shout;
use Awcodes\TableRepeater\Components\TableRepeater;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Component;

class PurchaseInvoiceResource extends Resource
{
    use InlineProductLineItems;
    use InvoiceDocumentFormLayout;

    protected static ?string $model = Invoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = "purchases";

    protected static ?string $recordTitleAttribute = "no";

    public static function canCreate(): bool
    {
        return true;
    }

    protected static $product_search_key = null;
    protected static $product_search_results_first_id = null;

    public static function getNavigationGroup(): ?string
    {
        return __('fields.nav_group_purchases');
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
        return Invoice::purchases()->where('temp', false)->count();
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([

                    Shout::make('inv-con-alert')
                        ->visible(fn() => Invoice::purchases()->count() == 0)
                        ->content(__('fields.config_invoice_alert'))
                        ->icon("")
                        ->color(Color::Sky)
                        ->type('warning'),

                    Forms\Components\Hidden::make('supply_order_id')->dehydrated(false),

                    Shout::make('from-price-offer')
                        ->visible(fn(Get $get) => $get('supply_order_id') !== null)
                        ->content(fn(Get $get) => SupplyOrder::find($get('supply_order_id'))?->description)
                        ->icon("")
                        ->color(Color::Yellow)
                        ->columnSpan(2)
                        ->type('warning'),

                    Forms\Components\Section::make()
                        ->disabled($form->getRecord()?->locked_at !== null)
                        ->schema([

                            Forms\Components\Hidden::make('status')->default('confirmed'),

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
                                ->createOptionForm(SupplierResource::getQuickCreateSchema())
                                ->createOptionUsing(function ($data) {
                                    $data['tenant_id'] = filament()->getTenant()->id;
                                    $model = Supplier::create($data);
                                    return $model->id;
                                })
                                ->createOptionAction(
                                    fn(Forms\Components\Actions\Action $action) => $action->modalWidth('md'),
                                )
                                ->required(),

                            static::invoicePaymentTermsSelect($form),

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


                    Forms\Components\Section::make(__('fields.items'))
                                ->disabled($form->getRecord()?->locked_at !== null)
                        ->key('items-section')
                        ->extraAttributes(['class' => 'invoice-lines-panel'])
                                        ->schema([
                            static::invoiceLinesToolbar(),

                            TableRepeater::make('items')
                                ->dehydrated(false)
                                ->headers(static::purchaseInvoiceLineTableHeaders())
                                ->label('')
                                ->emptyLabel(__('fields.no_records_placeholder'))
                                ->addActionLabel(__('fields.add_new_row'))
                                ->addAction(fn (Forms\Components\Actions\Action $action) => static::invoiceLinesAddAction($action))
                                ->addable(true)
                                ->defaultItems(fn () => $form->getRecord() === null ? 1 : 0)
                                ->minItems(1)
                                ->extraAttributes(['class' => 'invoice-lines-table'])
                                                ->live()
                                ->deletable($form->getRecord() === null)
                                ->deleteAction(
                                    fn(Forms\Components\Actions\Action $action) => $action->requiresConfirmation(),
                                )
                                ->afterStateHydrated(function ($livewire) {
                                    $items = self::inlineProductLinesFromState($livewire->data['items'] ?? []);

                                    foreach ($items as $key => $item) {
                                        $items[$key] = self::hydrateInlineProductRow($item);
                                    }

                                    $livewire->data['items'] = $items;
                                    $livewire->cachedInvoiceLineItems = $items;

                                    self::updateInvoicePropertiesFromLivewire($livewire);
                                })
                                ->afterStateUpdated(function ($state, $livewire) {
                                    if (! is_array($state)) {
                                        self::updateInvoicePropertiesFromLivewire($livewire);

                                        return;
                                    }

                                    $previous = $livewire->cachedInvoiceLineItems
                                        ?? self::inlineProductLinesFromState($livewire->data['items'] ?? []);

                                    if (self::isInlineProductLinesOrderPayload($state)) {
                                        $items = self::reorderInlineProductLines($state, $previous);
                                        $livewire->data['items'] = $items;
                                        $livewire->cachedInvoiceLineItems = $items;
                                    } else {
                                        $items = self::inlineProductLinesFromState($state);
                                        $livewire->cachedInvoiceLineItems = $items;
                                    }

                                    self::updateInvoicePropertiesFromLivewire($livewire);
                                })
                                ->schema([

                                    hidden_tenant_id_field(),

                                    Forms\Components\Hidden::make('item_id')->dehydrated(false),
                                    Forms\Components\Hidden::make('item_type')->dehydrated(false),
                                    Forms\Components\Hidden::make('type'),
                                    Forms\Components\Hidden::make('tax_profile_data'),
                                    Forms\Components\Hidden::make('name'),
                                    Forms\Components\Hidden::make('product_variant_id'),

                                    self::inlineProductSelect(
                                        'name',
                                        fn ($livewire) => self::updateInvoicePropertiesFromLivewire($livewire),
                                        prefillUnitPrice: false,
                                    ),

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
                                            if ($invoiceItem = InvoiceItem::find($get('id')) and $invoiceItem->qty_returned) {
                                                $msg = app()->getLocale() == "en" ? "returned $invoiceItem->qty_returned" : "تم إرجاع $invoiceItem->qty_returned";
                                                return $msg;
                                            }
                                        })
                                        ->translateFrontValidationGt()
                                        ->required(),

                                    TextInput::make('price')
                                        ->live(true)
                                        ->label(__('fields.purchase_price'))
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
                                        ->label(__('fields.tax_profile'))
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

                    static::invoiceExtrasTabs(
                        $form,
                        'items',
                        fn ($livewire) => self::updateInvoicePropertiesFromLivewire($livewire),
                        includeServices: false,
                    ),

                    static::invoiceTotalsSection(),

                    View::make('components.loading'),

                ]

            );
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->emptyStateHeading(__('fields.table_empty_state'))
            ->columns([
                static::invoicePurchaseReturnIndicatorTableColumn(),

                Tables\Columns\TextColumn::make('no')
                    ->label(__('fields.invoice'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('date')
                    ->label(__('fields.date'))
                    ->dateTime('M j, Y')
                    ->searchable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label(__('fields.supplier'))
                    ->url(function ($record) {
                        return SupplierResource::getUrl('view', ['record' => $record->supplier_id]);
                    }, true)
                    ->color(Color::Sky)
                    ->searchable(),

                static::invoiceSettlementStatusTableColumn(),

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

                Tables\Columns\TextColumn::make('purchases_total')
                    ->label(__('fields.purchases'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
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
                    })
                    ->summarize(Tables\Columns\Summarizers\Summarizer::make()
                        ->label(__('fields.total'))
                        ->using(function (Table $table) {
                            return main_currency_iso_code() . " " . format_amount($table->getRecords()->sum('items_cost'));
                        })
                    ),

                Tables\Columns\TextColumn::make('tax')
                    ->label(__('fields.tax'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->getStateUsing(function ($record) {
                        return setting('main_currency', 'SAR') . " " . format_amount($record->getTaxesAsAmount());
                    }),

                Tables\Columns\TextColumn::make('additional_costs_total')
                    ->label(__('fields.additional_costs'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
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
            ->actions([
                static::configureInvoiceTableActionGroup(Tables\Actions\ActionGroup::make([
                Tables\Actions\EditAction::make(),

                    static::shareInvoiceUrlTableAction(),

                    static::purchaseReturnInvoiceTableAction(),

//                    Tables\Actions\Action::make('download_invoice')
//                        ->label(__('fields.download_invoice'))
//                        ->icon('heroicon-o-arrow-down-tray')
//                        ->color('success')
//                        ->action(function (Invoice $record) {
//
//                            try {
//                                $invoice = (new InvoiceService())
//                                    ->filePath('invoices/purchases')
//                                    ->getInvoice($record, 0, 0, [
//                                        'Payment status' => $record->payment_status,
//                                        'Total paid' => $record->total_paid,
//                                    ]);
//
//                                Notification::make()
//                                    ->title(__('fields.invoice_download_complete') . " " . $record->no)
//                                    ->success()
//                                    ->persistent()
//                                    ->actions([
//                                        Action::make('view')
//                                            ->label(__('fields.invoice_download_view_file'))
//                                            ->button()
//                                            ->url($invoice->url(), shouldOpenInNewTab: true)
//                                    ])
//                                    ->send();
//                            } catch (\Throwable $exception) {
//                                Notification::make()
//                                    ->title(__('fields.invoice_download_error'))
//                                    ->body("")
//                                    ->danger()
//                                    ->persistent()
//                                    ->send();
//                            }
//
//                        }),

                Tables\Actions\Action::make('payment_details')
                    ->label(__('fields.payment_details'))
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                        ->url(fn (Invoice $record) => $record->getPaymentVoucherResourceUrl(), true),
                ])),
            ])
            ->groups([
                Tables\Grouping\Group::make('supplier.name')
                    ->label(__('fields.supplier')),

                Tables\Grouping\Group::make('created_at')
                    ->getTitleFromRecordUsing(fn(Invoice $record): string => $record->created_at->format('d-m-Y'))
                    ->label(__('fields.date')),
            ])
            ->filters([

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label(__('fields.supplier'))
                    ->multiple()
                    ->options(Supplier::pluck('name', 'id')),


                Tables\Filters\Filter::make('created_at')
                    ->label(__('fields.created_at'))
                    ->form([

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
                        return $indicator;
                    })
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'],
                                fn($query) => $query->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'],
                                fn($query) => $query->whereDate('created_at', '<=', $data['created_until']));
                    })
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
            ->where('temp', false)
            ->with(
                [
                    'items',
                    'purchasePayments',
                    'salesPayments',
                    'representative',
                    'supplier',
                    'user',
                    'reviewedBy',
                    'additionalCosts',
                    'purchasesReturns',
                ])
            ->withCount('purchasesReturns')
            ->latest();
    }

    public static function updateInvoicePropertiesFromLivewire($livewire, $updateUIFields = true): array
    {
        $startTime = microtime(true);

        self::updateItems($livewire);

        $prices_includes_taxes = $livewire->data['prices_includes_taxes'] ?? true;

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

                    $tax = MathService::instance()->getTaxFromTaxProfile($sub_total, $taxProfile, $prices_includes_taxes);
                    $totals['total_taxes'] += $tax;
                }
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

        if ($updateUIFields) {
            $livewire->data['total_invoice_pre_discount_pre_tax'] = format_amount($totals['total_purchases'] + $totals['total_additional_costs']);
            $livewire->data['total_discount'] = format_amount($totals['total_discount']);
            $livewire->data['total_taxes'] = format_amount($totals['total_taxes']);
            $livewire->data['total_invoice_post_discount'] = format_amount($totals['total_purchases'] + $totals['total_additional_costs'] - $totals['total_discount']);
            if ($prices_includes_taxes) {
                $livewire->data['total_invoice_with_taxes'] = format_amount($totals['total_purchases'] + $totals['total_additional_costs'] - $totals['total_discount']);

            } else {
                $livewire->data['total_invoice_with_taxes'] = format_amount($totals['total_purchases'] + $totals['total_additional_costs'] - $totals['total_discount'] + $totals['total_taxes']);
            }
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

            $price = $item['price'] ?? null;
            $qty = $item['qty'] ?? null;
            $tax = 0;

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

                $subTotal = $price * $qty;
                $original_sub_total = $price * $qty;

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
}
