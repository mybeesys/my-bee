<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Concerns\InteractsWithInvoiceReturnLineItems;
use App\Filament\Tenant\Resources\PurchasesReturnsResource\Pages;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PurchasesReturns;
use App\Models\Supplier;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchasesReturnsResource extends Resource
{
    use InteractsWithInvoiceReturnLineItems;

    protected static ?string $model = PurchasesReturns::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-long-up';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return user_setting('fav.purchases_returns', false) ? __('fields.navigation_group_favourites') : __('fields.nav_group_purchases');
    }

    public static function getLabel(): ?string
    {
        return __('fields.purchases_returns');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.purchases_returns');
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
                            ->label(__('fields.purchase_return_source'))
                            ->options([
                                'invoice' => __('fields.purchase_return_by_invoice'),
                                'supplier' => __('fields.purchase_return_by_supplier'),
                            ])
                            ->default('invoice')
                            ->inline()
                            ->live()
                            ->disabledOn('edit')
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                if ($state === 'supplier') {
                                    $set('invoice_id', null);
                                    $set('prices_includes_taxes', true);
                                } else {
                                    $set('supplier_id', null);
                                }

                                $set('details', []);
                            }),

                        Forms\Components\Select::make('invoice_id')
                            ->required(fn (Forms\Get $get): bool => ($get('return_mode') ?? 'invoice') === 'invoice')
                            ->visible(fn (Forms\Get $get): bool => ($get('return_mode') ?? 'invoice') === 'invoice')
                            ->disabled(fn (Page $livewire) => $livewire instanceof Pages\EditPurchasesReturns)
                            ->label(__('fields.purchase_invoice'))
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
                                return static::returnableInvoiceOptions('purchases', $livewire instanceof Pages\CreatePurchasesReturns);
                            }),

                        Forms\Components\Select::make('supplier_id')
                            ->required(fn (Forms\Get $get): bool => ($get('return_mode') ?? 'invoice') === 'supplier')
                            ->visible(fn (Forms\Get $get): bool => ($get('return_mode') ?? 'invoice') === 'supplier')
                            ->disabled(fn (Page $livewire) => $livewire instanceof Pages\EditPurchasesReturns)
                            ->label(__('fields.supplier'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('details', []))
                            ->options(fn () => Supplier::query()->orderBy('name')->pluck('name', 'id')),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('fields.items'))
                    ->visible(fn (Forms\Get $get): bool => filled($get('invoice_id')) || filled($get('supplier_id')))
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
                                    ->width('200px')
                                    ->align(fn () => app()->getLocale() == 'ar' ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.name')),
                                Header::make('qty')
                                    ->width('100px')
                                    ->align(fn () => app()->getLocale() == 'ar' ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.qty')),
                                Header::make('unit_price')
                                    ->width('200px')
                                    ->align(fn () => app()->getLocale() == 'ar' ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.unit_price')),
                                Header::make('discount')
                                    ->width('150px')
                                    ->align(fn () => app()->getLocale() == 'ar' ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.discount')),
                                Header::make('tax')
                                    ->width('150px')
                                    ->align(fn () => app()->getLocale() == 'ar' ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.tax')),
                                Header::make('price')
                                    ->width('200px')
                                    ->align(fn () => app()->getLocale() == 'ar' ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.price')),
                                Header::make('total')
                                    ->width('200px')
                                    ->align(fn () => app()->getLocale() == 'ar' ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.total')),
                            ])
                            ->emptyLabel(__('fields.no_records_placeholder'))
                            ->addActionLabel(__('fields.add'))
                            ->defaultItems(0)
                            ->deletable(fn ($record) => $record == null)
                            ->addable(fn () => true)
                            ->deleteAction(fn (Forms\Components\Actions\Action $action) => $action->requiresConfirmation())
                            ->live()
                            ->mutateRelationshipDataBeforeFillUsing(function ($data) {
                                $item = InvoiceItem::with('invoice')->find($data['invoice_item_id']);

                                if (! $item) {
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

                                if ($returnMode === 'supplier' && ! empty($data['product_line_key'])) {
                                    $template = static::findTemplateInvoiceItemForProductKey(
                                        (string) $data['product_line_key'],
                                        'purchases',
                                        supplierId: (int) ($livewire->data['supplier_id'] ?? 0),
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
                                            $livewire instanceof Pages\CreatePurchasesReturns
                                        );
                                    })
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
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
                                    ->visible(fn (Forms\Get $get, $livewire) => static::returnFormValue($get, 'return_mode', $livewire, 'invoice') === 'supplier')
                                    ->required(fn (Forms\Get $get, $livewire) => static::returnFormValue($get, 'return_mode', $livewire, 'invoice') === 'supplier')
                                    ->label(__('fields.name'))
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->options(function (Forms\Get $get, $livewire) {
                                        return static::returnableProductOptionsForSupplier(
                                            (int) static::returnFormValue($get, 'supplier_id', $livewire)
                                        );
                                    })
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get, $livewire) {
                                        $pricesIncludesTaxes = (bool) static::returnFormValue($get, 'prices_includes_taxes', $livewire, true);
                                        $supplierId = (int) static::returnFormValue($get, 'supplier_id', $livewire);

                                        if ($state && $supplierId > 0) {
                                            $available = static::getReturnableProductQty((string) $state, 'purchases', supplierId: $supplierId);
                                            $qty = min(1, $available);

                                            $set('min_qty', $available > 0 ? 1 : null);
                                            $set('max_qty', $available > 0 ? $available : null);
                                            $set('qty', $available > 0 ? $qty : null);
                                            static::applyProductReturnLineAmounts(
                                                $set,
                                                (string) $state,
                                                $qty,
                                                $pricesIncludesTaxes,
                                                'purchases',
                                                supplierId: $supplierId,
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
                                    ->disabled(fn ($record) => $record !== null)
                                    ->label(__('fields.qty'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(fn (Forms\Get $get) => $get('min_qty'))
                                    ->maxValue(fn (Forms\Get $get) => $get('max_qty'))
                                    ->live(true)
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set, $livewire) {
                                        $returnMode = static::returnFormValue($get, 'return_mode', $livewire, 'invoice');
                                        $pricesIncludesTaxes = (bool) static::returnFormValue($get, 'prices_includes_taxes', $livewire, true);

                                        if ($returnMode === 'supplier' && filled($get('product_line_key')) && $state) {
                                            static::applyProductReturnLineAmounts(
                                                $set,
                                                (string) $get('product_line_key'),
                                                $state,
                                                $pricesIncludesTaxes,
                                                'purchases',
                                                supplierId: (int) static::returnFormValue($get, 'supplier_id', $livewire),
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
                                    ->extraInputAttributes(fn (Forms\Get $get) => [
                                        'min' => $get('min_qty'),
                                        'max' => $get('max_qty'),
                                    ]),

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
                            ]),
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
                    ->getStateUsing(function (PurchasesReturns $record): string {
                        if ($record->isSupplierReturn()) {
                            $numbers = $record->details
                                ->map(fn ($detail) => $detail->invoiceItem?->invoice?->no)
                                ->filter()
                                ->unique()
                                ->values();

                            if ($numbers->count() > 1) {
                                return __('fields.purchase_return_multiple_invoices');
                            }

                            return $numbers->first() ?? '—';
                        }

                        return $record->invoice?->no ?? '—';
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label(__('fields.supplier'))
                    ->getStateUsing(fn (PurchasesReturns $record): ?string => $record->resolveSupplier()?->name)
                    ->searchable(),

                Tables\Columns\TextColumn::make('qty')
                    ->label(__('fields.qty'))
                    ->getStateUsing(fn ($record) => $record->details->sum('qty')),

                Tables\Columns\TextColumn::make('notes')
                    ->label(__('fields.notes'))
                    ->limit(50)
                    ->getStateUsing(fn ($record) => strip_tags($record->notes))
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.created_at'))
                    ->dateTime('M j, Y H:i'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->action(function (PurchasesReturns $record) {
                    $record->details()->delete();
                    $record->delete();
                    fns()->deleted();
                }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchasesReturns::route('/'),
            'create' => Pages\CreatePurchasesReturns::route('/create'),
            'edit' => Pages\EditPurchasesReturns::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'details.invoiceItem.invoice',
            'invoice.supplier',
            'supplier',
            'user',
        ]);
    }

    public static function validateReturnDetailsForCreate(array $data): ?string
    {
        $details = $data['details'] ?? [];
        $returnMode = $data['return_mode'] ?? 'invoice';

        if ($returnMode === 'supplier') {
            return static::validateSupplierReturnDetails($data, $details);
        }

        return static::validatePurchaseInvoiceReturnDetails($data, $details);
    }

    protected static function validatePurchaseInvoiceReturnDetails(array $data, array $details): ?string
    {
        $invoice = Invoice::find($data['invoice_id'] ?? null);

        if (! $invoice) {
            return __('fields.purchase_return_invoice_required');
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

    protected static function validateSupplierReturnDetails(array $data, array $details): ?string
    {
        $supplierId = (int) ($data['supplier_id'] ?? 0);

        if ($supplierId <= 0) {
            return __('fields.purchase_return_supplier_required');
        }

        foreach ($details as $detail) {
            $productKey = $detail['product_line_key'] ?? null;

            if (! $productKey) {
                continue;
            }

            $requested = (float) ($detail['qty'] ?? 0);
            $available = static::getReturnableProductQty((string) $productKey, 'purchases', supplierId: $supplierId);

            if ($requested > $available) {
                return __('fields.sales_return_qty_exceeds_available');
            }
        }

        return null;
    }
}
