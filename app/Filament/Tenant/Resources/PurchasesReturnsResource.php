<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\PurchasesReturnsResource\Pages;
use App\Filament\Tenant\Resources\PurchasesReturnsResource\RelationManagers;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PurchasesReturns;
use Awcodes\FilamentTableRepeater\Components\TableRepeater;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Resources\Resource;
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
                Forms\Components\Section::make()->schema([
                    hidden_user_id_field(),
                    hidden_tenant_id_field(),

                    Forms\Components\Select::make('invoice_id')
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
                            ->relationship('details')
                            ->label(__('fields.items'))
                            ->emptyLabel(__('fields.no_records_placeholder'))
                            ->addActionLabel(__('fields.add'))
                            ->alignHeaders(fn() => app()->getLocale() == "ar" ? "right" : "left")
                            ->hideLabels()
                            ->defaultItems(0)
                            ->deleteAction(
                                fn(Forms\Components\Actions\Action $action) => $action->requiresConfirmation(),
                            )
                            ->live()
                            ->columnWidths([
                                'invoice_item_id' => '200px',
                                'qty' => '100px',
                                'unit_price' => '150px',
                                'price' => '150px',
                            ])
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
                                                if ($livewire instanceof Pages\CreatePurchasesReturns)
                                                    fns()->sendWarning("تم إخفاء عناصر تم إرجاعها مسبقآ");
                                            }
                                        }
                                        return $data;
                                    })->afterStateUpdated(function ($state, Forms\Set $set) {
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
