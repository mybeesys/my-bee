<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\SalesReturnsResource\Pages;
use App\Filament\Tenant\Resources\SalesReturnsResource\RelationManagers;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SalesReturns;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
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
                Forms\Components\Section::make()->schema([
                    hidden_user_id_field(),
                    hidden_tenant_id_field(),

                    Forms\Components\Select::make('invoice_id')
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
                                    ->width("150px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.unit_price')),

                                Header::make('price')
                                    ->width("150px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.price')),
                            ])
                            ->emptyLabel(__('fields.no_records_placeholder'))
                            ->addActionLabel(__('fields.add'))
                            ->defaultItems(0)
                            ->deleteAction(
                                fn(Forms\Components\Actions\Action $action) => $action->requiresConfirmation(),
                            )
                            ->live()
                            ->mutateRelationshipDataBeforeFillUsing(function ($data) {
                                $price = InvoiceItem::find($data['invoice_item_id'])->price;

                                $data['unit_price'] = number_format($price, currency_decimals(), '.', ',');
                                $data['price'] = number_format($data['qty'] * $price, currency_decimals(), '.', ',');
                                return $data;
                            })
                            ->schema([

                                hidden_tenant_id_field(),
                                hidden_user_id_field(),

                                Forms\Components\Hidden::make('min_qty')->dehydrated(false),
                                Forms\Components\Hidden::make('max_qty')->dehydrated(false),

                                Forms\Components\Select::make('invoice_item_id')
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
                                            $set('min_qty', 1);
                                            $set('max_qty', $item->qty);
                                            $set('qty', 1);
                                            $set('unit_price', number_format($item->price, currency_decimals(), '.', ','));
                                            $set('price', number_format(1 * $item->price, currency_decimals(), '.', ','));
                                        } else {
                                            $set('min_qty', null);
                                            $set('max_qty', null);
                                            $set('qty', null);
                                            $set('unit_price', null);
                                            $set('price', null);
                                        }
                                    }),

                                TextInput::make('qty')
                                    ->label(__('fields.qty'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(fn(Forms\Get $get) => $get('min_qty'))
                                    ->maxValue(fn(Forms\Get $get) => $get('max_qty'))
                                    ->live(true)
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set){
                                        $item = InvoiceItem::find($get('invoice_item_id'));

                                        if ($item and $state) {
                                            $set('unit_price', number_format($item->price, currency_decimals(), '.', ','));
                                            $set('price', number_format($state * $item->price, currency_decimals(), '.', ','));
                                        } else {
                                            $set('unit_price', null);
                                            $set('price', null);
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
                                    ->prefixIcon('heroicon-o-calculator')
                                    ->suffix(fn() => main_currency_iso_code())
                                    ->dehydrated(false)
                                    ->readOnly(),

                                TextInput::make('price')
                                    ->label(__('fields.price'))
                                    ->prefixIcon('heroicon-o-calculator')
                                    ->suffix(fn() => main_currency_iso_code())
                                    ->dehydrated(false)
                                    ->readOnly(),

                            ])
                    ]),

                Forms\Components\Section::make()->schema([
                    Forms\Components\Textarea::make('notes')
                        ->cols(5)
                        ->rows(5)
                        ->label(__('fields.notes')),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->action(function (SalesReturns $record) {
                    $record->details()->delete();
                    $record->delete();
                    fns()->deleted();
                }),

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
