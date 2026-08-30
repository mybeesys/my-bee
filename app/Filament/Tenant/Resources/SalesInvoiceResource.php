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
use App\Filament\Tenant\Concerns\InlineProductLineItems;
use App\Filament\Tenant\Concerns\InvoiceDocumentFormLayout;
use App\Services\AccountingService;
use App\Services\InvoicePaymentTermsService;
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
    use InlineProductLineItems;
    use InvoiceDocumentFormLayout;

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
        return Invoice::sales()->where('temp', false)->listedInSalesModule()->count();
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

                Shout::make('order-review-hint')
                    ->visible(fn () => $form->getRecord()?->isEditable()
                        && Order::where('invoice_id', $form->getRecord()?->id)->exists())
                    ->content(__('fields.invoice_order_review_hint'))
                    ->icon('heroicon-o-information-circle')
                    ->color(Color::Sky)
                    ->columnSpanFull()
                    ->type('info'),

                Forms\Components\Section::make()
                    ->disabled(fn () => $form->getRecord()?->isLocked() ?? false)
                    ->schema([

                        Forms\Components\Hidden::make('status')
                            ->default(fn () => $form->getRecord()?->status ?? 'sale_order'),

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

                        static::invoicePaymentTermsSelect($form),

                    ])->columns(4),

                Forms\Components\Section::make(__('fields.items'))
                    ->disabled(fn () => $form->getRecord()?->isLocked() ?? false)
                    ->key('items-section')
                    ->extraAttributes(['class' => 'invoice-lines-panel'])
                                    ->schema([
                        static::invoiceLinesToolbar(),

                        TableRepeater::make('items')
                            ->dehydrated(false)
                            ->headers(static::invoiceLineTableHeaders())
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

                                Forms\Components\Hidden::make('extras_total')->dehydrated(false),

                                Forms\Components\Hidden::make('type'),
                                Forms\Components\Hidden::make('tax_profile_data'),
                                Forms\Components\Hidden::make('name')->dehydrated(false),
                                Forms\Components\Hidden::make('sub_total_before_tax')->dehydrated(false),
                                Forms\Components\Hidden::make('product_variant_id'),

                                self::inlineProductSelect(
                                    'name',
                                    fn ($livewire) => self::updateInvoicePropertiesFromLivewire($livewire),
                                ),

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
                                        if($invoiceItem = InvoiceItem::with('extras')->find($get('id'))) {
                                            $extras_total = $invoiceItem->extras_total;
                                        }else{
                                            $exts = ProductExtra::findMany($get('product_extras_ids'));
                                            $extras_total = format_amount(PricingService::instance()->getRetailItemsPrices($exts));
                                        }
                                        return $extras_total;
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
                                    ->label(__('fields.price'))
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

                            ]),
                    ]),

                static::invoiceExtrasTabs(
                    $form,
                    'items',
                    fn ($livewire) => self::updateInvoicePropertiesFromLivewire($livewire),
                ),

                static::invoiceTotalsSection(),

                Forms\Components\View::make('components.loading'),

            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->emptyStateHeading(__('fields.table_empty_state'))
            ->columns([
                static::invoiceSalesReturnIndicatorTableColumn(),

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

                static::invoiceSettlementStatusTableColumn(),

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
                    ->label(__('fields.invoice_total_with_tax'))
                    ->color(Color::Violet)
                    ->tooltip(function (Invoice $record) {
                        return numbers_to_words($record->getItemsCost(true, true, true));
                    })
                    ->getStateUsing(function (Invoice $record) {
                        return main_currency_iso_code() . ' ' . format_amount($record->getItemsCost(true, true, true));
                    })
                    ->summarize(Tables\Columns\Summarizers\Summarizer::make()
                        ->label(__('fields.total'))
                        ->using(function (Table $table) {
                            return main_currency_iso_code() . ' ' . format_amount(
                                $table->getRecords()->sum(fn (Invoice $record) => $record->getItemsCost(true, true, true))
                            );
                        })
                    ),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label(__('fields.unpaid_amount'))
                    ->color(fn (Invoice $record) => ((float) $record->total_unpaid) > 0 ? Color::Rose : Color::Emerald)
                    ->tooltip(function (Invoice $record) {
                        return numbers_to_words(max(0, (float) $record->total_unpaid));
                    })
                    ->getStateUsing(function (Invoice $record) {
                        return main_currency_iso_code() . ' ' . format_amount(max(0, (float) $record->total_unpaid));
                    })
                    ->summarize(Tables\Columns\Summarizers\Summarizer::make()
                        ->label(__('fields.total'))
                        ->using(function (Table $table) {
                            return main_currency_iso_code() . ' ' . format_amount(
                                $table->getRecords()->sum(fn (Invoice $record) => max(0, (float) $record->total_unpaid))
                            );
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

                        return $indicator;
                    })
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['customers'],
                                fn(Builder $query, $customers): Builder => $query->whereIn('customer_id', $customers),
                            )
                            ->when($data['created_from'],
                                fn($query) => $query->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'],
                                fn($query) => $query->whereDate('created_at', '<=', $data['created_until']));
                    })
            ])
            ->actions([
                static::configureInvoiceTableActionGroup(Tables\Actions\ActionGroup::make([
                    static::shareInvoiceUrlTableAction(),

                    static::salesReturnInvoiceTableAction(),

                Tables\Actions\Action::make('complete_payment')
                    ->label(__('fields.payment_details'))
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->visible(function ($record) {
                            return ! $record->paid;
                    })
                    ->action(function (Invoice $record) {
                        if ($record->salesPayments->isEmpty()) {
                            return redirect(ReceiptVoucherResource::getUrl('create', ['invoice_id' => $record->id]));
                        }

                            $rv = ReceiptVoucher::findForInvoice($record->id);

                            if ($rv) {
                            return redirect(ReceiptVoucherResource::getUrl('edit', ['record' => $rv->id, 'rv' => $rv->id]));
                            }
                    }),
                ])),
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
                    'items.taxProfile',
                    'salesPayments',
                    'customer',
                    'receiptVoucher',
                    'representative',
                    'user',
                    'reviewedBy',
                    'additionalCosts',
                    'services',
                    'salesReturns',
                ])
            ->withCount('salesReturns')
            ->latest();
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
                        ->label(__('fields.invoice_total_with_tax'))
                        ->getStateUsing(function ($record) {
                            return main_currency_iso_code() . " " . format_amount($record->getItemsCost(true, true, true));
                        }),

                    TextEntry::make('remaining_amount')
                        ->label(__('fields.unpaid_amount'))
                        ->getStateUsing(function ($record) {
                            return main_currency_iso_code() . " " . format_amount(max(0, (float) $record->total_unpaid));
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

        $rawItems = $livewire->data['items'] ?? [];

        $items = self::isInlineProductLinesOrderPayload($rawItems)
            ? self::reorderInlineProductLines($rawItems, $livewire->cachedInvoiceLineItems ?? [])
            : self::inlineProductLinesFromState($rawItems);

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
            $extras_total = count($item['product_extras_ids'] ?? []) > 0 ? PricingService::instance()->getRetailItemsPrices(ProductExtra::findMany($item['product_extras_ids'])) : 0;
            $extras_total = $extras_total * $qty;
            if($item['id'] ?? null){
                $extras_total = InvoiceItem::with('extras')->findOrFail($item['id'])->extras_total;
            }

            $discount = $item['discount'] ?? 0;
            $tax = 0;

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
            if($prices_includes_taxes){
                $livewire->data['total_invoice_with_taxes'] = format_amount($totals['total_purchases'] + $totals['total_services'] + $totals['total_additional_costs'] - $totals['total_discount']);
            }else{
                $livewire->data['total_invoice_with_taxes'] = format_amount($totals['total_purchases'] + $totals['total_services'] + $totals['total_additional_costs'] - $totals['total_discount'] + $totals['total_taxes']);
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


        $rawItems = $livewire->data['items'] ?? [];

        $items = self::isInlineProductLinesOrderPayload($rawItems)
            ? self::reorderInlineProductLines($rawItems, $livewire->cachedInvoiceLineItems ?? [])
            : self::inlineProductLinesFromState($rawItems);

        $newItems = [];
        foreach ($items as $item) {
            $item = self::normalizeInlineProductRowForSave($item);

            if (self::isEmptyInlineProductRow($item)) {
                $newItems[] = $item;

                continue;
            }

            $price = $item['price'] ?? null;
            $qty = $item['qty'] ?? null;
            $tax = 0;

            $extras_total = count($item['product_extras_ids'] ?? []) > 0 ? PricingService::instance()->getRetailItemsPrices(ProductExtra::findMany($item['product_extras_ids'])) : 0;
            $extras_total = $extras_total * $qty ?? 0;

            if($item['id'] ?? null){
                $extras_total = InvoiceItem::with('extras')->findOrFail($item['id'])->extras_total;
            }

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

                $item['sub_total_before_tax'] = format_amount($subTotal);

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
        $livewire->cachedInvoiceLineItems = $newItems;

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
