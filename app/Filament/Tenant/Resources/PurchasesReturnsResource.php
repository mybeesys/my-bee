<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\PurchasesReturnsResource\Pages;
use App\Filament\Tenant\Resources\PurchasesReturnsResource\RelationManagers;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PurchasesReturns;
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

class PurchasesReturnsResource extends Resource
{
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

                    Forms\Components\Select::make('invoice_id')
                        ->required()
                        ->disabled(fn(Page $livewire) => $livewire instanceof Pages\EditPurchasesReturns)
                        ->label(__('fields.purchase_invoice'))
                        ->searchable()
                        ->live()
                        ->options(function ($livewire) {
                            $data = [];
                            if ($livewire instanceof Pages\CreatePurchasesReturns)
                                $invoices = Invoice::with(['supplier'])->doesntHave('purchasesReturns')->purchases()->where('temp', 0)->get();
                            else
                                $invoices = Invoice::with(['supplier'])->purchases()->where('temp', 0)->get();

                            foreach ($invoices as $invoice) {
                                $data[$invoice->id] = $invoice->supplier->name . " - " . $invoice->no;
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
                                                if ($livewire instanceof Pages\CreatePurchasesReturns)
                                                    fns()->sendWarning("تم إخفاء عناصر تم إرجاعها مسبقآ");
                                            }
                                        }
                                        return $data;
                                    })
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
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
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set){
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
                                    ->prefixIcon('heroicon-o-calculator')
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


                Tables\Columns\TextColumn::make('invoice.supplier.name')
                    ->label(__('fields.supplier'))
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
                Tables\Actions\DeleteAction::make()->action(function (PurchasesReturns $record) {
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
            'index' => Pages\ListPurchasesReturns::route('/'),
            'create' => Pages\CreatePurchasesReturns::route('/create'),
            'edit' => Pages\EditPurchasesReturns::route('/{record}/edit'),
        ];
    }
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['details', 'invoice.supplier', 'user']);
    }
}
