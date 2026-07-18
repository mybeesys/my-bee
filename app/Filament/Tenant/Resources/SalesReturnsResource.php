<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Concerns\InteractsWithInvoiceReturnLineItems;
use App\Filament\Tenant\Resources\SalesReturnsResource\Pages;
use App\Filament\Tenant\Resources\SalesReturnsResource\RelationManagers;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SalesReturns;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalesReturnsResource extends Resource
{
    use InteractsWithInvoiceReturnLineItems;

    protected static ?string $model = SalesReturns::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-long-up';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return user_setting('fav.sales_returns', false) ? __('fields.navigation_group_favourites') : __('fields.nav_group_sales');
    }

    public static function getLabel(): ?string
    {
        return __('fields.sales_returns');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.sales_returns');
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
                    ->disabledOn('edit')
                    ->schema([
                        hidden_user_id_field(),
                        hidden_tenant_id_field(),

                        Forms\Components\ToggleButtons::make('return_mode')
                            ->label(__('fields.sales_return_source'))
                            ->options([
                                'invoice' => __('fields.sales_return_by_invoice'),
                                'customer' => __('fields.sales_return_by_customer'),
                            ])
                            ->default('invoice')
                            ->inline()
                            ->live()
                            ->disabledOn('edit')
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                if ($state === 'customer') {
                                    $set('invoice_id', null);
                                    $set('prices_includes_taxes', true);
                                } else {
                                    $set('customer_id', null);
                                }

                                $set('details', []);
                            }),

                        Forms\Components\Select::make('invoice_id')
                            ->required(fn (Forms\Get $get): bool => ($get('return_mode') ?? 'invoice') === 'invoice')
                            ->visible(fn (Forms\Get $get): bool => ($get('return_mode') ?? 'invoice') === 'invoice')
                            ->disabled(fn (Page $livewire) => $livewire instanceof Pages\EditSalesReturns)
                            ->label(__('fields.sales_invoice'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                $set('details', []);

                                if ($invoice = Invoice::find($state)) {
                                    $set('prices_includes_taxes', (bool) $invoice->prices_includes_taxes);
                                    $set('payment_terms', $invoice->payment_terms ?? 'cash');
                                }
                            })
                            ->options(function ($livewire) {
                                return static::returnableInvoiceOptions('sales', $livewire instanceof Pages\CreateSalesReturns);
                            }),

                        Forms\Components\Select::make('customer_id')
                            ->required(fn (Forms\Get $get): bool => ($get('return_mode') ?? 'invoice') === 'customer')
                            ->visible(fn (Forms\Get $get): bool => ($get('return_mode') ?? 'invoice') === 'customer')
                            ->disabled(fn (Page $livewire) => $livewire instanceof Pages\EditSalesReturns)
                            ->label(__('fields.client'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('details', []))
                            ->options(fn () => Customer::query()->orderBy('name')->pluck('name', 'id'))
                            ->createOptionForm(CustomerResource::getSchema())
                            ->createOptionUsing(function ($data) {
                                $data['tenant_id'] = filament()->getTenant()->id;
                                $model = Customer::create($data);

                                return $model->id;
                            })
                            ->createOptionAction(
                                fn (Forms\Components\Actions\Action $action) => $action->modalWidth('5xl'),
                            ),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('fields.items'))
                    ->visible(fn (Forms\Get $get): bool => filled($get('invoice_id')) || filled($get('customer_id')))
                    ->extraAttributes(['class' => 'invoice-lines-panel'])
                    ->schema([
                        static::returnLinesToolbar(),

                        TableRepeater::make('details')
                            ->required()
                            ->minItems(1)
                            ->relationship('details')
                            ->label(__('fields.items'))
                            ->headers([
                                Header::make('invoice_item_id')
                                    ->width("200px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.name')),

                                Header::make('qty')
                                    ->width("100px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.qty')),

                                Header::make('unit_price')
                                    ->width("200px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.unit_price')),

                                Header::make('discount')
                                    ->width("150px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.discount')),

                                Header::make('tax')
                                    ->width("150px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.tax')),

                                Header::make('price')
                                    ->width("200px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.price')),

                                Header::make('total')
                                    ->width("200px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.total')),

                            ])
                            ->emptyLabel(__('fields.no_records_placeholder'))
                            ->addActionLabel(__('fields.add'))
                            ->defaultItems(0)
                            ->deletable(function ($record, $state, Forms\Components\Repeater $component) {
                                return $record == null;
                            })
                            ->addable(function ($livewire, $record) {
                                return true;
                            })
                            ->deleteAction(
                                fn(Forms\Components\Actions\Action $action) => $action->requiresConfirmation(),
                            )
                            ->live()
                            ->mutateRelationshipDataBeforeFillUsing(function ($data) {
                                $item = InvoiceItem::with('invoice')->find($data['invoice_item_id']);

                                if (!$item) {
                                    return $data;
                                }

                                $pricesIncludesTaxes = (bool) ($item->invoice->prices_includes_taxes ?? true);
                                $amounts = static::formatReturnLineAmounts(
                                    static::calculateReturnLineAmounts($item, (float) $data['qty'], $pricesIncludesTaxes)
                                );

                                return array_merge($data, $amounts);
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data, $state, $record, $livewire): array {
                                $data['user_id'] = $data['user_id'] ?? filament()->auth()->id() ?? auth()->id();
                                $returnMode = $livewire->data['return_mode'] ?? 'invoice';

                                if ($returnMode === 'customer' && ! empty($data['product_line_key'])) {
                                    $template = static::findTemplateInvoiceItemForProductKey(
                                        (string) $data['product_line_key'],
                                        'sales',
                                        (int) ($livewire->data['customer_id'] ?? 0),
                                    );
                                    $data['invoice_item_id'] = $template?->id;
                                    unset($data['product_line_key']);
                                }

                                return static::normalizeReturnDetailForSave($data);
                            })
                            ->schema([

                                hidden_tenant_id_field(),
                                hidden_user_id_field(),

                                Forms\Components\Hidden::make('min_qty')->dehydrated(false),
                                Forms\Components\Hidden::make('max_qty')->dehydrated(false),

                                Forms\Components\Select::make('invoice_item_id')
                                    ->disabled(fn ($record) => $record !== null)
                                    ->visible(fn (Forms\Get $get, $livewire) => static::returnFormValue($get, 'return_mode', $livewire, 'invoice') === 'invoice')
                                    ->required(fn (Forms\Get $get, $livewire) => static::returnFormValue($get, 'return_mode', $livewire, 'invoice') === 'invoice')
                                    ->label(__('fields.name'))
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->options(function (Forms\Get $get, $livewire) {
                                        return static::returnableInvoiceItemOptionsForInvoice(
                                            (int) static::returnFormValue($get, 'invoice_id', $livewire),
                                            $livewire instanceof Pages\CreateSalesReturns
                                        );
                                    })->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        $item = InvoiceItem::with('invoice')->find($state);
                                        $pricesIncludesTaxes = $item
                                            ? static::resolveReturnPricesIncludeTaxes($item, $get)
                                            : true;

                                        if ($item) {
                                            $qty = 1;

                                            $set('min_qty', 1);
                                            $set('max_qty', $item->qty);
                                            $set('qty', $qty);
                                            static::applyReturnLineAmounts($set, $item, $qty, $pricesIncludesTaxes);
                                        } else {
                                            $set('min_qty', null);
                                            $set('max_qty', null);
                                            $set('qty', null);
                                            $set('unit_price', null);
                                            $set('tax', null);
                                            $set('discount', null);
                                            $set('price', null);
                                            $set('total', null);
                                        }
                                    }),

                                Forms\Components\Select::make('product_line_key')
                                    ->disabled(fn ($record) => $record !== null)
                                    ->visible(fn (Forms\Get $get, $livewire) => static::returnFormValue($get, 'return_mode', $livewire, 'invoice') === 'customer')
                                    ->required(fn (Forms\Get $get, $livewire) => static::returnFormValue($get, 'return_mode', $livewire, 'invoice') === 'customer')
                                    ->label(__('fields.name'))
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->options(function (Forms\Get $get, $livewire) {
                                        return static::returnableProductOptionsForCustomer(
                                            (int) static::returnFormValue($get, 'customer_id', $livewire)
                                        );
                                    })
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get, $livewire) {
                                        $pricesIncludesTaxes = (bool) static::returnFormValue($get, 'prices_includes_taxes', $livewire, true);
                                        $customerId = (int) static::returnFormValue($get, 'customer_id', $livewire);

                                        if ($state && $customerId > 0) {
                                            $available = static::getReturnableProductQty((string) $state, 'sales', $customerId);
                                            $qty = min(1, $available);

                                            $set('min_qty', $available > 0 ? 1 : null);
                                            $set('max_qty', $available > 0 ? $available : null);
                                            $set('qty', $available > 0 ? $qty : null);
                                            static::applyProductReturnLineAmounts(
                                                $set,
                                                (string) $state,
                                                $qty,
                                                $pricesIncludesTaxes,
                                                'sales',
                                                $customerId,
                                            );
                                        } else {
                                            $set('min_qty', null);
                                            $set('max_qty', null);
                                            $set('qty', null);
                                            $set('unit_price', null);
                                            $set('tax', null);
                                            $set('discount', null);
                                            $set('price', null);
                                            $set('total', null);
                                        }
                                    }),

                                TextInput::make('qty')
                                    ->disabled(fn($record) => $record !== null)
                                    ->label(__('fields.qty'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(fn(Forms\Get $get) => $get('min_qty'))
                                    ->maxValue(fn(Forms\Get $get) => $get('max_qty'))
                                    ->live(true)
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set, $livewire) {
                                        $returnMode = static::returnFormValue($get, 'return_mode', $livewire, 'invoice');
                                        $pricesIncludesTaxes = (bool) static::returnFormValue($get, 'prices_includes_taxes', $livewire, true);

                                        if ($returnMode === 'customer' && filled($get('product_line_key')) && $state) {
                                            static::applyProductReturnLineAmounts(
                                                $set,
                                                (string) $get('product_line_key'),
                                                $state,
                                                $pricesIncludesTaxes,
                                                'sales',
                                                (int) static::returnFormValue($get, 'customer_id', $livewire),
                                            );

                                            return;
                                        }

                                        $item = static::resolvePricingInvoiceItem($get, $livewire);

                                        if ($item && $state) {
                                            static::applyReturnLineAmounts($set, $item, $state, $pricesIncludesTaxes);
                                        } else {
                                            $set('unit_price', null);
                                            $set('price', null);
                                            $set('discount', null);
                                            $set('tax', null);
                                            $set('total', null);
                                        }
                                    })
                                    ->extraInputAttributes(function (Forms\Get $get) {
                                        return [
                                            'min' => $get('min_qty'),
                                            'max' => $get('max_qty'),
                                        ];
                                    }),

                                TextInput::make('unit_price')
                                    ->label(__('fields.unit_price'))
                                    ->dehydrated(false)
                                    ->readOnly(),

                                TextInput::make('discount')
                                    ->label(__('fields.discount'))
                                    ->readOnly(),

                                TextInput::make('tax')
                                    ->label(__('fields.tax'))
                                    ->readOnly(),

                                TextInput::make('price')
                                    ->label(__('fields.price'))
                                    ->readOnly(),

                                TextInput::make('total')
                                    ->label(__('fields.total'))
                                    ->readOnly(),

                            ])
                    ]),

                static::returnPaymentSection(),

                Forms\Components\Section::make()->schema([
                    Forms\Components\Textarea::make('notes')
                        ->cols(5)
                        ->rows(5)
                        ->label(__('fields.notes')),
                ]),

                View::make('components.loading'),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('fields.table_empty_state'))
            ->columns([
                Tables\Columns\TextColumn::make('invoice.no')
                    ->label(__('fields.invoice'))
                    ->getStateUsing(function (SalesReturns $record): string {
                        if ($record->isCustomerReturn()) {
                            $numbers = $record->details
                                ->map(fn ($detail) => $detail->invoiceItem?->invoice?->no)
                                ->filter()
                                ->unique()
                                ->values();

                            if ($numbers->count() > 1) {
                                return __('fields.sales_return_multiple_invoices');
                            }

                            return $numbers->first() ?? '—';
                        }

                        return $record->invoice?->no ?? '—';
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            $query->whereRelation('invoice', 'no', 'like', "%{$search}%")
                                ->orWhereHas('details.invoiceItem.invoice', fn (Builder $q) => $q->where('no', 'like', "%{$search}%"));
                        });
                    }),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('fields.client'))
                    ->getStateUsing(fn (SalesReturns $record): ?string => $record->resolveCustomer()?->name)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            $query->whereRelation('customer', 'name', 'like', "%{$search}%")
                                ->orWhereRelation('invoice.customer', 'name', 'like', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('qty')
                    ->label(__('fields.qty'))
                    ->getStateUsing(function ($record) {
                        return $record->details->sum('qty');
                    }),

                Tables\Columns\TextColumn::make('notes')
                    ->label(__('fields.notes'))
                    ->limit(50)
                    ->getStateUsing(function ($record) {
                        return strip_tags($record->notes);
                    })
                    ->searchable(),


                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.created_at'))
                    ->dateTime('M j, Y H:i'),
            ])
            ->filters([

                Tables\Filters\Filter::make('date')
                    ->indicator('advanced_filter')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')->label(__('fields.created_from')),
                        Forms\Components\DatePicker::make('date_until')->label(__('fields.created_until')),

                        Forms\Components\Select::make('customer_id')
                            ->label(__('fields.client'))
                            ->searchable()
                            ->options(Customer::pluck('name', 'id')),

                    ])->columns(3)
                    ->indicateUsing(function (array $data): ?string {
                        $indicator = null;
                        if ($data['date_from'] or $data['date_until']) {
                            $indicator = $indicator . __('fields.date');
                        }
                        if ($data['customer_id']) {
                            $indicator = $indicator . __('fields.client');
                        }
                        return $indicator;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['customer_id'],
                                fn (Builder $query, $customerId): Builder => $query->where(function (Builder $query) use ($customerId): void {
                                    $query->where('customer_id', $customerId)
                                        ->orWhereRelation('invoice', 'customer_id', $customerId);
                                }),
                            )
                            ->when(
                                $data['date_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    })

            ], Tables\Enums\FiltersLayout::Modal)
            ->filtersFormWidth(MaxWidth::FiveExtraLarge)
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListSalesReturns::route('/'),
            'create' => Pages\CreateSalesReturns::route('/create'),
            'edit' => Pages\EditSalesReturns::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'details.invoiceItem.invoice',
            'invoice.customer',
            'customer',
            'user',
        ]);
    }

    public static function validateReturnDetailsForCreate(array $data): ?string
    {
        $details = $data['details'] ?? [];
        $returnMode = $data['return_mode'] ?? 'invoice';

        if ($returnMode === 'customer') {
            return static::validateCustomerReturnDetails($data, $details);
        }

        return static::validateInvoiceReturnDetails($data, $details);
    }

    protected static function validateInvoiceReturnDetails(array $data, array $details): ?string
    {
        $invoice = Invoice::find($data['invoice_id'] ?? null);

        if (! $invoice) {
            return __('fields.sales_return_invoice_required');
        }

        if ($invoice->status !== 'confirmed') {
            return __('fields.you_need_to_confirm_invoice_before_this_operation');
        }

        $maxTotal = $invoice->getItemsCost(false, true, true);

        if (static::sumReturnDetailsTotals($details) > $maxTotal) {
            return __('fields.to_be_returned_amount_is_greater_than_paid_amount');
        }

        return null;
    }

    protected static function validateCustomerReturnDetails(array $data, array $details): ?string
    {
        $customerId = (int) ($data['customer_id'] ?? 0);

        if ($customerId <= 0) {
            return __('fields.sales_return_customer_required');
        }

        foreach ($details as $detail) {
            $productKey = $detail['product_line_key'] ?? null;

            if (! $productKey) {
                continue;
            }

            $requested = (float) ($detail['qty'] ?? 0);
            $available = static::getReturnableProductQty((string) $productKey, 'sales', $customerId);

            if ($requested > $available) {
                return __('fields.sales_return_qty_exceeds_available');
            }
        }

        return null;
    }
}
