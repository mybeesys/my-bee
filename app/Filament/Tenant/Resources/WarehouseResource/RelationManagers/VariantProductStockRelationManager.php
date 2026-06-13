<?php

namespace App\Filament\Tenant\Resources\WarehouseResource\RelationManagers;

use App\Models\ItemStock;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class VariantProductStockRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('fields.product_type_variants');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->variants->count();
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
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('fields.name')),

                Tables\Columns\IconColumn::make('available')
                    ->label(__('fields.available_stock'))
                    ->boolean()
                    ->getStateUsing(function (ProductVariant $record) {
                        if ($record->isInventoryUnlimited())
                            return true;
                        return $record->qty > 0;
                    }),

                Tables\Columns\TextColumn::make('qty')
                    ->label(__('fields.total_stock'))
                    ->getStateUsing(function (ProductVariant $record) {
                        if($record->isInventoryUnlimited())
                            return __('fields.unlimited');
                        return $record->qty;
                    }),

                Tables\Columns\TextColumn::make('sold')
                    ->label(__('fields.sold_stock'))
                    ->getStateUsing(function ($record) {
                        return $record->qty_out;
                    }),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label(__('fields.purchase_price'))
                    ->getStateUsing(function ($record) {
                        if (is_number($record->unit_cost)) {
                            return main_currency_iso_code() . " " . format_amount($record->unit_cost);
                        }
                        return "-";
                    }),

                Tables\Columns\TextColumn::make('price')
                    ->label(__('fields.price'))
                    ->tooltip(function ($record) {
                        return numbers_to_words($record->retail_price);
                    })
                    ->getStateUsing(function ($record) {
                        return main_currency_iso_code() . " " . format_amount($record->retail_price);
                    }),

            ])
            ->filters([
                //
            ])
            ->headerActions([
            ])
            ->actions([
                Tables\Actions\Action::make('update_qtys')
                    ->label(__('fields.update_qtys'))
                    ->modalWidth(MaxWidth::Small)
                    ->fillForm(function (ProductVariant $record){
                        return [
                            'qty' => $record->qty,
                            'unlimited_qty' => $record->unlimited_qty,
                        ];
                    })
                    ->form([
                        Forms\Components\Section::make()->schema([

                            Forms\Components\TextInput::make('qty')
                                ->label(__('fields.qty'))
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(PHP_INT_MAX)
                                ->visible(fn(Forms\Get $get) => $get('unlimited_qty') == false)
                                ->extraInputAttributes(['min' => 0, 'max' => PHP_INT_MAX]),

                            Forms\Components\Checkbox::make('unlimited_qty')
                                ->label(__('fields.unlimited_qty'))
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    if ($state) {
                                        $set('qty', null);
                                    } else {
                                        $set('qty', 0);
                                    }
                                }),

                        ])
                    ])
                    ->action(function (ProductVariant $record, array $data) {
                        $record->update(['qty' => $data['qty'] ?? null, 'unlimited_qty' => $data['unlimited_qty']]);
                        fns()->saved();
                    })
            ])
            ->bulkActions([
            ]);
    }
}
