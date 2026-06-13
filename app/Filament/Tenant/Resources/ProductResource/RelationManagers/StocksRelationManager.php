<?php

namespace App\Filament\Tenant\Resources\ProductResource\RelationManagers;

use App\Filament\Tenant\Resources\PurchaseInvoiceResource;
use App\Models\ItemStock;
use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class StocksRelationManager extends RelationManager
{
    protected static string $relationship = 'allStocks';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('fields.stock');
    }

    /**
     * @return string|null
     */
    public static function getModelLabel(): ?string
    {
        return __('fields.stock');
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
            ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {

        return $table
            ->description('يمكنك إضافة مخزون عن طريق فاتورة المشتريات')
            ->columns([

                Tables\Columns\TextColumn::make('no')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->label(__('fields.reference_code')),

                Tables\Columns\TextColumn::make('item.name')
                    ->label(__('fields.product')),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label(__('fields.warehouse'))
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('fields.type'))
                    ->toggleable()
                    ->getStateUsing(function ($record) {
                        $local = app()->getLocale();

                        if ($record->type == 'purchase') {
                            return $local == "en" ? "Purchases" : "مشتريات";
                        } else if ($record->type == 'opening-stock') {
                            return $local == "en" ? "Opening stock" : "مخزون إفتتاحي";
                        } else if ($record->type == 'moved') {
                            return $local == "en" ? "Moved" : "مخزون منقول";
                        }
                        return 'unknown';
                    }),

                Tables\Columns\IconColumn::make('available')
                    ->label(__('fields.available_stock'))
                    ->boolean()
                    ->toggleable()
                    ->getStateUsing(function ($record) {
                        return $record->available;
                    }),

                Tables\Columns\TextColumn::make('qty_in')
                    ->toggleable()
                    ->label(__('fields.total_stock')),

                Tables\Columns\TextColumn::make('sold')
                    ->toggleable()
                    ->label(__('fields.sold_stock'))
                    ->getStateUsing(function ($record) {
                        return $record->qty_out;
                    }),

                Tables\Columns\TextColumn::make('qty_moved')
                    ->toggleable()
                    ->label(__('fields.moved'))
                    ->action(function (ItemStock $record): void {

                        $stock = ItemStock::with('warehouse')->where('stock_id', $record->id)->first();

                        if ($stock) {
                            $name = $stock->warehouse->name;
                            $message = app()->getLocale() == "en" ? "This stock moved to ($name)" : "تم نقل المخزون إلي ($name)";
                            Notification::make()
                                ->title($message)
                                ->warning()
                                ->send();
                        }
                    }),

                Tables\Columns\TextColumn::make('available_stock')
                    ->label(__('fields.available_stock'))
                    ->toggleable()
                    ->getStateUsing(function ($record) {
                        return $record->available;
                    }),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label(__('fields.purchase_price'))
                    ->toggleable()
                    ->tooltip(function ($record) {
                        return main_currency_iso_code() . " " . format_amount($record->unit_cost);
                    })
                    ->getStateUsing(function ($record) {
                        return main_currency_iso_code() . " " . format_amount($record->unit_cost);
                    }),

                Tables\Columns\TextColumn::make('unit_retail')
                    ->label(__('fields.sale_price'))
                    ->toggleable()
                    ->tooltip(function ($record) {
                        if ($record->item?->lastPrice)
                            return numbers_to_words($record->item->lastPrice->retail_price);

                        return "-";
                    })
                    ->getStateUsing(function ($record) {
                        if ($record->item?->lastPrice)
                            return main_currency_iso_code() . " " . format_amount($record->item->lastPrice->retail_price);

                        return "-";
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->toggleable()
                    ->label(__('fields.created_at'))
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('add_stock')
                    ->label(__('fields.add_purchases'))
                    ->url(PurchaseInvoiceResource::getUrl('create'))
            ])
            ->actions([
            ])
            ->bulkActions([
            ]);
    }
}
