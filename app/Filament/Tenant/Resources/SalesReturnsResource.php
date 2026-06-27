<?php

namespace App\Filament\Tenant\Resources;

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

                        Forms\Components\Select::make('invoice_id')
                            ->required()
                            ->disabled(fn(Page $livewire) => $livewire instanceof Pages\EditSalesReturns)
                            ->label(__('fields.sales_invoice'))
                            ->searchable()
                            ->live()
                            ->options(function ($livewire) {
                                $data = [];
                                if ($livewire instanceof Pages\CreateSalesReturns)
                                    $invoices = Invoice::with(['customer'])->doesntHave('salesReturns')->sales()->where('temp', 0)->get();
                                else
                                    $invoices = Invoice::with(['customer'])->sales()->where('temp', 0)->get();

                                foreach ($invoices as $invoice) {
                                    $data[$invoice->id] = $invoice->customer->name . " - " . $invoice->no;
                                }
                                return $data;
                            }),
                    ]),

                Forms\Components\Section::make()
                    ->visible(fn(Forms\Get $get) => $get('invoice_id') !== null)
                    ->schema([
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
                                $price = InvoiceItem::find($data['invoice_item_id'])->price;

                                $data['discount'] = number_format($data['discount'], currency_decimals(), '.', ',');
                                $data['tax'] = number_format($data['tax'], currency_decimals(), '.', ',');
                                $data['price'] = number_format($data['price'], currency_decimals(), '.', ',');
                                $data['total'] = number_format($data['total'], currency_decimals(), '.', ',');
                                $data['unit_price'] = number_format($price, currency_decimals(), '.', ',');
                                $data['price'] = number_format($data['qty'] * $price, currency_decimals(), '.', ',');
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                $data['user_id'] = $data['user_id'] ?? filament()->auth()->id() ?? auth()->id();

                                return $data;
                            })
                            ->schema([

                                hidden_tenant_id_field(),
                                hidden_user_id_field(),

                                Forms\Components\Hidden::make('min_qty')->dehydrated(false),
                                Forms\Components\Hidden::make('max_qty')->dehydrated(false),

                                Forms\Components\Select::make('invoice_item_id')
                                    ->disabled(fn($record) => $record !== null)
                                    ->required()
                                    ->label(__('fields.name'))
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->options(function (Forms\Get $get, $livewire) {
                                        $invoice = $get('data.invoice_id', true);
                                        $data = [];
                                        foreach (InvoiceItem::with(['product', 'productVariant'])->where('invoice_id', $invoice)->get() as $item) {
                                            $data[$item->id] = $item->name;

                                            if ($item->qty > 0) {
                                                $data[$item->id] = $item->name;
                                            } else {
                                                if ($livewire instanceof Pages\CreateSalesReturns)
                                                    fns()->sendWarning("تم إخفاء عناصر تم إرجاعها مسبقآ");
                                            }
                                        }
                                        return $data;
                                    })->afterStateUpdated(function ($state, Forms\Set $set, $record) {
                                        $item = InvoiceItem::find($state);

                                        if ($item) {
                                            $qty = 1;
                                            $tax = ($item->tax / $item->qty);
                                            $discount = $item->discount;

                                            $total = number_format(($qty * $item->price) + $tax - $discount, currency_decimals(), '.', ',');

                                            $set('min_qty', 1);
                                            $set('max_qty', $item->qty);
                                            $set('qty', $qty);
                                            $set('discount', number_format($discount, currency_decimals(), '.', ','));
                                            $set('tax', number_format($tax, currency_decimals(), '.', ','));
                                            $set('unit_price', number_format($item->price, currency_decimals(), '.', ','));
                                            $set('price', number_format($qty * $item->price, currency_decimals(), '.', ','));
                                            $set('total', $total);

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
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                        $item = InvoiceItem::find($get('invoice_item_id'));

                                        if ($item and $state) {
                                            $tax_per_item = $item->tax / $item->qty;

                                            $tax = $tax_per_item * $state;
                                            $discount = $item->discount;

                                            $total = number_format(($state * $item->price) + $tax - $discount, currency_decimals(), '.', ',');

                                            $set('unit_price', number_format($item->price, currency_decimals(), '.', ','));
                                            $set('price', number_format($state * $item->price, currency_decimals(), '.', ','));
                                            $set('discount', number_format($discount, currency_decimals(), '.', ','));
                                            $set('tax', number_format($tax, currency_decimals(), '.', ','));
                                            $set('total', $total);
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
                    ->searchable(),

                Tables\Columns\TextColumn::make('invoice.customer.name')
                    ->label(__('fields.client'))
                    ->searchable(),

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
                                fn(Builder $query, $codes): Builder => $query->whereRelation('invoice', 'customer_id', $data['customer_id']),
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
        return parent::getEloquentQuery()->with(['details', 'invoice.customer', 'user']);
    }
}
