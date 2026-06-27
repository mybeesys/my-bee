<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Concerns\InlineProductLineItems;
use App\Filament\Tenant\Concerns\InvoiceDocumentFormLayout;
use App\Filament\Tenant\Resources\SupplyOrderResource\Pages;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SupplyOrder;
use App\Rules\UniqueTenantItemRule;
use Awcodes\TableRepeater\Components\TableRepeater;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupplyOrderResource extends Resource
{
    use InlineProductLineItems;
    use InvoiceDocumentFormLayout;

    protected static ?string $slug = 'supply-orders';
    protected static ?string $model = SupplyOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return user_setting('fav.supply_order', false) ? __('fields.navigation_group_favourites') : __('fields.nav_group_purchases');
    }

    public static function getLabel(): ?string
    {
        return __('fields.supply_order');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.supply_orders');
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

                        Forms\Components\TextInput::make('no')
                            ->label(__('fields.reference_code'))
                            ->readOnly()
                            ->required()
                            ->default(fn ($record) => $record == null ? generate_no(SupplyOrder::class) : $record->no)
                            ->rules([new UniqueTenantItemRule(SupplyOrder::class, 'no', $form->getRecord()?->id)])
                            ->columnSpan(['default' => 12, 'lg' => 4]),

                        Forms\Components\Select::make('supplier_id')
                            ->required()
                            ->label(__('fields.supplier'))
                            ->searchable()
                            ->options(Supplier::pluck('name', 'id'))
                            ->live()
                            ->createOptionForm(SupplierResource::getQuickCreateSchema())
                            ->createOptionUsing(function ($data) {
                                $data['tenant_id'] = filament()->getTenant()->id;
                                $model = Supplier::create($data);

                                return $model->id;
                            })
                            ->createOptionAction(
                                fn (Forms\Components\Actions\Action $action) => $action->modalWidth('md'),
                            )
                            ->columnSpan(['default' => 12, 'lg' => 4]),

                        Forms\Components\TextInput::make('description')
                            ->required()
                            ->label(__('fields.description'))
                            ->columnSpan(['default' => 12, 'lg' => 4]),

                    ])
                    ->columns(12),

                Forms\Components\Section::make(__('fields.order_details'))
                    ->disabled($form->getRecord()?->locked_at !== null)
                    ->key('details-section')
                    ->extraAttributes(['class' => 'invoice-lines-panel'])
                    ->schema([
                        static::invoiceLinesToolbar(showPricesToggle: false),

                        TableRepeater::make('details')
                            ->dehydrated(false)
                            ->headers(static::supplyOrderLineTableHeaders())
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
                                $details = self::inlineProductLinesFromState($livewire->data['details'] ?? []);

                                foreach ($details as $key => $detail) {
                                    $details[$key] = self::hydrateInlineProductRow($detail);
                                }

                                $livewire->data['details'] = $details;
                                $livewire->cachedInvoiceLineItems = $details;
                            })
                            ->afterStateUpdated(function ($state, $livewire) {
                                if (! is_array($state)) {
                                    return;
                                }

                                $previous = $livewire->cachedInvoiceLineItems
                                    ?? self::inlineProductLinesFromState($livewire->data['details'] ?? []);

                                if (self::isInlineProductLinesOrderPayload($state)) {
                                    $details = self::reorderInlineProductLines($state, $previous);
                                    $livewire->data['details'] = $details;
                                    $livewire->cachedInvoiceLineItems = $details;
                                } else {
                                    $livewire->cachedInvoiceLineItems = self::inlineProductLinesFromState($state);
                                }
                            })
                            ->schema([

                                hidden_tenant_id_field(),

                                hidden_user_id_field(),

                                Forms\Components\Hidden::make('item_id')->dehydrated(false),
                                Forms\Components\Hidden::make('item_type')->dehydrated(false),
                                Forms\Components\Hidden::make('type'),
                                Forms\Components\Hidden::make('display_name'),
                                Forms\Components\Hidden::make('product_variant_id'),

                                self::inlineProductSelect(
                                    'display_name',
                                    fn ($livewire) => null,
                                    prefillUnitPrice: false,
                                    limitQtyByStock: false,
                                ),

                                TextInput::make('qty')
                                    ->label(__('fields.qty'))
                                    ->required()
                                    ->numeric()
                                    ->live(true)
                                    ->minValue(1)
                                    ->maxValue(250000)
                                    ->extraInputAttributes(['min' => 1, 'max' => 250000], true)
                                    ->translateFrontValidationGt(),

                            ])
                    ]),
                View::make('components.loading'),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('fields.table_empty_state'))
            ->columns([
                Tables\Columns\TextColumn::make('no')->label(__('fields.reference_code'))->searchable(),
                Tables\Columns\TextColumn::make('supplier.name')->label(__('fields.supplier'))->searchable(),
                Tables\Columns\TextColumn::make('description')->label(__('fields.description'))->limit(50)->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                static::configureInvoiceTableActionGroup(Tables\Actions\ActionGroup::make([
                    static::shareSupplyOrderUrlTableAction(),

                    Tables\Actions\Action::make('make_purchases_invoice_from_supply_order')
                        ->label(__('fields.make_purchases_invoice_from_supply_order'))
                        ->icon('heroicon-o-document-plus')
                        ->requiresConfirmation()
                        ->color(Color::Green)
                        ->url(fn (SupplyOrder $record) => PurchaseInvoiceResource::getUrl('create', ['supply_order_id' => $record->id])),

                    Tables\Actions\EditAction::make(),

                    Tables\Actions\DeleteAction::make()->action(function ($record) {
                        $record->details()->delete();
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
            'index' => Pages\ListSupplyOrders::route('/'),
            'create' => Pages\CreateSupplyOrder::route('/create'),
            'edit' => Pages\EditSupplyOrder::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['details.item', 'supplier'])->latest();
    }

}
