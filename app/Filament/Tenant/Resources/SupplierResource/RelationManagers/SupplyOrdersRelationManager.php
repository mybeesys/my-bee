<?php

namespace App\Filament\Tenant\Resources\SupplierResource\RelationManagers;

use App\Filament\Tenant\Resources\PurchaseInvoiceResource;
use App\Filament\Tenant\Resources\SupplyOrderResource;
use App\Models\SupplyOrder;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SupplyOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'supplyOrders';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('fields.supply_orders');
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('no')
            ->columns([
                Tables\Columns\TextColumn::make('no')
                    ->label(__('fields.reference_code'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('fields.description'))
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.created_at'))
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                SupplyOrderResource::configureInvoiceTableActionGroup(Tables\Actions\ActionGroup::make([
                    SupplyOrderResource::shareSupplyOrderUrlTableAction(),

                    Tables\Actions\Action::make('make_purchases_invoice_from_supply_order')
                        ->label(__('fields.make_purchases_invoice_from_supply_order'))
                        ->icon('heroicon-o-document-plus')
                        ->color(Color::Green)
                        ->url(fn (SupplyOrder $record) => PurchaseInvoiceResource::getUrl('create', ['supply_order_id' => $record->id]), true),

                    Tables\Actions\EditAction::make()
                        ->url(fn (SupplyOrder $record) => SupplyOrderResource::getUrl('edit', ['record' => $record->id]), true),
                ])),
            ])
            ->bulkActions([]);
    }

    protected function canCreate(): bool
    {
        return false;
    }
}
