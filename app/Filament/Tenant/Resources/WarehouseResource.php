<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ProductResource\Widgets\PricingOverview;
use App\Filament\Tenant\Resources\WarehouseResource\Pages;
use App\Filament\Tenant\Resources\WarehouseResource\RelationManagers;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Warehouse;
use App\Rules\UniqueTenantItemRule;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $slug = "warehouses/warehouses";

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('fields.warehouses');
    }

    public static function getLabel(): ?string
    {
        return __('fields.warehouse');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.warehouses');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }


    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()->schema([

                    hidden_tenant_id_field(),

                    Forms\Components\TextInput::make('name')
                        ->label(__('fields.name'))
                        ->autofocus()
                        ->rules([new UniqueTenantItemRule(Warehouse::class, 'name', $form->getRecord()?->id)])
                        ->required(),

                    Forms\Components\TextInput::make('address')
                        ->label(__('fields.address')),

                    Forms\Components\TextInput::make('phone')
                        ->label(__('fields.phone')),

                ])->columns(3),

                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\RichEditor::make('description')
                            ->label(__('fields.description')),
                    ]),
            ]);
    }


    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('fields.name'))
                    ->extraAttributes(['class' => 'text-success-700'])
                    ->searchable(),
                Tables\Columns\TextColumn::make('address')
                    ->label(__('fields.address'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('fields.phone'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('stocks')
                    ->label(__('fields.products'))
                    ->getStateUsing(function ($record) {
                        return $record->stocks->pluck('item_id')->unique()->count();
                    }),

                Tables\Columns\TextColumn::make('warehouse_items_cost')
                    ->label(__('fields.warehouse_items_cost'))
                    ->tooltip(function ($record) {
                        $total = 0;
                        foreach ($record->stocks as $stock) {
                            $total += $stock->getTotalCost();
                        }
                        return numbers_to_words($total);
                    })
                    ->getStateUsing(function (Warehouse $record) {
                        $total = 0;
                        foreach ($record->stocks as $stock) {
                            $total += $stock->getTotalCost();
                        }
                        return setting('main_currency') . " " . format_amount($total,);
                    }),

//                    Tables\Columns\TextColumn::make('description')
//                        ->label(__('fields.description'))
//                        ->getStateUsing(function ($record) {
//                            return new HtmlString($record->description);
//                        })
//                        ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.created_at'))
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('delete')
                        ->label(__('fields.delete'))
                        ->icon('heroicon-o-trash')
                        ->action(function (Warehouse $record) {
                            if ($record->stocks->isNotEmpty()) {
                                Notification::make()
                                    ->title(__('fields.record_in_use_alert'))
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $record->delete();

                            Notification::make()
                                ->title(__('fields.record_deleted_alert'))
                                ->success()
                                ->send();

                        })
                        ->requiresConfirmation()
                        ->color('danger'),
                ]),

            ])
            ->bulkActions([
//                Tables\Actions\DeleteBulkAction::make(),
            ])->deferLoading();
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StocksRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            PricingOverview::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['stocks.item.lastPrice', 'stocks.stock'])->latest(); // TODO: Change the autogenerated stub
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
