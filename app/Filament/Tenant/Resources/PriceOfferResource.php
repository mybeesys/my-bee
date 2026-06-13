<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\PriceOfferResource\Pages;
use App\Filament\Tenant\Resources\PriceOfferResource\RelationManagers;
use App\Models\AdditionalCostType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\PriceOffer;
use App\Models\PriceOfferDetails;
use App\Models\Product;
use App\Models\ProductExtra;
use App\Models\ProductVariant;
use App\Models\ServiceType;
use App\Models\TaxProfile;
use App\Models\VariantLibrary;
use App\Models\VariantLibraryOption;
use App\Rules\UniqueTenantItemRule;
use App\Services\CacheService;
use App\Services\MathService;
use App\Services\PricingService;
use App\Filament\Tenant\Concerns\InlineProductLineItems;
use App\Filament\Tenant\Concerns\InvoiceDocumentFormLayout;
use App\Services\StockService;
use Awcodes\Shout\Components\Shout;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Actions\StaticAction;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class PriceOfferResource extends Resource
{
    use InlineProductLineItems;
    use InvoiceDocumentFormLayout;
    protected static ?string $model = PriceOffer::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return user_setting('fav.sales_price_offer', false) ? __('fields.navigation_group_favourites') : __('fields.nav_group_sales');
    }

    public static function getLabel(): ?string
    {
        return __('fields.price_offer');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.price_offers');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Section::make()
                    ->schema([

                    hidden_tenant_id_field(),
                    hidden_user_id_field(),

                    TextInput::make('no')
                        ->label(__('fields.reference_code'))
                        ->readOnly()
                        ->required()
                            ->default(fn ($record) => $record == null ? generate_no(PriceOffer::class) : $record->no)
                            ->rules([new UniqueTenantItemRule(PriceOffer::class, 'no', $form->getRecord()?->id)])
                            ->columnSpan(['default' => 12, 'lg' => 4]),

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
                            ->columnSpan(['default' => 12, 'lg' => 4]),

                        TextInput::make('description')
                        ->required()
                        ->label(__('fields.description'))
                            ->columnSpan(['default' => 12, 'lg' => 4]),

                    ])
                    ->columns(12),

                Forms\Components\Section::make(__('fields.items'))
                    ->key('details-section')
                    ->extraAttributes(['class' => 'invoice-lines-panel'])
                                    ->schema([
                        static::invoiceLinesToolbar(),

                        TableRepeater::make('details')
                            ->headers(static::invoiceLineTableHeaders())
                            ->label('')
                            ->emptyLabel(__('fields.no_records_placeholder'))
                            ->addActionLabel(__('fields.add_new_row'))
                            ->addAction(fn (Forms\Components\Actions\Action $action) => static::invoiceLinesAddAction($action))
                            ->addable(true)
                            ->defaultItems(fn () => $form->getRecord() === null ? 1 : 0)
                            ->minItems(1)
                            ->extraAttributes(['class' => 'invoice-lines-table'])
                            ->deleteAction(
                                fn(Forms\Components\Actions\Action $action) => $action->requiresConfirmation(),
                            )
                            ->afterStateHydrated(function ($livewire) {
                                $details = self::inlineProductLinesFromState($livewire->data['details'] ?? []);

                                foreach ($details as $key => $item) {
                                    $details[$key] = self::hydrateInlineProductRow($item);
                                }

                                $livewire->data['details'] = $details;
                                $livewire->cachedInvoiceLineItems = $details;

                                self::updateInvoicePropertiesFromLivewire($livewire);
                            })
                            ->afterStateUpdated(function ($state, $livewire) {
                                if (! is_array($state)) {
                                    self::updateInvoicePropertiesFromLivewire($livewire);

                                    return;
                                }

                                $previous = $livewire->cachedInvoiceLineItems
                                    ?? self::inlineProductLinesFromState($livewire->data['details'] ?? []);

                                if (self::isInlineProductLinesOrderPayload($state)) {
                                    $details = self::reorderInlineProductLines($state, $previous);
                                    $livewire->data['details'] = $details;
                                    $livewire->cachedInvoiceLineItems = $details;
                                } else {
                                    $details = self::inlineProductLinesFromState($state);
                                    $livewire->cachedInvoiceLineItems = $details;
                                }

                                self::updateInvoicePropertiesFromLivewire($livewire);
                            })
                            ->live()
                            ->schema([

                                hidden_tenant_id_field(),

                                hidden_user_id_field(),

                                Forms\Components\Hidden::make('extras_total')->dehydrated(false),

                                Forms\Components\Hidden::make('type'),
                                Forms\Components\Hidden::make('item_id'),
                                Forms\Components\Hidden::make('item_type'),
                                Forms\Components\Hidden::make('unit_price'),
                                Forms\Components\Hidden::make('display_name')->dehydrated(false),
                                Forms\Components\Hidden::make('sub_total_before_tax')->dehydrated(false),
                                Forms\Components\Hidden::make('product_variant_id'),

                                self::inlineProductSelect(
                                    'display_name',
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
                                        if($priceOfferDetails = PriceOfferDetails::with('offerDetailsExtras')->find($get('id'))) {
                                            $extras_total = $priceOfferDetails->extras_total;
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
//                                    ->readOnly(),


                                TextInput::make('qty')
                                    ->label(__('fields.qty'))
                                    ->live(true)
                                    ->numeric()
                                    ->extraInputAttributes(['min' => 1, 'max' => 250000], true)
                                    ->minValue(1)
                                    ->maxValue(250000)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get, $livewire) {
                                        if ($state) {
                                            $set('sub_total', format_amount($state * $get('unit_price')));
                                        }
                                        self::updateInvoicePropertiesFromLivewire($livewire);
                                    })
                                    ->translateFrontValidationGt()
                                    ->required(),

                                TextInput::make('price')
                                    ->label(__('fields.price'))
//                                    ->prefixIcon('heroicon-o-calculator')
                                    ->numeric()
                                    ->required(),

                                TextInput::make('discount')
                                    ->readOnly(fn (Forms\Get $get) => $get('data.discount_option_overall', true) === true)
                                    ->label(__('fields.discount_amount'))
                                    ->numeric()
                                    ->default(0)
                                    ->extraInputAttributes(['min' => 0, 'max' => PHP_INT_MAX])
                                    ->live(true)
                                    ->afterStateUpdated(fn ($livewire) => self::updateInvoicePropertiesFromLivewire($livewire)),

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
                    'details',
                    fn ($livewire) => self::updateInvoicePropertiesFromLivewire($livewire),
                ),

                static::invoiceTotalsSection(),

                View::make('components.loading'),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('fields.table_empty_state'))
            ->columns([

                Tables\Columns\TextColumn::make('no')
                    ->label(__('fields.reference_code'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->limit(70)
                    ->label(__('fields.description'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('fields.client'))
                    ->url(function ($record) {
                        return CustomerResource::getUrl('edit', ['record' => $record->customer_id]);
                    }, true)
                    ->color(Color::Sky)
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.created_at'))
                    ->dateTime('j M , Y H:i')
                    ->sortable(),
            ])
            ->filters([

                Tables\Filters\Filter::make('date')
                    ->columnSpanFull()
                    ->indicator('advanced_filter')
                    ->form([

                       Select::make('customers')
                            ->label(__('fields.client'))
                            ->multiple()
                            ->options(PriceOffer::with('customer')->get()->pluck('customer.name', 'customer.id')),

                        Forms\Components\DatePicker::make('date_from')->label(__('fields.created_from')),
                        Forms\Components\DatePicker::make('date_until')->label(__('fields.created_until')),

                    ])
                    ->indicateUsing(function (array $data): ?string {
                        $indicator = null;
                        if ($data['date_from'] or $data['date_until']) {
                            $indicator = $indicator . __('fields.date');
                        }
                        if ($data['customers']) {
                            $indicator = $indicator . __('fields.client');
                        }
                        return $indicator;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['customers'],
                                fn(Builder $query, $customers): Builder => $query->whereIn('customer_id', $customers),
                            )
                            ->when(
                                $data['date_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })

            ])
            ->actions([
                static::configureInvoiceTableActionGroup(Tables\Actions\ActionGroup::make([
                Tables\Actions\Action::make('price_offer_url')
                    ->label(__('fields.price_offer_url'))
                        ->icon('heroicon-o-link')
                    ->color(Color::Sky)
                        ->url(fn (PriceOffer $record) => $record->url, true),

                Tables\Actions\Action::make('make_sales_invoice_from_price_offer')
                        ->label(__('fields.convert_price_offer_to_sales_invoice'))
                        ->icon('heroicon-o-document-text')
                    ->requiresConfirmation()
                    ->color(Color::Green)
                        ->url(fn (PriceOffer $record) => SalesInvoiceResource::getUrl('create', ['price_offer_id' => $record->id])),

                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    ->action(function ($record) {
                        foreach ($record->details as $detail) {
                            $detail->offerDetailsExtras()->delete();
                            $detail->delete();
                        }
                        $record->delete();
                        fns()->deleted();
                        }),
                ])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
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
            'index' => Pages\ListPriceOffers::route('/'),
            'create' => Pages\CreatePriceOffer::route('/create'),
            'edit' => Pages\EditPriceOffer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['additionalCosts', 'services'])->latest();
    }

    public static function updateInvoicePropertiesFromLivewire($livewire, $updateUIFields = true): array
    {
        $startTime = microtime(true);

        self::updateItems($livewire);

        $prices_includes_taxes = $livewire->data['prices_includes_taxes'] ?? true;

        $items = $livewire->data['details'] ?? [];
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
            $extras_total = $extras_total * $qty ?? 0;

            if($item['id'] ?? null){
                $extras_total = PriceOfferDetails::with('offerDetailsExtras')->findOrFail($item['id'])->extras_total;
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

                    if (is_number($discount))
                        $sub_total -= $discount;

                    $tax = MathService::instance()->getTaxFromTaxProfile($sub_total, $taxProfile, $prices_includes_taxes);
//                    $totals['total_taxes'] += $sub_total * ($taxProfile->total_percentages / 100);
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

        $rawDetails = $livewire->data['details'] ?? [];

        $details = self::isInlineProductLinesOrderPayload($rawDetails)
            ? self::reorderInlineProductLines($rawDetails, $livewire->cachedInvoiceLineItems ?? [])
            : self::inlineProductLinesFromState($rawDetails);

        $newItems = [];

        foreach ($details as $item) {
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
                $extras_total = PriceOfferDetails::with('offerDetailsExtras')->findOrFail($item['id'])->extras_total;
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
                        if(!$prices_includes_taxes){
                            $subTotal += $tax;
                        }
                    }
                }

                $item['tax'] = number_format($tax, currency_decimals(), '.', '');

                $item['sub_total'] = format_amount($subTotal);

            }
            $newItems[] = $item;
        }

        $livewire->data['details'] = $newItems;
        $livewire->cachedInvoiceLineItems = $newItems;

        $newServices = [];

        foreach ($livewire->data['services'] ?? [] as $service) {
            $taxProfile = TaxProfile::with('taxes')->find($service['tax_profile_id']);
            $price = $service['price'] ?? null;
            if ($taxProfile and $price) {
                $service['tax_profile_data'] = $taxProfile->toArray();
                $tax = MathService::instance()->getTaxFromTaxProfile($price, $taxProfile, $prices_includes_taxes);
                $service['tax'] = number_format($tax, currency_decimals(), '.', '');
                if($prices_includes_taxes){
                    $service['total'] = number_format($price, currency_decimals(), '.', '');
                }else{
                    $service['total'] = number_format($price + $tax, currency_decimals(), '.', '');
                }
            }else{
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
                if($prices_includes_taxes){
                    $additionalCost['total'] = number_format($cost, currency_decimals(), '.', '');
                }else{
                    $additionalCost['total'] = number_format($cost + $tax, currency_decimals(), '.', '');
                }
            }else{
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
